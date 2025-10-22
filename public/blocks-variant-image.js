(function(wp){
    if (!wp || !wp.wc || !wp.wc.blocksRegistry) return;
    const { registerCartItemMeta } = wp.wc.blocksRegistry;
    registerCartItemMeta({
        name: 'Selected Variant Image',
        render: ({ value }) => {
            if (value && typeof value === 'string' && value.startsWith('http')) {
                return wp.element.createElement('img', {
                    src: value,
                    style: { maxWidth: '60px', verticalAlign: 'middle' },
                    alt: 'Variant'
                });
            }
            return value;
        }
    });

    (function waitForWPData() {
        if (typeof window.wp === 'undefined' || typeof window.wp.data === 'undefined' || typeof window.wp.hooks === 'undefined') {
            setTimeout(waitForWPData, 50);
            return;
        }
        console.log('WooCommerce Blocks variant image JS loaded');
        wp.hooks.addFilter(
            'woocommerce.blocks.cartItem.image',
            'exp-custom-variations/replace-image',
            ( OriginalComponent ) => ( props ) => {
                const variantImage = props?.cartItem?.extensions?.selected_variant_image;
                if (variantImage && typeof variantImage === 'string' && variantImage.startsWith('http')) {
                    return wp.element.createElement('img', {
                        src: variantImage,
                        style: { maxWidth: '60px', verticalAlign: 'middle' },
                        alt: 'Variant'
                    });
                }
                return wp.element.createElement(OriginalComponent, props);
            }
        );
    })();
})(window.wp || window.wpBlocks || {}); 