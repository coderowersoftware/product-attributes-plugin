<?php
/**
 * Plugin Configuration
 * 
 * This file contains configuration options for the Exp Custom Variations plugin.
 * You can modify these settings to customize the plugin behavior.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Show Custom Variations Metabox in Product Edit Page
 * 
 * Set to TRUE to show the Custom Variations metabox in the product edit page.
 * Set to FALSE to hide it (recommended if you're using CSV import exclusively).
 * 
 * Default: FALSE (hidden)
 * 
 * Note: The metabox was disabled because:
 * - Products are primarily managed via CSV import
 * - Manual editing in admin was not working properly
 * - Saving data from admin panel was disabled
 */
define('ECV_SHOW_VARIATIONS_METABOX', false);

/**
 * Show Product Extra Fields Metabox in Product Edit Page
 * 
 * Set to TRUE to show the Product Extra Fields metabox.
 * Set to FALSE to hide it.
 * 
 * Default: TRUE (visible)
 */
define('ECV_SHOW_EXTRA_FIELDS_METABOX', true);

/**
 * Enable Debug Mode
 * 
 * Set to TRUE to enable detailed debug logging.
 * Set to FALSE to disable debug logging.
 * 
 * Default: FALSE
 * 
 * Note: This is separate from WordPress WP_DEBUG.
 * When enabled, additional plugin-specific logs will be written.
 */
define('ECV_DEBUG_MODE', false);

/**
 * Currency Symbol
 * 
 * The currency symbol used in price display on the frontend.
 * 
 * Default: '₹' (Indian Rupee)
 * 
 * Examples:
 * - '$' for US Dollar
 * - '€' for Euro
 * - '£' for British Pound
 * - '₹' for Indian Rupee
 */
// Already defined in main plugin file: ECV_CURRENCY_SYMBOL

/**
 * Import/Export Settings
 */

/**
 * Maximum products to process in one import
 * 
 * Set to 0 for unlimited (not recommended for large imports)
 * 
 * Default: 1000
 */
define('ECV_MAX_IMPORT_PRODUCTS', 1000);

/**
 * Import timeout (in seconds)
 * 
 * Maximum time allowed for import processing.
 * Set to 0 to use PHP's default max_execution_time.
 * 
 * Default: 300 (5 minutes)
 */
define('ECV_IMPORT_TIMEOUT', 300);

/**
 * Extra Fields Settings
 */

/**
 * Maximum extra fields per product
 * 
 * Limit the number of extra fields that can be added to a single product.
 * Set to 0 for unlimited.
 * 
 * Default: 50
 */
define('ECV_MAX_EXTRA_FIELDS', 50);

/**
 * Auto-generate shortcodes
 * 
 * Automatically generate and display shortcodes in the admin metabox.
 * 
 * Default: TRUE
 */
define('ECV_AUTO_GENERATE_SHORTCODES', true);

/**
 * Show import timestamp
 * 
 * Show "CSV IMPORTED" badge and timestamp in admin for imported products.
 * 
 * Default: TRUE
 */
define('ECV_SHOW_IMPORT_TIMESTAMP', true);

// End of configuration
