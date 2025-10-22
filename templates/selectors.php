<?php if (empty($data) && empty($combinations)) return; ?>
<?php
global $product;
$base_price = $product ? floatval($product->get_price()) : 0;
$show_images = get_post_meta($product->get_id(), '_ecv_show_images', true) ?: 'yes';
$group_pricing_enabled = ecv_get_group_pricing_enabled($product->get_id());
$dropdown_tooltips = get_post_meta($product->get_id(), '_ecv_dropdown_tooltips', true) ?: array();
?>
<div id="ecv-frontend-variations" 
     data-ecv='<?php echo esc_attr(json_encode($data)); ?>' 
     data-ecv-combinations='<?php echo esc_attr(json_encode($combinations)); ?>' 
     data-ecv-base-price='<?php echo esc_attr($base_price); ?>'
     data-show-images='<?php echo esc_attr($show_images); ?>'
     data-group-pricing='<?php echo esc_attr($group_pricing_enabled); ?>'
     data-ecv-tooltips='<?php echo esc_attr(json_encode($dropdown_tooltips)); ?>'>
     
    <!-- Variations Display (works for both traditional and group-based) -->
        <?php 
        // Debug: Log attribute data structure to help troubleshoot images
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ECV Template Debug: Attribute data structure: ' . print_r($data, true));
        }
        foreach ($data as $ai => $attr): 
            $display_type = isset($attr['display_type']) ? $attr['display_type'] : 'buttons';
        ?>
        <div class="ecv-attr-group ecv-display-<?php echo esc_attr($display_type); ?>" data-ai="<?php echo $ai; ?>" data-display-type="<?php echo esc_attr($display_type); ?>">
            <div class="ecv-attr-label"><?php echo esc_html($attr['name']); ?></div>
            
            <?php if ($display_type === 'dropdown'): ?>
                <!-- Dropdown Display -->
                <select class="ecv-variants-select" data-ai="<?php echo $ai; ?>">
                    <option value=""><?php _e('Please select...', 'exp-custom-variations'); ?></option>
                    <?php
                    // Group variants by 'group' property for dropdown optgroups
                    $groups = [];
                    foreach (($attr['variants'] ?? []) as $vi => $variant) {
                        $group = isset($variant['group']) && $variant['group'] !== '' ? $variant['group'] : __('Other', 'exp-custom-variations');
                        $groups[$group][] = [ 'vi' => $vi, 'variant' => $variant ];
                    }
                    
                    foreach ($groups as $group_label => $group_variants):
                        if (count($groups) > 1 && $group_label !== __('Other', 'exp-custom-variations')): ?>
                            <optgroup label="<?php echo esc_attr($group_label); ?>">
                        <?php endif;
                        
                        foreach ($group_variants as $item): 
                            $vi = $item['vi']; 
                            $variant = $item['variant']; ?>
                            <option value="<?php echo esc_attr($vi); ?>" data-ai="<?php echo $ai; ?>" data-vi="<?php echo $vi; ?>">
                                <?php echo esc_html($variant['name']); ?>
                            </option>
                        <?php endforeach;
                        
                        if (count($groups) > 1 && $group_label !== __('Other', 'exp-custom-variations')): ?>
                            </optgroup>
                        <?php endif;
                    endforeach; ?>
                </select>
                <div class="ecv-tooltip-container" data-ai="<?php echo $ai; ?>" style="margin-top: 8px; font-style: italic; color: #666; display: none;"></div>
                
            <?php elseif ($display_type === 'radio'): ?>
                <!-- Radio Button Display -->
                <?php
                // Group variants by 'group' property
                $groups = [];
                foreach (($attr['variants'] ?? []) as $vi => $variant) {
                    $group = isset($variant['group']) && $variant['group'] !== '' ? $variant['group'] : __('Other', 'exp-custom-variations');
                    $groups[$group][] = [ 'vi' => $vi, 'variant' => $variant ];
                }
                
                foreach ($groups as $group_label => $group_variants): ?>
                    <div class="ecv-variant-group">
                        <?php if (count($groups) > 1 && $group_label !== __('Other', 'exp-custom-variations')): ?>
                            <div class="ecv-variant-group-label"><?php echo esc_html($group_label); ?></div>
                        <?php endif; ?>
                        <div class="ecv-variants ecv-radio-variants">
                            <?php foreach ($group_variants as $item): 
                                $vi = $item['vi']; 
                                $variant = $item['variant']; ?>
                                <label class="ecv-radio-label" data-ai="<?php echo $ai; ?>" data-vi="<?php echo $vi; ?>">
                                    <input type="radio" 
                                           name="ecv_attr_<?php echo $ai; ?>" 
                                           value="<?php echo esc_attr($vi); ?>"
                                           class="ecv-variant-radio"
                                           data-ai="<?php echo $ai; ?>" 
                                           data-vi="<?php echo $vi; ?>" />
                                    <?php 
                                    // Prefer per-value button image, then group button image; do NOT fall back to combination image for buttons
                                    $variant_image = !empty($variant['button_image']) ? $variant['button_image'] : (!empty($variant['group_button_image']) ? $variant['group_button_image'] : '');
                                    if ($show_images === 'yes' && !empty($variant_image)): ?>
                                        <img src="<?php echo esc_url($variant_image); ?>" alt="" class="ecv-variant-image" />
                                    <?php endif; ?>
                                    <span class="ecv-variant-name"><?php echo esc_html($variant['name']); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
            <?php else: // Default: buttons ?>
                <!-- Button Display -->
                <?php
                // Group variants by 'group' property
                $groups = [];
                foreach (($attr['variants'] ?? []) as $vi => $variant) {
                    $group = isset($variant['group']) && $variant['group'] !== '' ? $variant['group'] : __('Other', 'exp-custom-variations');
                    $groups[$group][] = [ 'vi' => $vi, 'variant' => $variant ];
                }
                
                // Preserve original group order as provided in data/import (no sorting)
                
                foreach ($groups as $group_label => $group_variants): ?>
                    <div class="ecv-variant-group">
                        <?php if (count($groups) > 1 && $group_label !== __('Other', 'exp-custom-variations')): ?>
                            <div class="ecv-variant-group-label"><?php echo esc_html($group_label); ?></div>
                        <?php endif; ?>
                        <div class="ecv-variants ecv-button-variants">
                            <?php foreach ($group_variants as $item): 
                                $vi = $item['vi']; 
                                $variant = $item['variant']; ?>
                                <button type="button" class="ecv-variant-btn" data-ai="<?php echo $ai; ?>" data-vi="<?php echo $vi; ?>">
                                    <?php 
                                    // Prefer per-value button image, then group button image; do NOT fall back to combination image for buttons
                                    $variant_image = !empty($variant['button_image']) ? $variant['button_image'] : (!empty($variant['group_button_image']) ? $variant['group_button_image'] : '');
                                    
                                    if ($show_images === 'yes' && !empty($variant_image)): ?>
                                        <div class="ecv-image-container">
                                            <img src="<?php echo esc_url($variant_image); ?>" alt="" class="ecv-variant-image" />
                                        </div>
                                    <?php endif; ?>
                                    <span class="ecv-variant-name"><?php echo esc_html($variant['name']); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<div id="ecv-variation-details"></div>
<div id="ecv-price-display" style="margin-top: 15px; font-size: 1.2em; font-weight: bold;"></div>
<input type="hidden" name="ecv_selected_combination" id="ecv_selected_combination" value="" />
