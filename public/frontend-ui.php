<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Enqueue frontend assets with Elementor Pro compatibility
add_action('wp_enqueue_scripts', function() {
	$css_file = ECV_PATH . 'public/frontend.css';
	$js_file  = ECV_PATH . 'public/frontend.js';
	$css_ver  = file_exists($css_file) ? filemtime($css_file) : null;
	$js_ver   = file_exists($js_file) ? filemtime($js_file) : null;

	// Enqueue with higher priority to load after Elementor
	$js_dependencies = ['jquery'];
	$css_dependencies = [];
	
	// Add Elementor dependencies if active
	if (defined('ELEMENTOR_VERSION')) {
		$js_dependencies[] = 'elementor-frontend';
		$css_dependencies[] = 'elementor-frontend';
	}
	
	// Add WooCommerce dependencies if active
	if (class_exists('WooCommerce')) {
		$js_dependencies[] = 'woocommerce';
		$js_dependencies[] = 'wc-add-to-cart';
	}

	wp_enqueue_script('ecv-frontend-js', ECV_URL . 'public/frontend.js', $js_dependencies, $js_ver ?: '1.0', true);
	wp_enqueue_style('ecv-frontend-css', ECV_URL . 'public/frontend.css', $css_dependencies, $css_ver ?: null);
	
	// Localize script with currency symbol
	wp_localize_script('ecv-frontend-js', 'ecvConfig', array(
		'currencySymbol' => defined('ECV_CURRENCY_SYMBOL') ? ECV_CURRENCY_SYMBOL : '₹'
	));
	
	// Add inline script to ensure Elementor compatibility
	wp_add_inline_script('ecv-frontend-js', '
		// ECV Elementor Pro Compatibility Flag
		window.ECV_ELEMENTOR_LOADED = typeof elementorFrontend !== "undefined";
		console.log("ECV Plugin: Elementor compat mode", window.ECV_ELEMENTOR_LOADED);
	', 'before');
}, 20); // Higher priority to load after Elementor

// Shortcode for custom selectors
add_shortcode('ecv_variations', function($atts) {
	global $post;
	if (!$post) return '';
	
	$variation_mode = get_post_meta($post->ID, '_ecv_variation_mode', true) ?: 'traditional';
	
	if ($variation_mode === 'grouped') {
		// Use grouped format - convert to traditional format
		$grouped_data = ecv_get_grouped_data($post->ID);
		$converted = ecv_convert_groups_to_traditional_format($grouped_data);
		$data = $converted['data'];
		$combinations = $converted['combinations'];
	} else {
		// Use traditional variations
		$data = ecv_get_variations_data($post->ID);
		$combinations = ecv_get_combinations_data($post->ID);
		// Resolve main_image_id to URL for each combination
		foreach ($combinations as &$combo) {
			if (!empty($combo['main_image_id'])) {
				$url = wp_get_attachment_image_url($combo['main_image_id'], 'large');
				if ($url) $combo['main_image_url'] = $url;
			}
		}
		unset($combo);
	}
	
	ob_start();
	include ECV_PATH . 'templates/selectors.php';
	return ob_get_clean();
});

// Auto-inject on product page (if using WooCommerce template)
add_action('woocommerce_single_product_summary', function() {
	echo do_shortcode('[ecv_variations]');
}, 21);

// Elementor Pro compatibility - ensure plugin works in Elementor widgets
if (defined('ELEMENTOR_VERSION')) {
	// Hook into Elementor frontend to ensure our scripts load correctly
	add_action('elementor/frontend/after_enqueue_scripts', function() {
		// Re-enqueue our scripts to ensure they're loaded after Elementor
		if (!wp_script_is('ecv-frontend-js', 'done')) {
			$js_file = ECV_PATH . 'public/frontend.js';
			$js_ver = file_exists($js_file) ? filemtime($js_file) : null;
			wp_enqueue_script('ecv-frontend-js-elementor', ECV_URL . 'public/frontend.js', ['jquery', 'elementor-frontend'], $js_ver ?: '1.0', true);
		}
	});
	
	// Add Elementor widget support for our shortcode
	add_action('elementor/widgets/widgets_registered', function() {
		// This ensures our shortcode works properly in Elementor text widgets
		add_filter('widget_text', 'do_shortcode');
	});
	
	// Hook into Elementor's frontend element ready event
	add_action('wp_footer', function() {
		?>
		<script>
		(function($) {
			if (typeof elementorFrontend !== 'undefined') {
				elementorFrontend.hooks.addAction('frontend/element_ready/global', function($scope) {
					// Re-initialize our plugin when Elementor widgets are ready
					if ($scope.find('#ecv-frontend-variations').length) {
						setTimeout(function() {
							if (typeof window.initECVPlugin === 'function') {
								window.initECVPlugin();
							}
						}, 300);
					}
				});
			}
		})(jQuery);
		</script>
		<?php
	});
}
