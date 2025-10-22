jQuery(document).ready(function($){
  const $root = $('#ecv-extra-attributes');
  if (!$root.length) return;

  let data = $root.data('extra') || [];
  let selected = {};
  let basePrice = 0;
  const currencySymbol = typeof ecvConfig !== 'undefined' && ecvConfig.currencySymbol ? ecvConfig.currencySymbol : '₹';

  function isAttrVisible(ai){
    const attr = data[ai];
    if (!attr || !attr.visible_when || !attr.visible_when.attribute || !attr.visible_when.value) return true;
    // find parent
    const parentIndex = data.findIndex(a => a.name === attr.visible_when.attribute);
    if (parentIndex === -1) return true;
    const sel = selected[parentIndex];
    if (!sel) return false;
    return sel.name === attr.visible_when.value;
  }

  function calculateExtraPrice() {
    let totalExtra = 0;
    Object.keys(selected).forEach(ai => {
      const variant = data[ai].variants[selected[ai].vi];
      const price = parseFloat(variant.price) || 0;
      totalExtra += price;
    });
    return totalExtra;
  }

  function updatePriceDisplay() {
    const extraPrice = calculateExtraPrice();
    
    // Update extra price display
    if (extraPrice > 0) {
      $('#ecv-extra-price-display').show();
      $('#ecv-extra-price-value').text(currencySymbol + extraPrice.toFixed(2));
    } else {
      $('#ecv-extra-price-display').hide();
    }
    
    // Update main product price (WooCommerce price elements)
    if (basePrice > 0) {
      const newPrice = basePrice + extraPrice;
      const formattedPrice = currencySymbol + newPrice.toFixed(2);
      
      // Update all price displays with proper WooCommerce structure
      const priceHtml = '<span class="woocommerce-Price-amount amount"><bdi>' + formattedPrice + '</bdi></span>';
      
      // Find and update price containers
      $('.summary .price, .product .price, .entry-summary .price, p.price').each(function() {
        // Check if there's a sale price structure
        if ($(this).find('del').length > 0) {
          // Has sale price, only update the ins element
          $(this).find('ins').html(priceHtml);
        } else {
          // Regular price, update the whole content
          $(this).html(priceHtml);
        }
      });
      
      console.log('ECV Extra: Updated price to', newPrice, '(base:', basePrice, '+ extra:', extraPrice, ')');
    }
  }

  function captureBasePrice() {
    // Capture the current product price from display
    let $priceElement = $('.price ins .woocommerce-Price-amount, .price .woocommerce-Price-amount, .summary .price .amount, p.price .amount').last();
    
    if ($priceElement.length) {
      let priceText = $priceElement.text().replace(/[^0-9.]/g, '');
      let capturedPrice = parseFloat(priceText) || 0;
      
      // Only update if it's different and valid
      if (capturedPrice > 0) {
        basePrice = capturedPrice;
        console.log('ECV Extra: Captured base price:', basePrice);
      }
    }
    
    // Fallback: try to get from hidden input if main variations are present
    const selectedCombo = $('#ecv_selected_combination').val();
    if (selectedCombo) {
      try {
        const combo = JSON.parse(selectedCombo);
        if (combo.sale_price && parseFloat(combo.sale_price) > 0) {
          basePrice = parseFloat(combo.sale_price);
        } else if (combo.price && parseFloat(combo.price) > 0) {
          basePrice = parseFloat(combo.price);
        }
      } catch(e) {}
    }
  }

  function renderUI(){
    // toggle groups and selection state
    $root.find('.ecv-extra-attr').each(function(){
      const ai = parseInt($(this).data('ai'));
      const vis = isAttrVisible(ai);
      if (!vis) { delete selected[ai]; }
      $(this).toggle(vis);
      // reflect selection
      const attr = data[ai];
      const disp = attr.display_type || 'dropdown';
      if (disp === 'buttons') {
        $(this).find('.ecv-extra-btn').removeClass('active');
        if (selected[ai]) {
          $(this).find('.ecv-extra-btn[data-vi="'+selected[ai].vi+'"]').addClass('active');
        }
      } else if (disp === 'radio') {
        $(this).find('input.ecv-extra-radio-input').prop('checked', false);
        if (selected[ai]) {
          $(this).find('input.ecv-extra-radio-input[value="'+selected[ai].vi+'"]').prop('checked', true);
        }
      } else {
        // dropdown
        const $sel = $(this).find('select.ecv-extra-select');
        $sel.val(selected[ai] ? String(selected[ai].vi) : '');
      }
    });

    // write hidden payload for cart including prices
    const payload = Object.keys(selected).map(ai => {
      const variant = data[ai].variants[selected[ai].vi];
      return {
        attribute: data[ai].name,
        value: selected[ai].name,
        price: variant.price || 0
      };
    });
    $('#ecv_extra_attributes').val(JSON.stringify(payload));
    
    // Update price display
    updatePriceDisplay();
  }

  // Button clicks
  $root.on('click', '.ecv-extra-btn', function(){
    const ai = parseInt($(this).data('ai'));
    const vi = parseInt($(this).data('vi'));
    const attr = data[ai];
    const variant = attr.variants[vi];
    if (selected[ai] && selected[ai].vi === vi) {
      delete selected[ai];
    } else {
      selected[ai] = { vi, name: variant.name };
    }
    renderUI();
  });

  // Dropdown
  $root.on('change', 'select.ecv-extra-select', function(){
    const ai = parseInt($(this).data('ai'));
    const viStr = $(this).val();
    if (!viStr) { delete selected[ai]; return renderUI(); }
    const vi = parseInt(viStr);
    selected[ai] = { vi, name: data[ai].variants[vi].name };
    renderUI();
  });

  // Radio
  $root.on('change', 'input.ecv-extra-radio-input', function(){
    const ai = parseInt($(this).data('ai'));
    const vi = parseInt($(this).val());
    selected[ai] = { vi, name: data[ai].variants[vi].name };
    renderUI();
  });

  // Ensure the hidden input ends up in the cart form
  setTimeout(function(){
    const $cartForm = $('form.cart, .cart form, form[action*="add-to-cart"]').first();
    const $hidden = $('#ecv_extra_attributes');
    if ($cartForm.length && $hidden.length && !$cartForm.find('#ecv_extra_attributes').length) {
      $hidden.appendTo($cartForm);
    }
  }, 400);

  // Also attach to submitted forms/buttons (AJAX)
  $(document).on('submit', 'form.cart, .cart form, form[action*="add-to-cart"]', function(){
    const json = $('#ecv_extra_attributes').val();
    $(this).find('input[name="ecv_extra_attributes"]').remove();
    $(this).append('<input type="hidden" name="ecv_extra_attributes" value="'+encodeURIComponent(json)+'"/>');
  });

  $(document).on('click', '.single_add_to_cart_button, .add_to_cart_button, button[name="add-to-cart"]', function(){
    const $form = $(this).closest('form');
    if ($form.length) {
      const json = $('#ecv_extra_attributes').val();
      $form.find('input[name="ecv_extra_attributes"]').remove();
      $form.append('<input type="hidden" name="ecv_extra_attributes" value="'+encodeURIComponent(json)+'"/>');
    }
  });

  // Capture base price on load
  setTimeout(captureBasePrice, 500);
  
  // Re-capture base price when main variations change
  $(document).on('ecv_variation_changed', function(e, data) {
    setTimeout(function() {
      captureBasePrice();
      updatePriceDisplay();
    }, 200);
  });
  
  // Also listen for WooCommerce variation events
  $('form.variations_form').on('found_variation', function(event, variation) {
    if (variation.display_price) {
      basePrice = parseFloat(variation.display_price);
      setTimeout(updatePriceDisplay, 100);
    }
  });
  
  // Watch for price changes from main ECV variations
  const priceObserver = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.target.classList && (mutation.target.classList.contains('price') || mutation.target.classList.contains('amount'))) {
        setTimeout(function() {
          captureBasePrice();
          updatePriceDisplay();
        }, 100);
      }
    });
  });
  
  // Observe price elements for changes
  $('.price, p.price').each(function() {
    priceObserver.observe(this, { childList: true, subtree: true, characterData: true });
  });
  
  // Re-capture when clicking main variation buttons
  $(document).on('click', '.ecv-variant-btn, .ecv-variant-radio', function() {
    setTimeout(function() {
      captureBasePrice();
      updatePriceDisplay();
    }, 300);
  });
  
  // Re-capture when changing main variation dropdown
  $(document).on('change', '.ecv-variants-select', function() {
    setTimeout(function() {
      captureBasePrice();
      updatePriceDisplay();
    }, 300);
  });
  
  // initial render
  renderUI();
});
