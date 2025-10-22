// Admin UI logic for Extra Attributes panel (separate from core variations)
jQuery(document).ready(function($){
  'use strict';
  const container = $('#ecv-extra-attrs-admin');
  if (!container.length) return;

  let extra = window.ecv_extra_attrs_data || [];

  function save() {
    $('#ecv_extra_attrs_data').val(JSON.stringify(extra));
  }

  function render() {
    let html = '';
    (extra || []).forEach((attr, ai) => {
      const display = attr.display_type || 'dropdown';
      const cond = attr.visible_when || null;
      const parentName = cond && cond.attribute ? cond.attribute : '';
      const parentValues = (extra.find(a => (a.name||'')===parentName)?.variants||[]).map(v=>v.name).filter(Boolean);
      html += `
      <div class="ecv-extra-attr" data-ai="${ai}">
        <div class="ecv-extra-attr-header">
          <input type="text" class="ecv-extra-name" placeholder="Attribute label (e.g. With/Without Lock)" value="${attr.name||''}" />
          <select class="ecv-extra-display" data-ai="${ai}" style="margin-left:8px;">
            <option value="buttons"${display==='buttons'?' selected':''}>Buttons</option>
            <option value="dropdown"${display==='dropdown'?' selected':''}>Dropdown</option>
            <option value="radio"${display==='radio'?' selected':''}>Radio Buttons</option>
          </select>
          <button type="button" class="button ecv-extra-remove" data-ai="${ai}" style="margin-left:8px;">Remove</button>
        </div>
        <div class="ecv-extra-visibility" style="margin-top:6px;">
          <label>
            <input type="checkbox" class="ecv-extra-cond-toggle" ${cond?'checked':''} /> Show only when another extra attribute has value
          </label>
          <div class="ecv-extra-cond-fields" style="margin-top:6px; ${cond?'':'display:none;'}">
            <select class="ecv-extra-parent" data-ai="${ai}">
              <option value="">Select parent attribute</option>
              ${extra.map((a, idx) => a.name && idx!==ai ? `<option value="${String(a.name).replace(/"/g,'&quot;')}"${a.name===parentName?' selected':''}>${a.name}</option>` : '').join('')}
            </select>
            <select class="ecv-extra-parent-value" data-ai="${ai}" style="margin-left:6px;">
              <option value="">Select value</option>
              ${(parentValues||[]).map(v => `<option value="${String(v).replace(/"/g,'&quot;')}"${cond && cond.value===v?' selected':''}>${v}</option>`).join('')}
            </select>
          </div>
        </div>
        <div class="ecv-extra-variants" style="margin-top:8px;">
          ${(attr.variants||[]).map((v, vi) => `
            <div class="ecv-extra-variant" data-vi="${vi}" style="display:flex; align-items:center; margin-bottom:8px;">
              <input type="text" class="ecv-extra-variant-name" placeholder="Option (e.g. Handle Pair Only)" value="${v.name||''}" style="flex:1;" />
              <input type="number" class="ecv-extra-variant-price" placeholder="Price" value="${v.price||''}" step="0.01" min="0" style="width:100px; margin-left:8px;" title="Additional price for this option" />
              <button type="button" class="button ecv-extra-variant-remove" data-ai="${ai}" data-vi="${vi}" style="margin-left:6px;">Remove</button>
            </div>
          `).join('')}
          <button type="button" class="button ecv-extra-variant-add" data-ai="${ai}">Add Option</button>
        </div>
      </div>`;
    });
    container.find('#ecv-extra-attrs-list').html(html || '<em>No extra attributes defined.</em>');
    save();
  }

  // Initial render
  render();

  // Events
  container.on('click', '#ecv-extra-add-attribute', function(){
    extra.push({ name:'', display_type:'dropdown', variants:[] });
    render();
  });

  container.on('click', '.ecv-extra-remove', function(){
    const ai = $(this).data('ai');
    extra.splice(ai,1);
    render();
  });

  container.on('click', '.ecv-extra-variant-add', function(){
    const ai = $(this).data('ai');
    extra[ai].variants = extra[ai].variants || [];
    extra[ai].variants.push({name:''});
    render();
  });

  container.on('click', '.ecv-extra-variant-remove', function(){
    const ai = $(this).data('ai');
    const vi = $(this).data('vi');
    extra[ai].variants.splice(vi,1);
    render();
  });

  container.on('input', '.ecv-extra-name', function(){
    const ai = $(this).closest('.ecv-extra-attr').data('ai');
    extra[ai].name = $(this).val();
    save();
  });

  container.on('change', '.ecv-extra-display', function(){
    const ai = $(this).data('ai');
    extra[ai].display_type = $(this).val();
    save();
  });

  container.on('input', '.ecv-extra-variant-name', function(){
    const ai = $(this).closest('.ecv-extra-attr').data('ai');
    const vi = $(this).closest('.ecv-extra-variant').data('vi');
    extra[ai].variants[vi].name = $(this).val();
    save();
  });

  container.on('input', '.ecv-extra-variant-price', function(){
    const ai = $(this).closest('.ecv-extra-attr').data('ai');
    const vi = $(this).closest('.ecv-extra-variant').data('vi');
    extra[ai].variants[vi].price = $(this).val();
    save();
  });

  container.on('change', '.ecv-extra-cond-toggle', function(){
    const $wrap = $(this).closest('.ecv-extra-attr');
    const ai = $wrap.data('ai');
    if ($(this).is(':checked')) {
      extra[ai].visible_when = { attribute:'', value:'' };
      $wrap.find('.ecv-extra-cond-fields').show();
    } else {
      delete extra[ai].visible_when;
      $wrap.find('.ecv-extra-cond-fields').hide();
    }
    save();
  });

  container.on('change', '.ecv-extra-parent', function(){
    const ai = $(this).data('ai');
    const parentName = $(this).val();
    extra[ai].visible_when = extra[ai].visible_when || {attribute:'', value:''};
    extra[ai].visible_when.attribute = parentName;
    extra[ai].visible_when.value = '';
    render(); // refresh values dropdown
  });

  container.on('change', '.ecv-extra-parent-value', function(){
    const ai = $(this).data('ai');
    const val = $(this).val();
    extra[ai].visible_when = extra[ai].visible_when || {attribute:'', value:''};
    extra[ai].visible_when.value = val;
    save();
  });
});