<div id="ecv-extra-attrs-admin">
  <p class="description">Manage extra attributes that do not affect the existing ECV variations panel. These are independent and will appear as a separate section on the product page.</p>
  <div id="ecv-extra-attrs-list"></div>
  <button type="button" id="ecv-extra-add-attribute" class="button">Add Extra Attribute</button>
</div>
<input type="hidden" name="ecv_extra_attrs_data" id="ecv_extra_attrs_data" value='<?php echo esc_attr(json_encode($extra ?: [])); ?>' />
<script>
window.ecv_extra_attrs_data = <?php echo json_encode($extra ?: []); ?>;
</script>
