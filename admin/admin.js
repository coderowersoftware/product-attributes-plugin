// Enhanced jQuery ready with error handling and debug support
jQuery(document).ready(function($) {
    'use strict';
    
    // Debug logging function
    function ecvLog(message, data) {
        if (typeof ecvAdminConfig !== 'undefined' && ecvAdminConfig.debug) {
            console.log('ECV Admin:', message, data || '');
        }
    }
    
    // Error handling wrapper
    function ecvTryCatch(fn, context) {
        try {
            return fn();
        } catch (error) {
            console.error('ECV Admin Error in ' + context + ':', error);
            if (typeof ecvAdminConfig !== 'undefined' && ecvAdminConfig.debug) {
                alert('ECV Admin Error in ' + context + ': ' + error.message);
            }
            return false;
        }
    }
    
    ecvLog('Initializing ECV Admin Panel');
    
    const container = $('#ecv-variations-admin');
    
    if (container.length === 0) {
        ecvLog('WARNING: Admin container not found. Retrying in 500ms...');
        setTimeout(function() {
            if ($('#ecv-variations-admin').length > 0) {
                ecvLog('Admin container found on retry, reinitializing...');
                // Re-run initialization
                setTimeout(function() { location.reload(); }, 100);
            } else {
                ecvLog('ERROR: Admin container still not found after retry');
            }
        }, 500);
        return;
    }
    
    ecvLog('Admin container found, proceeding with initialization');
    let data = window.ecv_variations_data || [];
    let combinations = window.ecv_combinations_data || [];
    let groupedData = window.ecv_grouped_data || []; // Deprecated but keep for compatibility
    let crossGroupData = window.ecv_cross_group_data || [];
    let variationMode = window.ecv_variation_mode || 'traditional';

    // Toggle sections by variation mode
    function toggleModeSections(mode){
        const m = mode || ($('#ecv_variation_mode').val() || variationMode || 'traditional');
        $('#ecv-attributes-section').toggle(m === 'traditional');
        $('#ecv-cross-group-section').toggle(m === 'cross_group');
    }
    // Initial toggle in case template styles didn’t reflect
    toggleModeSections(variationMode);

    // Listen for variation mode changes and toggle sections live
    container.on('change', '#ecv_variation_mode', function(){
        variationMode = $(this).val();
        toggleModeSections(variationMode);
    });
    
    // Initialize group images from server data - FIX ARRAY ISSUE
    let groupImages = {}; // Always start with empty object
    
    if (window.ecv_group_images_data) {
        try {
            let serverData;
            if (typeof window.ecv_group_images_data === 'string') {
                serverData = JSON.parse(window.ecv_group_images_data);
            } else {
                serverData = window.ecv_group_images_data;
            }
            
            // CRITICAL: Force creation of PLAIN OBJECT - no array inheritance
            if (serverData && typeof serverData === 'object') {
                groupImages = Object.create(null); // Create object with no prototype
                for (let key in serverData) {
                    if (serverData.hasOwnProperty(key) && typeof serverData[key] === 'string') {
                        groupImages[key] = serverData[key];
                    }
                }
                // Convert to regular object
                const regularObj = {};
                for (let key in groupImages) {
                    regularObj[key] = groupImages[key];
                }
                groupImages = regularObj;
            }
            
            console.log('ECV Debug: Initialized group images:', groupImages);
            console.log('ECV Debug: Type:', typeof groupImages, Object.prototype.toString.call(groupImages));
            console.log('ECV Debug: JSON test:', JSON.stringify(groupImages));
            
        } catch (e) {
            console.warn('ECV Debug: Parse error:', e);
            groupImages = {};
        }
    }
    
    // Ensure hidden input exists early
    function ensureGroupImagesInput() {
        let hiddenInput = $('#ecv_group_images_data');
        if (hiddenInput.length === 0) {
            $('<input type="hidden" name="ecv_group_images_data" id="ecv_group_images_data" />').appendTo('#ecv-variations-admin');
            console.log('ECV Debug: Created hidden input for group images');
        }
        return $('#ecv_group_images_data');
    }
    
    // Ensure groupImages is always a proper plain object
    function ensureGroupImagesIsObject() {
        const objType = Object.prototype.toString.call(groupImages);
        if (!groupImages || typeof groupImages !== 'object' || objType !== '[object Object]' || Array.isArray(groupImages)) {
            console.log('ECV Debug: FIXING groupImages - was type:', typeof groupImages, 'toString:', objType);
            const fixedObj = {};
            if (groupImages && typeof groupImages === 'object') {
                for (let key in groupImages) {
                    if (groupImages.hasOwnProperty(key) && typeof groupImages[key] === 'string') {
                        fixedObj[key] = groupImages[key];
                    }
                }
            }
            groupImages = fixedObj;
            console.log('ECV Debug: FIXED groupImages to:', groupImages, 'New toString:', Object.prototype.toString.call(groupImages));
        }
        return groupImages;
    }
    
    // Initialize hidden input immediately
    ensureGroupImagesInput();

    // Render attributes section
    function renderAttributes() {
        let html = '';
        data.forEach((attr, ai) => {
            // Parse group names for this attribute
            let groupNames = (attr.groups || '').split(',').map(g => g.trim()).filter(g => g);
            let displayType = attr.display_type || 'buttons';
            
            html += `<div class="ecv-attribute">
                <div class="ecv-attr-header">
                    <input type="text" class="ecv-attr-name" placeholder="Attribute name (e.g. Size)" value="${attr.name||""}" />
                    <select class="ecv-attr-display-type" data-ai="${ai}" style="margin-left:8px;">
                        <option value="buttons"${displayType==='buttons'?' selected':''}>Buttons</option>
                        <option value="dropdown"${displayType==='dropdown'?' selected':''}>Dropdown</option>
                        <option value="radio"${displayType==='radio'?' selected':''}>Radio Buttons</option>
                    </select>
                    <button type="button" class="ecv-remove-attr button" data-ai="${ai}" style="margin-left:8px;">Remove</button>
                </div>
                <div class="ecv-attr-secondary" style="margin-top:8px;">
                    <input type="text" class="ecv-attr-groups" placeholder="Groups (comma separated, e.g. Signature finishes, Luxe finishes)" value="${attr.groups||""}" style="width:100%;" />
                </div>
                <div class="ecv-variants-list">`;
            (attr.variants||[]).forEach((v, vi) => {
                let groupVal = v.group || '';
                html += `<div class="ecv-variant">
                    <input type="text" class="ecv-variant-name" placeholder="Variant name (e.g. Large)" value="${v.name||''}" />
                    <select class="ecv-variant-group-select" data-ai="${ai}" data-vi="${vi}">`;
                html += `<option value="">(No group)</option>`;
                groupNames.forEach(g => {
                    html += `<option value="${g.replace(/"/g,'&quot;')}"${groupVal===g?' selected':''}>${g}</option>`;
                });
                html += `</select>`;
                html += `<button type="button" class="ecv-upload-image button" data-ai="${ai}" data-vi="${vi}">Image</button>
                    <span class="ecv-image-preview">${v.image ? `<img src="${v.image}" />` : ''}</span>
                    <button type="button" class="ecv-remove-variant button" data-ai="${ai}" data-vi="${vi}">Remove</button>
                </div>`;
            });
            html += `<button type="button" class="ecv-add-variant button" data-ai="${ai}">Add Option</button>`;
            html += '</div></div>';
        });
        $('#ecv-attributes-list').html(html);
        $('#ecv_variations_data').val(JSON.stringify(data));
    }

    // Generate all possible combinations
    function generateCombinations() {
        let result = [];
        function cartesian(arr) {
            return arr.reduce((a, b) => {
                return a.flatMap(x => b.map(y => [...x, y]));
            }, [[]]);
        }
        // Get all variant arrays
        let variantArrays = data.map(attr => 
            attr.variants.map((v, i) => ({
                attribute: attr.name,
                name: v.name,
                image: v.image || ''
            }))
        );
        // Generate all combinations
        if (variantArrays.length) {
            let combos = cartesian(variantArrays);
            combos.forEach((combo, i) => {
                result.push({
                    id: i,
                    enabled: true,
                    sku: '',
                    price: '',
                    stock: '',
                    variants: combo
                });
            });
        }
        return result;
    }

    // Render combinations matrix
    function renderCombinations() {
        let html = '<table class="ecv-combinations-table">';
        // Headers
        html += '<thead><tr>';
        html += '<th><input type="checkbox" class="ecv-select-all" /></th>';
        html += '<th>Enabled</th>';
        data.forEach(attr => {
            html += `<th>${attr.name}</th>`;
        });
        html += '<th>SKU</th><th>Price</th><th>Sale Price</th><th>Stock</th><th>Main Image</th></tr></thead>';
        // Rows
        html += '<tbody>';
        combinations.forEach((combo, ci) => {
            html += `<tr class="ecv-combination-row${combo.enabled ? '' : ' disabled'}" data-ci="${ci}">`;
            html += `<td><input type="checkbox" class="ecv-select-combination" /></td>`;
            html += `<td><input type="checkbox" class="ecv-combination-enabled" ${combo.enabled ? 'checked' : ''} /></td>`;
            combo.variants.forEach(v => {
                html += `<td>${v.name}</td>`;
            });
            html += `<td><input type="text" class="ecv-combination-sku" value="${combo.sku||''}" /></td>`;
            html += `<td><input type="number" class="ecv-combination-price" value="${combo.price||''}" step="0.01" /></td>`;
            html += `<td><input type="number" class="ecv-combination-sale-price" value="${combo.sale_price||''}" step="0.01" /></td>`;
            html += `<td><input type="number" class="ecv-combination-stock" value="${combo.stock||''}" /></td>`;
            let previewUrl = combo.main_image_url || combo.main_image || '';
            html += `<td><input type="hidden" class="ecv-combination-main-image-url" value="${combo.main_image_url||''}" />`;
            html += `<input type="hidden" class="ecv-combination-main-image-id" value="${combo.main_image_id||''}" />`;
            html += `<button type="button" class="ecv-upload-combo-image button" data-ci="${ci}">Image</button> `;
            html += `<span class="ecv-combo-image-preview">${previewUrl ? `<img src="${previewUrl}" style="max-width:40px;vertical-align:middle;" />` : ''}</span></td>`;
            html += '</tr>';
        });
        html += '</tbody></table>';
        $('#ecv-combinations-list').html(html);
        $('#ecv_combinations_data').val(JSON.stringify(combinations));
        console.log('Rendered combinations:', JSON.stringify(combinations));
    }



    // Initial render
    renderAttributes();
    renderCombinations();

    // Attribute events
    container.on('click', '#ecv-add-attribute', function(){
        data.push({name:'',variants:[]});
        renderAttributes();
    });
    container.on('click', '.ecv-remove-attr', function(){
        data.splice($(this).data('ai'),1);
        renderAttributes();
    });
    container.on('click', '.ecv-add-variant', function(){
        let ai = $(this).data('ai');
        data[ai].variants = data[ai].variants || [];
        data[ai].variants.push({name:'',image:''});
        renderAttributes();
    });
    container.on('click', '.ecv-remove-variant', function(){
        let ai = $(this).data('ai'), vi = $(this).data('vi');
        data[ai].variants.splice(vi,1);
        renderAttributes();
    });
    container.on('input', '.ecv-attr-name', function(){
        let ai = $(this).closest('.ecv-attribute').index();
        data[ai].name = $(this).val();
        $('#ecv_variations_data').val(JSON.stringify(data));
    });
    // Update groups field (on blur, not input)
    container.on('blur', '.ecv-attr-groups', function(){
        let ai = $(this).closest('.ecv-attribute').index();
        data[ai].groups = $(this).val();
        renderAttributes(); // re-render to update dropdowns
    });
    container.on('input', '.ecv-variant-name', function(){
        let ai = $(this).closest('.ecv-attribute').index();
        let vi = $(this).closest('.ecv-variant').index();
        data[ai].variants[vi].name = $(this).val();
        $('#ecv_variations_data').val(JSON.stringify(data));
    });
    // Group dropdown change
    container.on('change', '.ecv-variant-group-select', function(){
        let ai = $(this).data('ai'), vi = $(this).data('vi');
        let val = $(this).val();
        data[ai].variants[vi].group = val;
        $('#ecv_variations_data').val(JSON.stringify(data));
    });
    
    // Display type change
    container.on('change', '.ecv-attr-display-type', function(){
        let ai = $(this).data('ai');
        let val = $(this).val();
        data[ai].display_type = val;
        $('#ecv_variations_data').val(JSON.stringify(data));
    });


    // Image upload
    container.on('click', '.ecv-upload-image', function(e){
        e.preventDefault();
        let ai = $(this).data('ai'), vi = $(this).data('vi');
        let frame = wp.media({title:'Select Image',multiple:false});
        frame.on('select',function(){
            let url = frame.state().get('selection').first().toJSON().url;
            data[ai].variants[vi].image = url;
            renderAttributes();
        });
        frame.open();
    });

    // Combinations events
    container.on('click', '#ecv-create-variations', function(){
        combinations = generateCombinations();
        renderCombinations();
    });

    container.on('change', '.ecv-select-all', function(){
        let checked = $(this).prop('checked');
        $('.ecv-select-combination').prop('checked', checked);
    });

    container.on('change', '.ecv-combination-enabled', function(){
        let ci = $(this).closest('tr').data('ci');
        combinations[ci].enabled = $(this).prop('checked');
        $(this).closest('tr').toggleClass('disabled', !combinations[ci].enabled);
        $('#ecv_combinations_data').val(JSON.stringify(combinations));
    });

    // Save combinations data to hidden input and log for debug
    function saveCombinationsData() {
        console.log('Saving combinations:', JSON.stringify(combinations));
        $('#ecv_combinations_data').val(JSON.stringify(combinations));
    }

    container.on('input', '.ecv-combination-sku, .ecv-combination-price, .ecv-combination-sale-price, .ecv-combination-stock', function(){
        let ci = $(this).closest('tr').data('ci');
        let field = $(this).attr('class').replace('ecv-combination-', '').replace(/ .*/, '').replace(/-/g, '_');
        combinations[ci][field] = $(this).val();
        saveCombinationsData();
    });

    // Bulk edit
    container.on('click', '#ecv-bulk-edit', function(){
        let selected = $('.ecv-select-combination:checked').map(function(){
            return $(this).closest('tr').data('ci');
        }).get();
        if (!selected.length) return;
        
        let html = `
        <div class="ecv-bulk-edit-form">
            <p><label><input type="checkbox" id="ecv-bulk-enabled" /> Enabled</label></p>
            <p><input type="number" id="ecv-bulk-price" placeholder="Price" step="0.01" /></p>
            <p><input type="number" id="ecv-bulk-stock" placeholder="Stock" /></p>
        </div>`;
        
        // Use WordPress dialog or custom modal here
        if (confirm('Apply to selected variations?')) {
            let enabled = $('#ecv-bulk-enabled').prop('checked');
            let price = $('#ecv-bulk-price').val();
            let stock = $('#ecv-bulk-stock').val();
            
            selected.forEach(ci => {
                if (enabled !== undefined) combinations[ci].enabled = enabled;
                if (price) combinations[ci].price = price;
                if (stock) combinations[ci].stock = stock;
            });
            renderCombinations();
        }
    });

    // Main Image upload for combinations
    container.on('click', '.ecv-upload-combo-image', function(e){
        e.preventDefault();
        let ci = $(this).data('ci');
        let frame = wp.media({title:'Select Image',multiple:false});
        frame.on('select',function(){
            let selection = frame.state().get('selection').first().toJSON();
            combinations[ci].main_image_url = selection.url;
            combinations[ci].main_image_id = selection.id;
            renderCombinations();
        });
        frame.open();
    });

    // Grouped format functions
    function renderGroups() {
        let html = '';
        groupedData.forEach((group, gi) => {
            html += `<div class="ecv-group">
                <div class="ecv-group-header">
                    <input type="text" class="ecv-group-name" placeholder="Group name (e.g. Premium Finishes)" value="${group.name || ''}" />
                    <input type="number" class="ecv-group-price" placeholder="Group price" value="${group.price || ''}" step="0.01" />
                    <button type="button" class="ecv-upload-group-image button" data-gi="${gi}">Group Image</button>
                    <span class="ecv-group-image-preview">${group.image ? `<img src="${group.image}" />` : ''}</span>
                    <button type="button" class="ecv-remove-group button" data-gi="${gi}">Remove Group</button>
                </div>
                <div class="ecv-group-description">
                    <textarea class="ecv-group-desc" placeholder="Group description">${group.description || ''}</textarea>
                </div>
                <div class="ecv-group-variations">
                    <h5>Variations in this group:</h5>`;
            
            (group.variations || []).forEach((variation, vi) => {
                html += `<div class="ecv-group-variation">
                    <input type="text" class="ecv-variation-name" placeholder="Variation name" value="${variation.value || ''}" />
                    <button type="button" class="ecv-upload-variation-image button" data-gi="${gi}" data-vi="${vi}">Image</button>
                    <span class="ecv-variation-image-preview">${variation.image ? `<img src="${variation.image}" />` : ''}</span>
                    <button type="button" class="ecv-remove-variation button" data-gi="${gi}" data-vi="${vi}">Remove</button>
                </div>`;
            });
            
            html += `<button type="button" class="ecv-add-variation button" data-gi="${gi}">Add Variation</button>
                </div>
            </div>`;
        });
        $('#ecv-groups-list').html(html);
        $('#ecv_grouped_data').val(JSON.stringify(groupedData));
    }

    // Toggle between variation modes
    function toggleVariationMode() {
        const mode = $('#ecv_variation_mode').val();
        if (mode === 'cross_group') {
            $('#ecv-attributes-section').hide();
            $('#ecv-grouped-section').hide();
            $('#ecv-cross-group-section').show();
            renderCrossGroups();
        } else if (mode === 'grouped') {
            $('#ecv-attributes-section').hide();
            $('#ecv-cross-group-section').hide();
            $('#ecv-grouped-section').show();
            renderGroups();
        } else {
            $('#ecv-grouped-section').hide();
            $('#ecv-cross-group-section').hide();
            $('#ecv-attributes-section').show();
            renderAttributes();
        }
    }

    // Initialize based on current mode
    toggleVariationMode();
    
    // Initialize group images UI on page load for cross-group mode
    if (variationMode === 'cross_group') {
        // Check if we have existing cross group data with groups definition
        let existingGroupsDefinition = '';
        if (crossGroupData && crossGroupData.groupsDefinition) {
            existingGroupsDefinition = crossGroupData.groupsDefinition;
        }
        
        // Show group images UI if we have a groups definition
        if (existingGroupsDefinition) {
            showGroupImagesUI(existingGroupsDefinition);
        }
    }

    // Mode change handler with error handling
    $('#ecv_variation_mode').on('change', function() {
        ecvTryCatch(function() {
            ecvLog('Variation mode change triggered', $(this).val());
            variationMode = $(this).val();
            toggleVariationMode();
        }.bind(this), 'mode change handler');
    });

    // Add group
    $(document).on('click', '#ecv-add-group', function() {
        groupedData.push({
            name: '',
            price: 0,
            image: '',
            description: '',
            variations: []
        });
        renderGroups();
    });

    // Remove group
    $(document).on('click', '.ecv-remove-group', function() {
        const gi = $(this).data('gi');
        groupedData.splice(gi, 1);
        renderGroups();
    });

    // Add variation to group
    $(document).on('click', '.ecv-add-variation', function() {
        const gi = $(this).data('gi');
        if (!groupedData[gi].variations) {
            groupedData[gi].variations = [];
        }
        groupedData[gi].variations.push({
            value: '',
            image: ''
        });
        renderGroups();
    });

    // Remove variation from group
    $(document).on('click', '.ecv-remove-variation', function() {
        const gi = $(this).data('gi');
        const vi = $(this).data('vi');
        groupedData[gi].variations.splice(vi, 1);
        renderGroups();
    });

    // Update group data
    $(document).on('input', '.ecv-group-name', function() {
        const gi = $(this).closest('.ecv-group').index();
        groupedData[gi].name = $(this).val();
        $('#ecv_grouped_data').val(JSON.stringify(groupedData));
    });

    $(document).on('input', '.ecv-group-price', function() {
        const gi = $(this).closest('.ecv-group').index();
        groupedData[gi].price = parseFloat($(this).val()) || 0;
        $('#ecv_grouped_data').val(JSON.stringify(groupedData));
    });

    $(document).on('input', '.ecv-group-desc', function() {
        const gi = $(this).closest('.ecv-group').index();
        groupedData[gi].description = $(this).val();
        $('#ecv_grouped_data').val(JSON.stringify(groupedData));
    });

    $(document).on('input', '.ecv-variation-name', function() {
        const gi = $(this).closest('.ecv-group').index();
        const vi = $(this).closest('.ecv-group-variation').index();
        groupedData[gi].variations[vi].value = $(this).val();
        $('#ecv_grouped_data').val(JSON.stringify(groupedData));
    });

    // Group image upload
    $(document).on('click', '.ecv-upload-group-image', function() {
        const gi = $(this).data('gi');
        const frame = wp.media({
            title: 'Select Group Image',
            button: { text: 'Use Image' },
            multiple: false
        });
        frame.on('select', function() {
            let selection = frame.state().get('selection').first().toJSON();
            groupedData[gi].image = selection.url;
            renderGroups();
        });
        frame.open();
    });

    // Variation image upload
    $(document).on('click', '.ecv-upload-variation-image', function() {
        const gi = $(this).data('gi');
        const vi = $(this).data('vi');
        const frame = wp.media({
            title: 'Select Variation Image',
            button: { text: 'Use Image' },
            multiple: false
        });
        frame.on('select', function() {
            let selection = frame.state().get('selection').first().toJSON();
            groupedData[gi].variations[vi].image = selection.url;
            renderGroups();
        });
        frame.open();
    });
    
    // Convert imported cross-group combination data to Excel-like format
    function convertImportedDataToExcelFormat(importedData) {
        console.log('Converting imported data to Excel format:', importedData);
        
        let groupsDefinition = '';
        const combinations = [];
        
        // Extract groups definition from first combination
        if (importedData.length > 0) {
            groupsDefinition = generateGroupsDefinitionFromData(importedData);
        }
        
        // Convert each combination to table row format
        importedData.forEach((combination) => {
            combinations.push({
                combination_name: combination.combination_id.replace('_', '+'),
                price: combination.combination_price || combination.price || '',
                sale_price: combination.combination_sale_price || '',
                stock: combination.combination_stock || '',
                image: combination.combination_image || '',
                description: combination.combination_description || ''
            });
        });
        
        console.log('Converted to Excel format:', { groupsDefinition, combinations });
        return { groupsDefinition, combinations };
    }
    
    // Generate groups definition string from imported data
    function generateGroupsDefinitionFromData(importedData) {
        const attributesMap = {};
        
        importedData.forEach((combination) => {
            const attributes = combination.attributes || {};
            
            Object.keys(attributes).forEach((attrName) => {
                const attrData = attributes[attrName];
                const groupName = attrData.group_name;
                const values = attrData.values || [];
                
                if (!attributesMap[attrName]) {
                    attributesMap[attrName] = {};
                }
                
                if (!attributesMap[attrName][groupName]) {
                    attributesMap[attrName][groupName] = new Set();
                }
                
                values.forEach(value => attributesMap[attrName][groupName].add(value));
            });
        });
        
        // Build groups definition string
        const attributeParts = [];
        Object.keys(attributesMap).forEach((attrName) => {
            const groupParts = [];
            Object.keys(attributesMap[attrName]).forEach((groupName) => {
                const values = Array.from(attributesMap[attrName][groupName]).join(',');
                groupParts.push(groupName + '=' + values);
            });
            attributeParts.push(attrName + ':' + groupParts.join('|'));
        });
        
        return attributeParts.join(';');
    }
    
    // Cross Group Pricing functions
    function renderCrossGroups() {
        console.log('Rendering cross groups with data:', crossGroupData);
        
        let groupsDefinition = '';
        let combinations = [];
        
        // Check if this is imported cross-group data (combination format)
        if (Array.isArray(crossGroupData) && crossGroupData.length > 0 && crossGroupData[0].combination_id) {
            // Convert imported combination data to Excel format
            const excelData = convertImportedDataToExcelFormat(crossGroupData);
            groupsDefinition = excelData.groupsDefinition;
            combinations = excelData.combinations;
        } else if (crossGroupData && crossGroupData.groupsDefinition) {
            // This is already in Excel format
            groupsDefinition = crossGroupData.groupsDefinition || '';
            combinations = crossGroupData.combinations || [];
        }
        
        // Set groups definition textarea
        $('#ecv-groups-definition').val(groupsDefinition);
        
        // Render combinations table
        renderCombinationsTable(combinations);
        
        // Save data
        saveCrossGroupData({ groupsDefinition, combinations });
        
        // Show group images UI if we have groups definition
        if (groupsDefinition) {
            showGroupImagesUI(groupsDefinition);
        }
    }
    
    // Render combinations table (Excel-like)
    function renderCombinationsTable(combinations) {
        let html = '';
        
        combinations.forEach((combination, index) => {
            html += `<tr data-combination-index="${index}">
                <td><input type="text" class="ecv-combination-name" value="${combination.combination_name || ''}" placeholder="G1+C1" /></td>
                <td><input type="number" class="ecv-combination-price" value="${combination.price || ''}" placeholder="25.00" step="0.01" /></td>
                <td><input type="number" class="ecv-combination-sale-price" value="${combination.sale_price || ''}" placeholder="22.50" step="0.01" /></td>
                <td><input type="number" class="ecv-combination-stock" value="${combination.stock || ''}" placeholder="50" /></td>
                <td>
                    <input type="text" class="ecv-combination-image" value="${combination.image || ''}" placeholder="https://example.com/image.jpg" />
                    <button type="button" class="ecv-select-image button" data-combination-index="${index}">Select</button>
                </td>
                <td><input type="text" class="ecv-combination-description" value="${combination.description || ''}" placeholder="Combination description" /></td>
                <td><button type="button" class="ecv-remove-combination button" data-combination-index="${index}">Remove</button></td>
            </tr>`;
        });
        
        $('#ecv-cross-combinations-body').html(html);
    }
    
    // Add empty combination row
    function addCombinationRow() {
        const combinations = getCurrentCombinations();
        combinations.push({
            combination_name: '',
            price: '',
            sale_price: '',
            stock: '',
            image: '',
            description: ''
        });
        renderCombinationsTable(combinations);
        saveCrossGroupData({ 
            groupsDefinition: $('#ecv-groups-definition').val(), 
            combinations 
        });
    }
    
    // Get current combinations from table
    function getCurrentCombinations() {
        const combinations = [];
        $('#ecv-cross-combinations-body tr').each(function() {
            const $row = $(this);
            combinations.push({
                combination_name: $row.find('.ecv-combination-name').val(),
                price: $row.find('.ecv-combination-price').val(),
                sale_price: $row.find('.ecv-combination-sale-price').val(),
                stock: $row.find('.ecv-combination-stock').val(),
                image: $row.find('.ecv-combination-image').val(),
                description: $row.find('.ecv-combination-description').val()
            });
        });
        return combinations;
    }
    
    // Save cross group data
    function saveCrossGroupData(data) {
        crossGroupData = data;
        $('#ecv_cross_group_data').val(JSON.stringify(crossGroupData));
        console.log('Saved cross group data:', crossGroupData);
    }
    
    // Group images management functions
    
    // Parse groups definition and show group images UI
    function showGroupImagesUI(groupsDefinition) {
        if (!groupsDefinition || groupsDefinition.trim() === '') {
            $('#ecv-group-images-section').hide();
            return;
        }
        
        const parsedGroups = parseGroupsForImageUI(groupsDefinition);
        if (Object.keys(parsedGroups).length === 0) {
            $('#ecv-group-images-section').hide();
            return;
        }
        
        let html = '';
        Object.keys(parsedGroups).forEach(attributeName => {
            html += `<div class="ecv-attribute-groups">`;
            html += `<h6>${attributeName} Groups:</h6>`;
            
            Object.keys(parsedGroups[attributeName]).forEach(groupName => {
                const imageKey = attributeName + ':' + groupName;
                const currentImage = groupImages[imageKey] || '';
                
                html += `<div class="ecv-group-image-item" style="display: flex; align-items: center; margin: 8px 0; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">`;
                html += `<strong style="min-width: 80px;">${groupName}:</strong>`;
                html += `<span style="margin: 0 8px; color: #666;">(${parsedGroups[attributeName][groupName].join(', ')})</span>`;
                html += `<button type="button" class="ecv-select-group-image button" data-image-key="${imageKey}" style="margin: 0 8px;">Select Image</button>`;
                html += `<span class="ecv-group-image-preview" style="margin-left: 8px;">${currentImage ? `<img src="${currentImage}" style="max-width: 40px; max-height: 40px; vertical-align: middle;" />` : '<em>No image</em>'}</span>`;
                if (currentImage) {
                    html += `<button type="button" class="ecv-remove-group-image button" data-image-key="${imageKey}" style="margin-left: 8px; color: #dc3232;">Remove</button>`;
                }
                html += `</div>`;
            });
            
            html += `</div>`;
        });
        
        $('#ecv-group-images-list').html(html);
        $('#ecv-group-images-section').show();
        
        // Save group images to hidden field
        saveGroupImages();
    }
    
    // Parse groups definition for UI purposes
    function parseGroupsForImageUI(groupsDefinition) {
        const result = {};
        
        const attributeParts = groupsDefinition.split(';');
        
        attributeParts.forEach(part => {
            const colonIndex = part.indexOf(':');
            if (colonIndex === -1) return;
            
            const attrName = part.substring(0, colonIndex).trim();
            const groupsString = part.substring(colonIndex + 1).trim();
            
            const groupParts = groupsString.split('|');
            result[attrName] = {};
            
            groupParts.forEach(groupPart => {
                const equalsIndex = groupPart.indexOf('=');
                if (equalsIndex === -1) return;
                
                const groupName = groupPart.substring(0, equalsIndex).trim();
                const valuesString = groupPart.substring(equalsIndex + 1).trim();
                const values = valuesString.split(',').map(v => v.trim());
                
                result[attrName][groupName] = values;
            });
        });
        
        return result;
    }
    
    // Save group images to hidden input
    function saveGroupImages() {
        // CRITICAL: Ensure groupImages is a proper object before saving
        ensureGroupImagesIsObject();
        
        const hiddenInput = ensureGroupImagesInput();
        const jsonData = JSON.stringify(groupImages);
        hiddenInput.val(jsonData);
        
        console.log('ECV Debug: saveGroupImages() called');
        console.log('ECV Debug: Group images object:', groupImages);
        console.log('ECV Debug: groupImages type:', typeof groupImages, groupImages.constructor.name);
        console.log('ECV Debug: Is Array?', Array.isArray(groupImages));
        console.log('ECV Debug: JSON data being saved:', jsonData);
        console.log('ECV Debug: Hidden input value after save:', hiddenInput.val());
        console.log('ECV Debug: Hidden input exists in DOM:', hiddenInput.length > 0);
        console.log('ECV Debug: Hidden input name attribute:', hiddenInput.attr('name'));
        
        // Verify data immediately after saving
        setTimeout(() => {
            console.log('ECV Debug: Verification - hidden input value after 100ms:', $('#ecv_group_images_data').val());
        }, 100);
    }
    
    // Parse groups definition and generate combinations with enhanced error handling
    function parseGroupsDefinitionAndGenerateCombinations() {
        return ecvTryCatch(function() {
            ecvLog('Starting groups definition parsing');
            
            const groupsDefinition = $('#ecv-groups-definition').val().trim();
            ecvLog('Groups definition input', groupsDefinition);
            
            if (!groupsDefinition) {
                alert('Please enter a groups definition first.');
                return false;
            }
            
            const combinations = generateCombinationsFromDefinition(groupsDefinition);
            ecvLog('Generated combinations', combinations);
            
            if (!combinations || combinations.length === 0) {
                alert('No combinations were generated. Please check your groups definition format.');
                return false;
            }
            
            renderCombinationsTable(combinations);
            saveCrossGroupData({ groupsDefinition, combinations });
            
            alert('Generated ' + combinations.length + ' combinations successfully!');
            ecvLog('Parsing completed successfully');
            return true;
        }, 'parseGroupsDefinitionAndGenerateCombinations');
    }
    
    // Generate combinations from groups definition
    function generateCombinationsFromDefinition(groupsDefinition) {
        console.log('DEBUG: Input groups definition:', groupsDefinition);
        
        const combinations = [];
        const attributes = {};
        
        // Split by semicolon for different attributes
        const attributeParts = groupsDefinition.split(';');
        console.log('DEBUG: Total input length:', groupsDefinition.length);
        console.log('DEBUG: Input string character by character:', groupsDefinition.split('').map((char, i) => `${i}:"${char}"`));
        console.log('DEBUG: Found', attributeParts.length, 'parts after splitting by ";"');
        console.log('DEBUG: Attribute parts after split by ";":', attributeParts.map((part, i) => `Part ${i}: "${part}"`));
        
        attributeParts.forEach((part, index) => {
            console.log(`DEBUG: Processing attribute part ${index}:`, part);
            
            const colonIndex = part.indexOf(':');
            if (colonIndex === -1) {
                console.log(`DEBUG: No colon found in part ${index}, skipping:`, part);
                return;
            }
            
            const attrName = part.substring(0, colonIndex).trim();
            const groupsString = part.substring(colonIndex + 1).trim();
            
            console.log(`DEBUG: Attribute name: "${attrName}", Groups string: "${groupsString}"`);
            
            // Parse groups: "G1=Matte,Glossy|G2=Textured"
            const groupParts = groupsString.split('|');
            console.log(`DEBUG: Group parts for ${attrName}:`, groupParts);
            
            attributes[attrName] = {};
            
            groupParts.forEach((groupPart, groupIndex) => {
                console.log(`DEBUG: Processing group part ${groupIndex} for ${attrName}:`, groupPart);
                
                const equalsIndex = groupPart.indexOf('=');
                if (equalsIndex === -1) {
                    console.log(`DEBUG: No equals sign found in group part, skipping:`, groupPart);
                    return;
                }
                
                const groupName = groupPart.substring(0, equalsIndex).trim();
                const valuesString = groupPart.substring(equalsIndex + 1).trim();
                const values = valuesString.split(',').map(v => v.trim());
                
                console.log(`DEBUG: Group name: "${groupName}", Values:`, values);
                
                attributes[attrName][groupName] = values;
            });
        });
        
        console.log('DEBUG: Final parsed attributes:', attributes);
        
        // Generate combinations: one group selected from each attribute
        const attributeNames = Object.keys(attributes);
        console.log('DEBUG: All attributes found:', attributeNames);
        
        if (attributeNames.length >= 1) {
            // Get all groups for each attribute
            const allGroupsPerAttribute = [];
            attributeNames.forEach(attrName => {
                const groupNames = Object.keys(attributes[attrName]);
                console.log(`DEBUG: Groups for attribute '${attrName}':`, groupNames);
                allGroupsPerAttribute.push(groupNames.map(groupName => ({
                    attribute: attrName,
                    group: groupName,
                    values: attributes[attrName][groupName]
                })));
            });
            
            console.log('DEBUG: Groups per attribute:', allGroupsPerAttribute);
            
            // Generate cartesian product of all attributes (one group from each)
            const cartesianProduct = generateCartesianProduct(allGroupsPerAttribute);
            console.log('DEBUG: Cartesian product result:', cartesianProduct);
            
            cartesianProduct.forEach((combination, index) => {
                const combinationName = combination.map(item => item.group).join('+');
                console.log(`DEBUG: Generated combination ${index + 1}:`, combinationName);
                
                combinations.push({
                    combination_name: combinationName,
                    price: '',
                    sale_price: '',
                    stock: '',
                    image: '',
                    description: ''
                });
            });
            
            console.log('DEBUG: Final combinations array:', combinations);
        } else {
            console.log('DEBUG: Not enough attributes found (need at least 1)');
        }
        
        return combinations;
    }
    
    // Helper function to generate cartesian product of arrays
    function generateCartesianProduct(arrays) {
        if (arrays.length === 0) return [];
        if (arrays.length === 1) return arrays[0].map(item => [item]);
        
        const result = [];
        const restProduct = generateCartesianProduct(arrays.slice(1));
        
        arrays[0].forEach(item => {
            restProduct.forEach(combination => {
                result.push([item].concat(combination));
            });
        });
        
        return result;
    }
    
    // Render individual attribute with its groups (deprecated - keeping for compatibility)
    function renderCrossAttributeItem(attrName, attribute, index) {
        const groups = attribute.groups || {};
        
        let html = `<div class="ecv-cross-attribute" data-attr-index="${index}">
            <div class="ecv-cross-attr-header">
                <h4>Attribute: <input type="text" class="ecv-cross-attr-name" placeholder="Attribute name (e.g. finish, colour)" value="${attrName}" /></h4>
                <button type="button" class="ecv-remove-cross-attribute button" data-attr-index="${index}">Remove Attribute</button>
            </div>
            <div class="ecv-cross-groups-container">
                <h5>Groups within this attribute:</h5>
                <div class="ecv-cross-groups-list">`;
        
        Object.keys(groups).forEach((groupName, gi) => {
            const group = groups[groupName];
            html += renderCrossGroupItem(groupName, group, index, gi);
        });
        
        html += `</div>
                <button type="button" class="ecv-add-cross-group button" data-attr-index="${index}">Add Group</button>
            </div>
        </div>`;
        
        return html;
    }
    
    function renderCrossGroupItem(groupName, group, attrIndex, groupIndex) {
        const values = group.values || [];
        
        let html = `<div class="ecv-cross-group" data-attr-index="${attrIndex}" data-group-index="${groupIndex}">
            <div class="ecv-cross-group-header">
                <strong>Group ID:</strong> <input type="text" class="ecv-cross-group-name" placeholder="Group ID (e.g. G1, C1)" value="${groupName}" />
                <button type="button" class="ecv-remove-cross-group button" data-attr-index="${attrIndex}" data-group-index="${groupIndex}">Remove Group</button>
            </div>
            <div class="ecv-cross-group-values">
                <h6>Values in this group:</h6>`;
        
        values.forEach((value, vi) => {
            html += `<div class="ecv-cross-value" data-value-index="${vi}">
                <input type="text" class="ecv-cross-value-name" placeholder="Value (e.g. Matte, Red)" value="${value}" />
                <button type="button" class="ecv-remove-cross-value button" data-attr-index="${attrIndex}" data-group-index="${groupIndex}" data-value-index="${vi}">Remove</button>
            </div>`;
        });
        
        html += `<button type="button" class="ecv-add-cross-value button" data-attr-index="${attrIndex}" data-group-index="${groupIndex}">Add Value</button>
            </div>
        </div>`;
        
        return html;
    }
    
    // Parse groups definition and generate combinations
    $(document).on('click', '#ecv-parse-groups-definition', function() {
        const success = parseGroupsDefinitionAndGenerateCombinations();
        if (success) {
            // Show group images UI after successful parsing
            const groupsDefinition = $('#ecv-groups-definition').val().trim();
            showGroupImagesUI(groupsDefinition);
        }
    });
    
    // Update group images UI when groups definition changes
    $(document).on('input', '#ecv-groups-definition', function() {
        const groupsDefinition = $(this).val().trim();
        if (groupsDefinition) {
            showGroupImagesUI(groupsDefinition);
        } else {
            $('#ecv-group-images-section').hide();
        }
        
        saveCrossGroupData({ 
            groupsDefinition: $(this).val(), 
            combinations: getCurrentCombinations() 
        });
    });
    
    // Group image selection
    $(document).on('click', '.ecv-select-group-image', function() {
        const $button = $(this);
        const imageKey = $button.data('image-key');
        
        const frame = wp.media({
            title: 'Select Group Button Image',
            button: { text: 'Use This Image' },
            multiple: false
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            
            console.log('ECV Debug: Image selected for key:', imageKey);
            console.log('ECV Debug: Attachment URL:', attachment.url);
            console.log('ECV Debug: groupImages before assignment:', groupImages);
            console.log('ECV Debug: groupImages type before:', typeof groupImages, groupImages.constructor.name);
            
            // CRITICAL: Ensure we're working with a plain object
            ensureGroupImagesIsObject();
            
            // Set the image URL
            groupImages[imageKey] = attachment.url;
            
            console.log('ECV Debug: groupImages after assignment:', groupImages);
            console.log('ECV Debug: groupImages type after:', typeof groupImages, groupImages.constructor.name);
            console.log('ECV Debug: JSON.stringify test:', JSON.stringify(groupImages));
            
            // Update preview
            $button.siblings('.ecv-group-image-preview').html(`<img src="${attachment.url}" style="max-width: 40px; max-height: 40px; vertical-align: middle;" />`);
            
            // Add remove button if not exists
            if (!$button.siblings('.ecv-remove-group-image').length) {
                $button.parent().append(`<button type="button" class="ecv-remove-group-image button" data-image-key="${imageKey}" style="margin-left: 8px; color: #dc3232;">Remove</button>`);
            }
            
            saveGroupImages();
        });
        
        frame.open();
    });
    
    // Remove group image
    $(document).on('click', '.ecv-remove-group-image', function() {
        const $button = $(this);
        const imageKey = $button.data('image-key');
        
        delete groupImages[imageKey];
        $button.siblings('.ecv-group-image-preview').html('<em>No image</em>');
        $button.remove();
        
        saveGroupImages();
    });
    
    
    // Ensure group images are saved before form submission (multiple selectors)
    $(document).on('submit', 'form#post, #post, form[name="post"], .wrap form', function() {
        console.log('ECV Debug: Form submission detected');
        console.log('ECV Debug: Group images at form submit:', groupImages);
        if (Object.keys(groupImages).length > 0) {
            saveGroupImages();
            console.log('ECV Debug: Group images saved before form submission');
        } else {
            console.log('ECV Debug: No group images to save');
        }
    });
    
    // Also hook into WordPress publish/update buttons
    $(document).on('click', '#publish, #save-post', function() {
        console.log('ECV Debug: Publish/Save button clicked');
        if (Object.keys(groupImages).length > 0) {
            saveGroupImages();
            console.log('ECV Debug: Group images saved on publish/save button click');
        }
    });
    
    // Add combination row
    $(document).on('click', '#ecv-add-combination-row', function() {
        addCombinationRow();
    });
    
    // Remove combination row
    $(document).on('click', '.ecv-remove-combination', function() {
        const index = $(this).data('combination-index');
        $(this).closest('tr').remove();
        
        // Update data
        const combinations = getCurrentCombinations();
        saveCrossGroupData({ 
            groupsDefinition: $('#ecv-groups-definition').val(), 
            combinations 
        });
    });
    
    // Update groups definition
    $(document).on('input', '#ecv-groups-definition', function() {
        saveCrossGroupData({ 
            groupsDefinition: $(this).val(), 
            combinations: getCurrentCombinations() 
        });
    });
    
    // Update combination data on input change
    $(document).on('input', '.ecv-combination-name, .ecv-combination-price, .ecv-combination-sale-price, .ecv-combination-stock, .ecv-combination-image, .ecv-combination-description', function() {
        const combinations = getCurrentCombinations();
        saveCrossGroupData({ 
            groupsDefinition: $('#ecv-groups-definition').val(), 
            combinations 
        });
    });
    
    // Image selection for combinations
    $(document).on('click', '.ecv-select-image', function() {
        const $button = $(this);
        const $input = $button.siblings('.ecv-combination-image');
        
        const frame = wp.media({
            title: 'Select Combination Image',
            button: { text: 'Use This Image' },
            multiple: false
        });
        
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            $input.val(attachment.url);
            
            // Trigger input change to save data
            $input.trigger('input');
        });
        
        frame.open();
    });

});
