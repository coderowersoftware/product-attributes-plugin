<?php if (empty($extra)) return; ?>
<div id="ecv-extra-attributes" data-extra='<?php echo esc_attr(json_encode($extra)); ?>'>
  <div id="ecv-extra-price-display" style="display:none;">
    <strong><?php _e('Extra Options Price:', 'exp-custom-variations'); ?></strong> 
    <span id="ecv-extra-price-value"><?php echo ECV_CURRENCY_SYMBOL; ?>0.00</span>
  </div>
  <?php foreach ($extra as $ai => $attr): $display = isset($attr['display_type']) ? $attr['display_type'] : 'dropdown'; ?>
    <div class="ecv-extra-attr" data-ai="<?php echo (int)$ai; ?>">
      <div class="ecv-extra-label"><?php echo esc_html($attr['name'] ?? ''); ?></div>
      <?php if ($display === 'buttons'): ?>
        <div class="ecv-extra-variants">
          <?php foreach (($attr['variants'] ?? []) as $vi => $variant): 
            $price = isset($variant['price']) && $variant['price'] > 0 ? floatval($variant['price']) : 0;
          ?>
            <button type="button" class="ecv-extra-btn" data-ai="<?php echo (int)$ai; ?>" data-vi="<?php echo (int)$vi; ?>" data-price="<?php echo esc_attr($price); ?>"><?php echo esc_html($variant['name'] ?? ''); ?></button>
          <?php endforeach; ?>
        </div>
      <?php elseif ($display === 'radio'): ?>
        <div class="ecv-extra-variants ecv-extra-radio">
          <?php foreach (($attr['variants'] ?? []) as $vi => $variant): 
            $price = isset($variant['price']) && $variant['price'] > 0 ? floatval($variant['price']) : 0;
          ?>
            <label>
              <input type="radio" class="ecv-extra-radio-input" name="ecv_extra_attr_<?php echo (int)$ai; ?>" value="<?php echo (int)$vi; ?>" data-ai="<?php echo (int)$ai; ?>" data-price="<?php echo esc_attr($price); ?>" />
              <?php echo esc_html($variant['name'] ?? ''); ?>
            </label>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <select class="ecv-extra-select" data-ai="<?php echo (int)$ai; ?>">
          <option value=""><?php _e('Please select...', 'exp-custom-variations'); ?></option>
          <?php foreach (($attr['variants'] ?? []) as $vi => $variant): 
            $price = isset($variant['price']) && $variant['price'] > 0 ? floatval($variant['price']) : 0;
          ?>
            <option value="<?php echo (int)$vi; ?>" data-price="<?php echo esc_attr($price); ?>"><?php echo esc_html($variant['name'] ?? ''); ?></option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<input type="hidden" id="ecv_extra_attributes" name="ecv_extra_attributes" value="" />
