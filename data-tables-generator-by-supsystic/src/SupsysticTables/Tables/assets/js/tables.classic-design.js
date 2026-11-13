(function ($, app) {
  'use strict';
  $(function () {
    var panel = $('#stb-classic-design-panel');
    if (!panel.length) return;
    var form = panel.closest('form'), presets = panel.data('presets'), fields = panel.data('fields'), applying = false, undo = null;
    var enabled = panel.find('[name="classicDesign[enabled]"]'), selection = panel.find('[name="classicDesign[preset]"]'), status = $('#stb-design-status');
    var paletteTier = 'all', paletteSearch = $('#stb-palette-search'), paletteCollection = $('#stb-palette-collection');
    var paletteGallery = panel.find('.stb-design-gallery')[0];
    // Only inside a slimScroll rail: elsewhere the gallery scrolls natively and the page can scroll past it.
    if (paletteGallery && $(paletteGallery).closest('.slimScrollDiv').length) {
      var supportsWheel = 'onwheel' in paletteGallery;
      function stopPaletteWheel(event) {
        event.stopPropagation();
        if (event.ctrlKey) return; // Preserve browser zoom and trackpad pinch.
        event.preventDefault();
        if (event.type !== 'wheel' && supportsWheel) return;
        var delta = event.deltaY;
        if (typeof delta !== 'number') delta = event.wheelDelta ? -event.wheelDelta / 3 : (event.detail || 0) * 16;
        if (event.deltaMode === 1) delta *= 16;
        if (event.deltaMode === 2) delta *= paletteGallery.clientHeight;
        paletteGallery.scrollTop += delta;
      }
      // slimScroll listens to legacy mousewheel on the outer settings rail.
      // Consume both event families, even when the gallery is at either edge.
      paletteGallery.addEventListener('wheel', stopPaletteWheel, { passive: false });
      paletteGallery.addEventListener('mousewheel', stopPaletteWheel, { passive: false });
      paletteGallery.addEventListener('DOMMouseScroll', stopPaletteWheel, { passive: false });
      paletteGallery.addEventListener('touchstart', function (event) { event.stopPropagation(); }, { passive: true });
      paletteGallery.addEventListener('touchmove', function (event) { event.stopPropagation(); }, { passive: true });
    }
    function filterGallery() {
      var query = $.trim(paletteSearch.val()).toLowerCase(), words = query ? query.split(/\s+/) : [], collection = paletteCollection.val(), count = 0;
      panel.find('.stb-design-card').each(function () {
        var card = $(this), preset = presets[card.data('preset')], haystack = (card.text() + ' ' + (preset ? preset.description : '') + ' ' + card.data('collection') + ' ' + card.data('tone')).toLowerCase();
        var matches = (paletteTier === 'all' || card.data('tier') === paletteTier) &&
          (!collection || (collection.indexOf('tone:') === 0 ? card.data('tone') === collection.substring(5) : card.data('collection') === collection)) &&
          words.every(function (word) { return haystack.indexOf(word) !== -1; });
        card.prop('hidden', !matches);
        if (matches) count++;
      });
      panel.find('[data-palette-tier]').each(function () { $(this).attr('aria-pressed', $(this).data('palette-tier') === paletteTier ? 'true' : 'false'); });
      $('#stb-palette-results').text(count + ' ' + $('#stb-palette-results').data('label'));
      $('#stb-palette-clear').prop('hidden', !query && !collection && paletteTier === 'all');
      panel.find('.stb-palette-empty').prop('hidden', count !== 0);
      panel.find('.stb-design-gallery').scrollTop(0);
    }
    panel.on('click', '[data-palette-tier]', function () { paletteTier = $(this).data('palette-tier'); filterGallery(); });
    paletteSearch.on('input search', filterGallery).on('keydown', function (event) { if (event.key === 'Enter' || event.which === 13) event.preventDefault(); });
    paletteCollection.on('change', filterGallery);
    $('#stb-palette-clear').on('click', function () { paletteTier = 'all'; paletteSearch.val(''); paletteCollection.val(''); filterGallery(); });
    function field(key) { return panel.find('[name="classicDesign[' + key + ']"]'); }
    function dirty() { if (typeof g_stbIsDataEdited !== 'undefined') g_stbIsDataEdited.settings = true; }
    function tokens() {
      var result = {};
      $.each(fields, function (key, spec) {
        var value = field(key).val();
        if (spec.type === 'color') value = /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(value) ? value : spec.default;
        else if (spec.type === 'weight') value = /^(400|500|600|700)$/.test(value) ? value : spec.default;
        else {
          value = value !== '' && isFinite(Number(value)) ? Math.max(spec.min, Math.min(spec.max, Number(value))) : spec.default;
          if (key !== 'lineHeight') value += 'px';
        }
        result['--stb-' + key] = value;
      });
      return result;
    }
    function update() {
      var active = enabled.prop('checked'), variables = tokens();
      panel.find('.stb-design-fields').prop('hidden', !active);
      $('#table-preview .supsystic-tables-wrap').toggleClass('stb-classic-design', active).css(variables)
        .attr('data-stb-sort-hover', form.find('[name="styles[showSortHover]"]').prop('checked') ? 'on' : 'off');
      $('#table-preview table.supsystic-table').toggleClass('stb-classic-table', active).css(variables);
      $(document).trigger('stb:classic-design');
      panel.find('.stb-design-card[data-preset]').each(function () { $(this).attr('aria-pressed', active && $(this).data('preset') === selection.val() ? 'true' : 'false'); });
      var preset = active && presets[selection.val()];
      status.text(preset ? status.data('applied') + ': ' + preset.name : status.data(active ? 'custom' : 'current'));
    }
    function snapshot() {
      var values = [];
      form.find('[name^="styles["], [name^="classicDesign["]').each(function () { values.push({ element: this, value: $(this).val(), checked: this.checked }); });
      var sheet = document.getElementById('stb-preview-css').sheet;
      return { values: values, css: Array.prototype.map.call(sheet.cssRules || [], function (rule) { return rule.cssText; }).join('\n'), disabled: sheet.disabled };
    }
    panel.find('.stb-design-palette-list').on('toggle', function () {
      var gallery = panel.find('.stb-design-gallery')[0], selected = panel.find('[data-preset][aria-pressed="true"]')[0];
      if (this.open && gallery && selected && !selected.hidden) {
        gallery.scrollTop += selected.getBoundingClientRect().top - gallery.getBoundingClientRect().top - (gallery.clientHeight - selected.offsetHeight) / 2;
      }
    });
    panel.on('click', '[data-preset]', function () {
      if ($(this).attr('data-locked') === '1') {
        status.text(status.data('locked'));
        return;
      }
      var preset = presets[$(this).data('preset')];
      if (!preset) return;
      undo = snapshot();
      applying = true;
      $.each(preset.styles, function (key, value) { form.find('[name="styles[' + key + ']"]').val(value).trigger('change'); });
      $.each(preset.values, function (key, value) { field(key).val(value).trigger('change'); });
      form.find('[name="styles[useCustomStyles]"]').prop('checked', true).trigger('change');
      enabled.prop('checked', true);
      if ($.fn.iCheck) { enabled.iCheck('update'); form.find('[name="styles[useCustomStyles]"]').iCheck('update'); }
      selection.val($(this).data('preset'));
      applying = false;
      $('#stb-design-undo').prop('disabled', false);
      update(); dirty();
      if (!$('#table-preview table.supsystic-table').length) $('#loadPreviewBtn').trigger('click');
    });
    $('#stb-design-undo').on('click', function () {
      if (!undo) return;
      applying = true;
      $.each(undo.values, function (_, item) { $(item.element).val(item.value).prop('checked', item.checked).trigger('change'); });
      if ($.fn.iCheck) form.find('input[type=checkbox][name^="styles["],input[type=checkbox][name^="classicDesign["]').iCheck('update');
      var style = document.getElementById('stb-preview-css');
      style.textContent = undo.css; style.sheet.disabled = undo.disabled;
      applying = false;
      update(); dirty(); status.text(status.data('restored'));
      undo = null; $(this).prop('disabled', true);
    });
    form.on('change.classicDesign input.classicDesign ifChanged.classicDesign', '[name^="classicDesign["], [name^="styles["]', function () {
      if (applying) return;
      // Existing saved preview CSS uses a literal cell text color. Give the
      // new stripe/hover text fields a fallback without changing that color.
      if (this.name === 'classicDesign[enabled]' && enabled.prop('checked') && form.find('[name="styles[useCustomStyles]"]').prop('checked')) {
        applying = true;
        form.find('[name="styles[cellFontColor]"]').trigger('change');
        applying = false;
      }
      var searchMap = { inputBg: 'searchBackgroundColor', inputText: 'searchFontColor', inputBorder: 'searchBorderColor' }, changed = this;
      $.each(searchMap, function (token, legacy) {
        if (changed.name === 'classicDesign[' + token + ']') {
          applying = true; form.find('[name="styles[' + legacy + ']"]').val($(changed).val()).trigger('change'); applying = false;
        } else if (changed.name === 'styles[' + legacy + ']') {
          field(token).val($(changed).val());
        }
      });
      if (this.name !== 'classicDesign[enabled]' && this.name !== 'classicDesign[preset]') selection.val('');
      if ($(this).hasClass('color-input')) $(this).closest('.color-settings-wrapper').find('.color-picker-preview').css('background-color', $(this).val());
      update(); dirty();
    });
    var preview = document.getElementById('table-preview');
    // Preview markup is replaced by AJAX. Keep tokens live without re-saving table rows.
    if (preview && window.MutationObserver) new MutationObserver(function (changes) {
      if (changes.some(function (change) { return change.addedNodes.length; })) update();
    }).observe(preview, { childList: true, subtree: true });
    update();
    filterGallery();
  });
})(jQuery, window.supsystic.Tables);
