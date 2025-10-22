/**
 * Cart Image Fix - Ensures variant images display properly in cart and checkout
 */
jQuery(document).ready(function($) {
    'use strict';
    
    function fixCartImages() {
        // Find all cart items with variant data
        $('.woocommerce-cart-form .cart_item, .woocommerce-checkout .cart_item, .shop_table .cart_item').each(function() {
            const $item = $(this);
            const $thumbnail = $item.find('.product-thumbnail');
            const $img = $thumbnail.find('img');
            
            // Check if there's a variant image URL in item data
            const $variantData = $item.find('dl dd:contains("http")');
            if ($variantData.length > 0) {
                const imageUrl = $variantData.text().trim();
                if (imageUrl && imageUrl.startsWith('http')) {
                    // Replace the thumbnail with variant image
                    const newImg = $('<img>').attr({
                        'src': imageUrl,
                        'alt': 'Variant Image',
                        'class': 'attachment-woocommerce_thumbnail size-woocommerce_thumbnail',
                        'width': '64',
                        'height': '64'
                    }).css({
                        'width': '64px',
                        'height': '64px',
                        'object-fit': 'cover',
                        'border-radius': '4px',
                        'border': '1px solid #ddd',
                        'display': 'block',
                        'margin': '0 auto'
                    });
                    
                    $thumbnail.html(newImg);
                }
            }
            
            // Also check for broken/missing images and fix them
            $img.each(function() {
                const img = this;
                if (!img.src || img.src.includes('placeholder') || img.naturalWidth === 0) {
                    // Try to find variant image in the item
                    const itemHtml = $item.html();
                    const urlMatch = itemHtml.match(/https?:\/\/[^\s"<>]+?\.(jpg|jpeg|png|gif|webp)/i);
                    if (urlMatch) {
                        $(img).attr('src', urlMatch[0]).css({
                            'width': '64px',
                            'height': '64px',
                            'object-fit': 'cover',
                            'border-radius': '4px'
                        });
                    }
                }
            });
        });
    }
    
    // Fix images on page load
    fixCartImages();
    
    // Fix images after AJAX updates
    $(document.body).on('updated_cart_totals updated_checkout', function() {
        setTimeout(fixCartImages, 100);
    });
    
    // Fix images when cart is updated
    $(document.body).on('wc_update_cart', function() {
        setTimeout(fixCartImages, 500);
    });
    
    // Debug logging for cart images
    if (window.console && console.log) {
        console.log('ECV Cart Image Fix: Initialized');
        
        // Count cart items with images
        const cartItemsWithImages = $('.product-thumbnail img[src*="http"]').length;
        const totalCartItems = $('.product-thumbnail').length;
        console.log('ECV Cart Image Fix: Found ' + cartItemsWithImages + '/' + totalCartItems + ' cart items with images');
    }
});