jQuery(document).ready(function ($) {
    // Elementor Pro Compatibility: Wait for Elementor to finish loading
    function initECVPlugin() {
        const $root = $('#ecv-frontend-variations');
        if (!$root.length || $root.data('ecv-initialized')) return;
        
        // Mark as initialized to prevent duplicate initialization
        $root.data('ecv-initialized', true);
        
        // Check if we're inside an Elementor widget and wait for it to be fully loaded
        const $elementorWidget = $root.closest('.elementor-widget-container, .elementor-section, .elementor-column');
        if ($elementorWidget.length && $elementorWidget.hasClass('elementor-invisible')) {
            // Wait for Elementor to make the widget visible
            $root.removeData('ecv-initialized');
            setTimeout(initECVPlugin, 100);
            return;
        }
        
        const data = $root.data('ecv');
        const combinations = $root.data('ecv-combinations');
        const basePrice = parseFloat($root.data('ecv-base-price')) || 0;
        const showImages = $root.data('show-images') || 'yes';
        const tooltips = $root.data('ecv-tooltips') || {};
        let selected = [];
        
        if (!combinations) {
            return;
        }

        // Store the original product image src for fallback - Elementor Pro Compatible
        let originalProductImg = $('.woocommerce-product-gallery__image img, .product .wp-post-image, .elementor-widget-image img, .elementor-image img').first().attr('src');
        
        // Enhanced original image storage
        setTimeout(() => {
            $('img').each(function() {
                if (!$(this).data('ecv-original-src') && $(this).attr('src') && 
                    $(this).closest('#ecv-frontend-variations').length === 0) {
                    $(this).data('ecv-original-src', $(this).attr('src'));
                    if ($(this).attr('srcset')) {
                        $(this).data('ecv-original-srcset', $(this).attr('srcset'));
                    }
                }
            });
        }, 500);

        // Initialize selection for traditional variations (works for both traditional and group-based)
        data.forEach((attr, ai) => {
            selected[ai] = null; // Start with nothing selected
        });
        
        // Helper: check cross-group format
        function isCrossGroup() {
            return Array.isArray(combinations) && combinations.some(c => c && c.group_combination_id);
        }

        // Helper: compute valid option names for a given attribute index with current partial selections
        function computeValidOptionsForAttribute(ai) {
            const attr = data[ai];
            const validOptions = new Set();
            let hasAny = false;
            const cross = isCrossGroup();

            (combinations || []).forEach(combo => {
                if (!combo || combo.enabled === false) return;
                const comboVariants = combo.variants || combo.attributes || [];
                if (comboVariants.length === 0) return;

                // Check compatibility with already selected attributes (excluding current ai)
                let compatible = true;
                selected.forEach((sel, i) => {
                    if (i === ai || sel === null) return;
                    const selVariant = data[i].variants[sel];
                    const selName = selVariant && selVariant.name;
                    const selAttr = data[i].name;
                    const selGroup = cross ? selVariant.group : undefined;

                    const found = comboVariants.find(v => {
                        const name = v.name || v.value;
                        const attrName = v.attribute;
                        if (attrName !== selAttr) return false;
                        if (name !== selName) return false;
                        if (cross) {
                            const vGroup = v.group_name;
                            return (selGroup || null) === (vGroup || null);
                        }
                        return true;
                    });
                    if (!found) compatible = false;
                });
                if (!compatible) return;

                // Add valid option names for the target attribute
                comboVariants.forEach(v => {
                    if (v.attribute !== attr.name) return;
                    const name = v.name || v.value;
                    if (!name || (typeof name === 'string' && name.toLowerCase() === 'none')) return;
                    validOptions.add(name);
                    hasAny = true;
                });
            });

            // If nothing is selected yet, allow all non-"none" variants for this attribute
            if (selected.every(s => s === null)) {
                (attr.variants || []).forEach(v => {
                    if (v && v.name && v.name.toLowerCase() !== 'none') {
                        validOptions.add(v.name);
                        hasAny = true;
                    }
                });
            }

            return { validOptions, hasAny };
        }

        // Helper: pick the lowest value variant index among valid options
        function pickLowestVariantIndex(ai, validOptions) {
            const variants = data[ai].variants || [];
            const candidates = variants
                .map((v, idx) => ({ idx, name: v.name }))
                .filter(x => x.name && x.name.toLowerCase() !== 'none' && validOptions.has(x.name));
            if (candidates.length === 0) return null;

            const nums = candidates.map(c => {
                const n = parseFloat(String(c.name).replace(/[^0-9.\-]/g, ''));
                return isNaN(n) ? null : n;
            });
            const allNumeric = nums.every(n => n !== null);

            candidates.sort((a, b) => {
                if (allNumeric) {
                    const na = parseFloat(String(a.name).replace(/[^0-9.\-]/g, ''));
                    const nb = parseFloat(String(b.name).replace(/[^0-9.\-]/g, ''));
                    return na - nb;
                }
                return String(a.name).localeCompare(String(b.name), undefined, { sensitivity: 'base', numeric: true });
            });

            return candidates[0].idx;
        }

        // Preselect: for each attribute, select the lowest value variant that is valid given prior picks
        function autoSelectLowestValuesPerAttribute() {
            if (!Array.isArray(data) || data.length === 0) return;
            selected = data.map(() => null);

            for (let ai = 0; ai < data.length; ai++) {
                const { validOptions } = computeValidOptionsForAttribute(ai);
                const idx = pickLowestVariantIndex(ai, validOptions);
                if (idx !== null && idx !== undefined) {
                    selected[ai] = idx;
                }
            }

            // Fallback: if no full valid combination after this, try to find any enabled combination and adopt its values
            const combo = (function findCombo() {
                const selectedVars = data.map((attr, ai) => ({
                    attribute: attr.name,
                    name: selected[ai] !== null ? attr.variants[selected[ai]].name : null
                }));
                const visibleSelected = selectedVars.filter((v, i) => {
                    const $attrGroup = $root.find(`.ecv-attr-group[data-ai="${i}"]`);
                    return $attrGroup.length ? $attrGroup.is(':visible') : true;
                });
                const match = (combinations || []).find(c => {
                    if (!c || c.enabled === false) return false;
                    const vars = c.variants || c.attributes || [];
                    return visibleSelected.every(sv => {
                        if (!sv.name) return true;
                        const m = vars.find(v => (v.attribute === sv.attribute) && ((v.name || v.value) === sv.name));
                        return !!m;
                    });
                });
                return match || null;
            })();

            if (!combo) return;

            const comboVars = combo.variants || combo.attributes || [];
            data.forEach((attr, ai) => {
                const v = comboVars.find(x => x.attribute === attr.name);
                if (!v) return;
                const value = v.name || v.value;
                const idx = (attr.variants || []).findIndex(opt => opt.name === value);
                if (idx !== -1) selected[ai] = idx;
            });
        }

        // Enable: Auto-select lowest-value variants per attribute on initialization
        autoSelectLowestValuesPerAttribute();

        // Debug
        console.log('ECV: Auto-selected lowest-value variants per attribute (if available).');
        

        function getSelectedVariants() {
            return data.map((attr, ai) => ({
                attribute: attr.name,
                name: selected[ai] !== null ? attr.variants[selected[ai]].name : null,
                image: selected[ai] !== null ? attr.variants[selected[ai]].image : null
            }));
        }

        function findMatchingCombination() {
            const selectedVars = getSelectedVariants();
            
            // Get list of visible attributes
            const visibleAttributes = [];
            data.forEach((attr, ai) => {
                const $attrGroup = $root.find(`.ecv-attr-group[data-ai="${ai}"]`);
                if ($attrGroup.is(':visible')) {
                    visibleAttributes.push(attr.name);
                }
            });
            
            // Only check if all VISIBLE attributes are selected
            const visibleSelectedVars = selectedVars.filter((v, i) => {
                const $attrGroup = $root.find(`.ecv-attr-group[data-ai="${i}"]`);
                return $attrGroup.is(':visible');
            });
            
            // If any visible attribute is not selected, return null
            if (visibleSelectedVars.some(v => v.name === null)) return null;

            return combinations.find(combo => {
                if (!combo.enabled) return false;

                // Handle both old and new combination structures
                const comboVariants = combo.variants || combo.attributes || [];
                
                // Check if all visible selected variants match this combination
                // Ignore hidden attributes in the matching logic
                return visibleSelectedVars.every((selectedVar, i) => {
                    if (selectedVar.name === null) return true; // Skip unselected
                    
                    // Find matching variant in combination
                    const matchingVariant = comboVariants.find(v => {
                        const variantName = v.name || v.value;
                        const variantAttr = v.attribute;
                        return variantAttr === selectedVar.attribute && variantName === selectedVar.name;
                    });
                    
                    return matchingVariant !== undefined;
                });
            });
        }

        // Helper function to check if a variant is a "reset" variant (has none for all subsequent attributes)
        function checkIfResetVariant(ai, vi) {
            const clickedVariant = data[ai].variants[vi];
            if (!clickedVariant) return false;
            
            // Find combinations that include this variant
            const combosWithThisVariant = combinations.filter(combo => {
                if (!combo || combo.enabled === false) return false;
                const comboVariants = combo.variants || combo.attributes || [];
                
                return comboVariants.some(v => {
                    const variantName = v.name || v.value;
                    const variantAttr = v.attribute;
                    return variantAttr === data[ai].name && variantName === clickedVariant.name;
                });
            });
            
            if (combosWithThisVariant.length === 0) return false;
            
            // Check if all these combinations have "none" for all subsequent attributes
            for (let i = ai + 1; i < data.length; i++) {
                const subsequentAttr = data[i].name;
                
                // Check if any combination with this variant has a non-"none" value for subsequent attributes
                const hasNonNoneValue = combosWithThisVariant.some(combo => {
                    const comboVariants = combo.variants || combo.attributes || [];
                    return comboVariants.some(v => {
                        const variantName = v.name || v.value;
                        const variantAttr = v.attribute;
                        return variantAttr === subsequentAttr && 
                               variantName && 
                               variantName.toLowerCase() !== 'none';
                    });
                });
                
                if (hasNonNoneValue) {
                    return false; // This variant requires subsequent selections
                }
            }
            
            return true; // This is a reset variant
        }
        
        function updateAvailableOptions() {
            data.forEach((attr, ai) => {
                // Find valid options for this attribute based on group combinations
                let validOptions = new Set();
                
                // Check if this is a cross-group format by looking for group_combination_id
                const hasCrossGroupCombinations = combinations.some(combo => combo.group_combination_id);
                
                // NEW: Track if any value exists for this attribute in available combinations
                let hasAnyValidOption = false;
                
                if (hasCrossGroupCombinations) {
                    // Cross-group format logic
                    combinations.forEach(combo => {
                        if (!combo.enabled) return;
                        
                        const comboVariants = combo.variants || combo.attributes || [];
                        if (comboVariants.length === 0) return;
                        
                        // Check if this combination is compatible with current selections
                        let isCompatible = true;
                        
                        // For cross-group, check compatibility by validating all selected variants
                        selected.forEach((sel, i) => {
                            if (sel !== null && i !== ai) {
                                const selectedVariant = data[i].variants[sel];
                                const selectedVariantName = selectedVariant.name;
                                const selectedVariantGroup = selectedVariant.group;
                                
                                // Find if this selected variant with its group exists in current combination
                                const matchingVariant = comboVariants.find(v => 
                                    (v.name || v.value) === selectedVariantName && 
                                    v.attribute === data[i].name &&
                                    v.group_name === selectedVariantGroup
                                );
                                
                                if (!matchingVariant) {
                                    isCompatible = false;
                                }
                            }
                        });
                        
                        // If compatible, add valid options for this attribute from this combination
                        if (isCompatible) {
                            comboVariants.forEach(variant => {
                                if (variant.attribute === attr.name) {
                                    const variantValue = variant.name || variant.value;
                                    // Treat "none" as no value - don't add it to valid options
                                    if (variantValue && variantValue.toLowerCase() !== 'none') {
                                        validOptions.add(variantValue);
                                        hasAnyValidOption = true;
                                    }
                                }
                            });
                        }
                    });
                } else {
                    // Traditional group format logic (fallback)
                    combinations.forEach(combo => {
                        if (!combo.enabled) return;
                        
                        const comboVariants = combo.variants || combo.attributes || [];
                        if (comboVariants.length === 0) return;
                        
                        // Traditional compatibility check
                        let isCompatible = true;
                        selected.forEach((sel, i) => {
                            if (sel !== null && i !== ai) {
                                const selectedVariantName = data[i].variants[sel].name;
                                
                                const matchingVariant = comboVariants.find(v => 
                                    (v.name || v.value) === selectedVariantName && 
                                    v.attribute === data[i].name
                                );
                                
                                if (!matchingVariant) {
                                    isCompatible = false;
                                }
                            }
                        });
                        
                        if (isCompatible) {
                            comboVariants.forEach(variant => {
                                if (variant.attribute === attr.name) {
                                    const variantValue = variant.name || variant.value;
                                    // Treat "none" as no value - don't add it to valid options
                                    if (variantValue && variantValue.toLowerCase() !== 'none') {
                                        validOptions.add(variantValue);
                                        hasAnyValidOption = true;
                                    }
                                }
                            });
                        }
                    });
                }
                
                // If no selections made yet, show all options (except "none")
                if (selected.every(s => s === null)) {
                    attr.variants.forEach(variant => {
                        // Skip "none" values - treat as no value
                        if (variant.name && variant.name.toLowerCase() !== 'none') {
                            validOptions.add(variant.name);
                            hasAnyValidOption = true;
                        }
                    });
                }
                
                // NEW: Hide/show attribute group based on whether it has any valid options
                const $attrGroup = $root.find(`.ecv-attr-group[data-ai="${ai}"]`);
                const displayType = $attrGroup.data('display-type') || 'buttons';
                
                // Hide attribute if it has no valid options in current context
                if (!hasAnyValidOption || validOptions.size === 0) {
                    $attrGroup.hide();
                    // Clear selection if attribute is hidden
                    if (selected[ai] !== null) {
                        selected[ai] = null;
                    }
                    return; // Skip further processing for this attribute
                } else {
                    // Show attribute if it has valid options
                    $attrGroup.show();
                }

                if (displayType === 'dropdown') {
                    // Handle dropdown options
                    $attrGroup.find('.ecv-variants-select option').each(function () {
                        const vi = $(this).val();
                        if (vi !== '') {
                            const variantName = data[ai].variants[parseInt(vi)].name;
                            $(this).prop('disabled', !validOptions.has(variantName));
                        }
                    });
                } else if (displayType === 'radio') {
                    // Handle radio button options
                    $attrGroup.find('.ecv-radio-label').each(function (vi) {
                        const variantName = data[ai].variants[vi].name;
                        const $radio = $(this).find('.ecv-variant-radio');
                        const disabled = !validOptions.has(variantName);
                        $radio.prop('disabled', disabled);
                        $(this).toggleClass('disabled', disabled);
                    });
                } else {
                    // Handle button options (default)
                    $attrGroup.find('.ecv-variant-btn').each(function (vi) {
                        const variantName = data[ai].variants[vi].name;
                        const isValid = validOptions.has(variantName);
                        const isResetVariant = checkIfResetVariant(ai, vi);
                        
                        // Reset variants should be clickable even if not in current valid options
                        if (isResetVariant) {
                            $(this).removeClass('disabled').addClass('ecv-reset-option');
                        } else {
                            $(this).toggleClass('disabled', !isValid);
                            $(this).removeClass('ecv-reset-option');
                        }
                    });
                }
            });
        }

        function updateMainImage(combo, variants) {
            // Determine new image URL
            let newImg = combo && combo.main_image_url ? combo.main_image_url : null;
            if (!newImg) {
                let variantWithImage = variants.find(v => v.image);
                newImg = variantWithImage ? variantWithImage.image : null;
            }
            if (!newImg && originalProductImg) {
                newImg = originalProductImg;
            }
            if (!newImg) return;

            // Store original image for reset
            if (!$('body').data('ecv-images-stored')) {
                $('img[src]').each(function() {
                    if (!$(this).data('ecv-original-src')) {
                        $(this).data('ecv-original-src', $(this).attr('src'));
                    }
                });
                $('body').data('ecv-images-stored', true);
            }

            // Method 1: WooCommerce Gallery
            let imageUpdated = false;
            let $gallery = $('.woocommerce-product-gallery__wrapper, .woocommerce-product-gallery');
            if ($gallery.length) {
                let $mainImg = $gallery.find('.woocommerce-product-gallery__image img, .wp-post-image').first();
                if ($mainImg.length) {
                    $mainImg.attr('src', newImg).attr('srcset', newImg + ' 1x');
                    let $anchor = $mainImg.closest('a');
                    if ($anchor.length) {
                        $anchor.attr('href', newImg);
                    }
                    imageUpdated = true;
                }
            }

            // Method 2: Elementor Product Images
            if (!imageUpdated || $('.elementor').length) {
                let elementorSelectors = [
                    '.elementor-widget-woocommerce-product-images img',
                    '.elementor-widget-image img',
                    '.elementor-image img',
                    '.elementor-product-gallery img'
                ];
                
                elementorSelectors.forEach(selector => {
                    $(selector).each(function() {
                        if ($(this).closest('#ecv-frontend-variations').length === 0) {
                            $(this).attr('src', newImg).attr('srcset', newImg + ' 1x');
                            imageUpdated = true;
                        }
                    });
                });
            }

            // Method 3: General product images
            if (!imageUpdated) {
                let generalSelectors = [
                    '.wp-post-image',
                    '.product-image img',
                    '.product-main-image img',
                    '.woo-product-image img',
                    '.product .attachment-shop_single',
                    '.single-product-summary img'
                ];
                
                generalSelectors.forEach(selector => {
                    $(selector).each(function() {
                        if ($(this).closest('#ecv-frontend-variations').length === 0) {
                            $(this).attr('src', newImg).attr('srcset', newImg + ' 1x');
                            imageUpdated = true;
                        }
                    });
                });
            }

            // Method 4: Force update all product images if nothing worked
            if (!imageUpdated) {
                $('img').each(function() {
                    let src = $(this).attr('src');
                    if (src && src.includes('wp-content/uploads') && 
                        $(this).closest('#ecv-frontend-variations').length === 0 &&
                        $(this).width() > 100 && $(this).height() > 100) {
                        $(this).attr('src', newImg).attr('srcset', newImg + ' 1x');
                    }
                });
            }

            // Update any lightbox/zoom functionality
            setTimeout(() => {
                if (typeof $.fn.zoom !== 'undefined') {
                    $('img[src="' + newImg + '"]').closest('a').trigger('zoom.destroy');
                }
                $(document).trigger('woocommerce_gallery_image_changed', [newImg]);
            }, 100);
        }

        function updateUI() {
            // Update UI based on per-attribute display type
            $root.find('.ecv-attr-group').each(function (ai) {
                const $group = $(this);
                const displayType = $group.data('display-type') || 'buttons';

                if (displayType === 'dropdown') {
                    // Update dropdown selection
                    const $select = $group.find('.ecv-variants-select');
                    $select.val(selected[ai] !== null ? selected[ai] : '');
                } else if (displayType === 'radio') {
                    // Update radio button selection
                    $group.find('.ecv-variant-radio').each(function (vi) {
                        $(this).prop('checked', selected[ai] === vi);
                    });

                    // Update label states using data attributes
                    $group.find('.ecv-radio-label').removeClass('selected');
                    if (selected[ai] !== null) {
                        $group.find('.ecv-radio-label[data-ai="' + ai + '"][data-vi="' + selected[ai] + '"]').addClass('selected');
                    }
                } else {
                    // Update button selection (default)
                    $group.find('.ecv-variant-btn')
                        .removeClass('active')
                        .toggleClass('disabled', false);
                    if (selected[ai] !== null) {
                        // Use data attributes instead of sequential index to handle grouped variants
                        const selector = '.ecv-variant-btn[data-ai="' + ai + '"][data-vi="' + selected[ai] + '"]';
                        const $activeBtn = $group.find(selector);
                        console.log('ECV Debug: Looking for button with selector:', selector, 'Found:', $activeBtn.length, 'buttons');
                        if ($activeBtn.length > 0) {
                            $activeBtn.addClass('active');
                            console.log('ECV Debug: Activated button for attribute', ai, 'variant', selected[ai], '- Text:', $activeBtn.text().trim());
                        } else {
                            console.log('ECV Debug: WARNING - No button found for attribute', ai, 'variant', selected[ai]);
                        }
                    }
                }
            });

            updateAvailableOptions();

            // Find matching combination
            const combo = findMatchingCombination();
            const variants = getSelectedVariants();

            // Update main image
            updateMainImage(combo, variants);

            if (combo) {
                // Calculate price
                let price = parseFloat(combo.price) || basePrice;
                let salePrice = combo.sale_price ? parseFloat(combo.sale_price) : null;

                // Generate price HTML
                let priceHtml = '';
                // Get currency symbol from localized config or fallback to Rupee
                const currencySymbol = (typeof ecvConfig !== 'undefined' && ecvConfig.currencySymbol) ? ecvConfig.currencySymbol : '₹';
                
                if (salePrice && !isNaN(salePrice) && salePrice < price) {
                    priceHtml = '<span class="price"><del><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">' + currencySymbol + '</span>' + price.toFixed(2) + '</span></del> <ins><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">' + currencySymbol + '</span>' + salePrice.toFixed(2) + '</span></ins></span>';
                } else {
                    priceHtml = '<span class="price"><span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">' + currencySymbol + '</span>' + price.toFixed(2) + '</span></span>';
                }

                // Update plugin's dedicated price display
                $('#ecv-price-display').html('<span class="ecv-price-label">Price: </span>' + priceHtml);
                
                // Update existing WooCommerce price elements
                $(
                    '.summary .price, ' +
                    '.product .price, ' +
                    '.entry-summary .price, ' +
                    '.elementor-widget-container .price, ' +
                    '.woocommerce-product-details__short-description + .price, ' +
                    'p.price'
                ).html(priceHtml);
                
                // If no price elements exist, create one
                if ($('.price').length === 0) {
                    // Find a suitable container
                    let $container = $('.entry-summary, .summary, .elementor-widget-container, .product-summary').first();
                    if (!$container.length) {
                        $container = $root.parent();
                    }
                    
                    // Add price element
                    $container.find('.single_add_to_cart_button, .cart, .product_meta').first().before(
                        '<p class="price ecv-created-price" style="font-size: 1.25em; margin: 10px 0; font-weight: bold;">' + priceHtml + '</p>'
                    );
                }

                // Update stock status
                if (combo.stock !== undefined && combo.stock !== '') {
                    if (parseInt(combo.stock) > 0) {
                        $('.stock').removeClass('out-of-stock').addClass('in-stock')
                            .text(`${combo.stock} in stock`);
                    } else {
                        $('.stock').removeClass('in-stock').addClass('out-of-stock')
                            .text('Out of stock');
                    }
                }

                // Update SKU
                if (combo.sku) {
                    $('.sku').text(combo.sku);
                }

                // Update add to cart button
                $('.single_add_to_cart_button').prop('disabled', parseInt(combo.stock) <= 0);

                // Update variation ID for the form
                $('input[name="variation_id"]').val(combo.id);

                // Get the current main image URL as shown to the user
                let currentMainImg = '';
                let $gallery = $('.woocommerce-product-gallery__wrapper');
                if ($gallery.length) {
                    let $mainImg = $gallery.find('.woocommerce-product-gallery__image').first().find('img');
                    currentMainImg = $mainImg.attr('src') || '';
                } else {
                    currentMainImg = $('.wp-post-image, .product .wp-post-image').attr('src') || '';
                }
                const cartData = {
                    id: combo.id,
                    sku: combo.sku,
                    price: combo.price,
                    sale_price: combo.sale_price,
                    stock: combo.stock,
                    main_image_url: currentMainImg,
                    attributes: variants.filter(v => v.name).map((v, i) => ({
                        attribute: data[i].name,
                        value: v.name,
                        image: v.image
                    }))
                };
                
                const jsonString = JSON.stringify(cartData);
                $('#ecv_selected_combination').val(jsonString);
                
                // Also ensure the hidden input is in the cart form
                const $cartForm = $('form.cart, .cart form, form[action*="add-to-cart"]').first();
                if ($cartForm.length && !$cartForm.find('#ecv_selected_combination').length) {
                    $('#ecv_selected_combination').appendTo($cartForm);
                }
                
                console.log('ECV: Generated cart data:', cartData);
            } else {
                // No valid combination selected
                $('#ecv-price-display').html('<span class="na">Please select all options</span>');
                $('.price').html('<span class="na">Please select all options</span>');
                $('.stock').removeClass('in-stock').addClass('out-of-stock').text('Select options');
                $('.single_add_to_cart_button').prop('disabled', true);
                $('input[name="variation_id"]').val('');
                $('#ecv_selected_combination').val('');

                // Reset images to original
                if (originalProductImg) {
                    // Reset all images that we might have changed
                    $('img').each(function() {
                        let originalSrc = $(this).data('ecv-original-src');
                        if (originalSrc && $(this).closest('#ecv-frontend-variations').length === 0) {
                            $(this).attr('src', originalSrc);
                            // Also reset srcset if it exists
                            let originalSrcset = $(this).data('ecv-original-srcset');
                            if (originalSrcset) {
                                $(this).attr('srcset', originalSrcset);
                            }
                        }
                    });
                }
            }

            // Update details panel - only show visible attributes
            let html = '';
            getSelectedVariants().forEach((v, i) => {
                const $attrGroup = $root.find(`.ecv-attr-group[data-ai="${i}"]`);
                // Only show details for visible attributes
                if ($attrGroup.is(':visible')) {
                    html += `<div class="ecv-detail-row">
                    <strong>${data[i].name}:</strong> 
                    ${v.name || 'Select an option'}
                </div>`;
                }
            });
            $('#ecv-variation-details').html(html);
        }

        // Initial render
        updateUI();

        // Handle selection for buttons
        $root.on('click', '.ecv-variant-btn', function () {
            let ai = $(this).data('ai'), vi = $(this).data('vi');
            
            // Allow clicking even if disabled, if this variant has "none" for all subsequent attributes
            // This allows users to "reset" to simpler combinations
            const clickedVariant = data[ai].variants[vi];
            const isResetVariant = checkIfResetVariant(ai, vi);
            
            if (isResetVariant) {
                // This is a "reset" variant (has none for all subsequent attributes)
                // Clear all subsequent attribute selections
                for (let i = ai + 1; i < selected.length; i++) {
                    selected[i] = null;
                }
                selected[ai] = vi;
            } else {
                // Normal behavior for non-reset variants
                if ($(this).hasClass('disabled')) {
                    return; // Don't allow clicking disabled buttons unless it's a reset variant
                }
                
                // Toggle selection
                if (selected[ai] === vi) {
                    selected[ai] = null;
                } else {
                    selected[ai] = vi;
                }
            }
            
            updateUI();
        });

        // Function to update tooltip for dropdown
        function updateTooltip(ai, vi) {
            const $tooltipContainer = $root.find(`.ecv-tooltip-container[data-ai="${ai}"]`);
            if (!$tooltipContainer.length) return;
            
            // Only show tooltip if a valid selection is made
            if (vi === null || vi === '' || vi === undefined) {
                $tooltipContainer.hide().text('');
                return;
            }
            
            // Get attribute name and variant name
            const attr = data[ai];
            if (!attr) return;
            
            const variant = attr.variants[parseInt(vi)];
            if (!variant) return;
            
            const attrName = attr.name;
            const variantName = variant.name;
            const variantGroup = variant.group || '';
            
            // Look up tooltip: tooltips[attrName][groupName][valueName]
            let tooltipText = '';
            if (tooltips[attrName] && tooltips[attrName][variantGroup] && tooltips[attrName][variantGroup][variantName]) {
                tooltipText = tooltips[attrName][variantGroup][variantName];
            }
            
            if (tooltipText) {
                $tooltipContainer.text(tooltipText).show();
            } else {
                $tooltipContainer.hide().text('');
            }
        }
        
        // Handle dropdown selection
        $root.on('change', '.ecv-variants-select', function () {
            let ai = $(this).data('ai');
            let vi = $(this).val();
            selected[ai] = vi === '' ? null : parseInt(vi);
            updateTooltip(ai, vi);
            updateUI();
        });

        // Handle radio button selection
        $root.on('change', '.ecv-variant-radio', function () {
            let ai = $(this).data('ai');
            let vi = parseInt($(this).val());
            selected[ai] = vi;
            updateUI();
        });


    }

    // Expose initialization function globally for Elementor compatibility
    window.initECVPlugin = initECVPlugin;
    
    // Initialize the plugin
    initECVPlugin();
    
    // Re-initialize on Elementor frontend updates
    if (typeof elementorFrontend !== 'undefined') {
        elementorFrontend.hooks.addAction('frontend/element_ready/widget', function($scope) {
            if ($scope.find('#ecv-frontend-variations').length) {
                setTimeout(function() {
                    initECVPlugin();
                }, 100);
            }
        });
    }
    
    // Re-initialize on AJAX content loads (for dynamic content)
    $(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.url && settings.url.indexOf('elementor') !== -1) {
            setTimeout(function() {
                if ($('#ecv-frontend-variations').length && !$('#ecv-frontend-variations').data('ecv-initialized')) {
                    initECVPlugin();
                }
            }, 200);
        }
    });
    
    // GLOBAL EVENT HANDLERS FOR ADD TO CART
    // These are outside the initECVPlugin function to ensure they always work
    
    // Handle form submission for add to cart
    $(document).on('submit', 'form.cart, .cart form, form[action*="add-to-cart"]', function(e) {
        const comboData = $('#ecv_selected_combination').val();
        if (comboData && comboData !== '""' && comboData !== '') {
            // Remove any existing hidden input first
            $(this).find('input[name="ecv_selected_combination"]').remove();
            
            // Add the hidden input with variant data
            $(this).append('<input type="hidden" name="ecv_selected_combination" value="' + encodeURIComponent(comboData) + '" />');
            
            console.log('ECV: Form submit - Adding variant data:', comboData);
        } else {
            console.log('ECV: Form submit - No variant data found');
        }
    });
    
    // Handle add to cart button clicks (including AJAX)
    $(document).on('click', '.single_add_to_cart_button, .add_to_cart_button, button[name="add-to-cart"]', function(e) {
        const comboData = $('#ecv_selected_combination').val();
        if (comboData && comboData !== '""' && comboData !== '') {
            const $form = $(this).closest('form');
            if ($form.length) {
                // Remove any existing hidden input first
                $form.find('input[name="ecv_selected_combination"]').remove();
                
                // Add the hidden input with variant data
                $form.append('<input type="hidden" name="ecv_selected_combination" value="' + encodeURIComponent(comboData) + '" />');
            }
            
            // Also add to button data attributes for AJAX requests
            $(this).attr('data-ecv_selected_combination', encodeURIComponent(comboData));
            
            console.log('ECV: Button click - Adding variant data:', comboData);
        } else {
            console.log('ECV: Button click - No variant data found');
        }
    });
    
    // Intercept AJAX requests to add variant data
    $(document).ajaxSend(function(event, xhr, settings) {
        if (settings.data && (
            settings.data.indexOf('action=woocommerce_add_to_cart') !== -1 ||
            settings.data.indexOf('add-to-cart=') !== -1
        )) {
            const comboData = $('#ecv_selected_combination').val();
            if (comboData && comboData !== '""' && comboData !== '') {
                if (settings.data.indexOf('ecv_selected_combination') === -1) {
                    settings.data += '&ecv_selected_combination=' + encodeURIComponent(comboData);
                }
                console.log('ECV: AJAX intercept - Adding variant data:', comboData);
            }
        }
    });
    
    // Handle WooCommerce AJAX events
    $(document.body).on('wc_fragment_refresh', function() {
        console.log('ECV: WooCommerce fragments refreshed');
    });
    
    // Monitor cart updates
    $(document.body).on('added_to_cart', function(event, fragments, cart_hash, $button) {
        console.log('ECV: Product added to cart');
    });
    
});
