# Image Import Fix Documentation

## Problem Identified

The WordPress plugin was not importing the **Main Product Image** and **Gallery Images** from CSV files during product import. 

### Root Cause

**UTF-8 BOM (Byte Order Mark) Issue:**
- Your CSV file starts with a UTF-8 BOM character (`﻿`) before the first column header "ID"
- This caused the header mapping to fail because the code was looking for "main product image" but the actual header key was "﻿main product image" (with BOM)
- The BOM character is invisible in most text editors but causes string matching to fail

## Fix Applied

### 1. BOM Removal (Line 181-188 in import-handler.php)

**Before:**
```php
function ecv_map_csv_headers($headers) {
    $map = array();
    foreach ($headers as $index => $header) {
        $clean_header = strtolower(trim($header));
        $map[$clean_header] = $index;
    }
    return $map;
}
```

**After:**
```php
function ecv_map_csv_headers($headers) {
    $map = array();
    foreach ($headers as $index => $header) {
        // Remove UTF-8 BOM if present (especially from first column)
        $header = str_replace("\xEF\xBB\xBF", '', $header);
        $clean_header = strtolower(trim($header));
        $map[$clean_header] = $index;
    }
    return $map;
}
```

### 2. Enhanced Logging

Added comprehensive logging to help debug image import issues:
- Logs available header keys when image columns are not found
- Logs each step of the image import process
- Verifies that images were successfully saved after product creation/update
- Shows main image ID and gallery image IDs after save

## How Your CSV Format Works

Your CSV format uses these columns for images:
- **Main Product Image** (column 13): Single URL for the main product image
  - Example: `http://temstar.local/wp-content/uploads/2024/10/71w16GmYwEL._SY879_.jpg`

- **Gallery Images** (column 14): Multiple URLs separated by pipe `|`
  - Example: `http://temstar.local/wp-content/uploads/2024/10/41yzqce5oDL.jpg|http://temstar.local/wp-content/uploads/2024/10/71w16GmYwEL._SY879_.jpg`

## Testing Instructions

### 1. Test the Import

1. Go to WordPress Admin → Products → Import/Export (or your plugin's import page)
2. Upload your CSV file: `unified-variations-template (7).csv`
3. Check the "Create New" and/or "Update Existing" options
4. Click Import

### 2. Check WordPress Debug Logs

Enable WordPress debugging to see detailed logs:

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Then check the log file at: `wp-content/debug.log`

Look for these log entries:
```
ECV Import: Processing main product image: [URL]
ECV Import: Main product image set successfully, ID: [number]
ECV Import: Processing gallery images: [URLs]
ECV Import: Gallery image imported successfully, ID: [number]
ECV Import: Setting gallery image IDs: [numbers]
ECV Create Product: Verified main image ID: [number]
ECV Create Product: Verified gallery image IDs: [numbers]
```

### 3. Verify in WordPress Admin

After import:
1. Go to Products → All Products
2. Find the imported product (e.g., "Premium T-Shirt with Cross Groups")
3. Edit the product
4. Scroll down to "Product image" section - you should see the main image
5. Check "Product gallery" section - you should see all gallery images

### 4. Verify on Frontend

View the product on your store's frontend to ensure images display correctly.

## What If Images Still Don't Import?

If images still don't work after this fix, check these:

### 1. Image URLs Are Accessible
Make sure the image URLs in your CSV are publicly accessible:
- Open each URL in a browser
- Verify they load without errors
- Check if authentication is required

### 2. WordPress Media Upload Permissions
WordPress must have permission to download and save images:
```bash
# Check uploads directory permissions (on server)
ls -la wp-content/uploads
# Should be 755 or 775
```

### 3. PHP Memory Limit
Large images may require more memory:
```php
// Add to wp-config.php if needed
define('WP_MEMORY_LIMIT', '256M');
```

### 4. Check Image Import Function Errors
Look in debug.log for these errors:
```
ECV Image Import: Download failed: [error message]
ECV Image Import: Sideload failed: [error message]
```

### 5. File Type Restrictions
WordPress only allows certain image types by default:
- JPG/JPEG
- PNG
- GIF
- WebP (WordPress 5.8+)

### 6. Image URL Format
Make sure URLs are properly formatted:
- Must start with `http://` or `https://`
- No spaces or special characters that aren't URL-encoded
- Direct link to image file (not a page containing the image)

## CSV Format Reminder

Your CSV should:
1. **NOT** have a UTF-8 BOM (this fix handles it, but it's better to avoid it)
2. Use pipe `|` to separate multiple gallery images
3. Have valid, accessible URLs
4. Match this header format (with "Enable Cross Group" = "Yes" for your attribute-based format)

## Additional Notes

- The fix handles the BOM automatically, so you don't need to change your CSV
- Images are only downloaded once - if an image URL already exists in WordPress media library, it's reused
- Each image download/import is logged for troubleshooting
- The plugin now verifies images were saved correctly after each product import

## Support

If you continue experiencing issues:
1. Check the WordPress debug log (`wp-content/debug.log`)
2. Look for lines starting with "ECV Import:" or "ECV Create Product:"
3. Share any error messages for further assistance
