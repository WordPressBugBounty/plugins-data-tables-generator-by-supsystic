(function ($) {
  'use strict';

  $(function () {
    var $root = $('.st-pricing-editor');
    if (!$root.length || typeof grapesjs === 'undefined') {
      return;
    }
    if ($.fn.iCheck) {
      $root.find('input[type="checkbox"]').each(function () {
        var $input = $(this);
        if ($input.parent().hasClass('icheckbox_minimal')) { $input.iCheck('destroy'); }
      });
    }
    var builder = JSON.parse($('#st-pricing-data').text());
    var tableId = parseInt($root.data('table-id'), 10);
    var nonce = $root.data('nonce');
    var status = $('#st-pricing-status');
    var currentTemplate = builder.template || 'classic';
    var sourceDirty = { html: false, css: false };
    var fontLibrary = JSON.parse($('#st-pricing-font-library').text() || '{}');
    var fontNames = [].concat(fontLibrary.local || [], fontLibrary.google || [], fontLibrary.system || []).filter(function (font, index, list) { return list.indexOf(font) === index; });
    var usedFonts = (builder.fonts || []).slice();
    var iconLibrary = JSON.parse($('#st-pricing-icon-library-data').text() || '[]');
    var toggleOptions = (builder.toggleOptions || []).slice();
    if (!toggleOptions.length) { toggleOptions = [{ key: 'monthly', label: 'Monthly' }, { key: 'yearly', label: 'Yearly' }]; }
    var toggleVariants = $.extend({}, builder.toggleVariants || {});
    var toggleVariantStyles = $.extend({}, builder.toggleVariantStyles || {});
    var activeToggleKey = builder.toggleDefault || toggleOptions[0].key;
    var wooCommerceActive = JSON.parse($('#st-pricing-woocommerce-active').text() || 'false');
    var wooProducts = JSON.parse($('#st-pricing-woocommerce-products').text() || '[]');
    var sourceMode = null;
    var sourceEditors = {};
    var sourceSyncing = false;
    var imageSizes = {};
    function isZeroCssValue(value) {
      var parts = String(value || '').trim().toLowerCase().split(/\s+/);
      return parts.length > 0 && parts.length <= 4 && parts.every(function (part) {
        return /^0(?:px|em|rem|%|vw|vh)?$/.test(part);
      });
    }
    function normalizePricingCss(css) {
      return String(css || '').replace(/(^|(?<=}))\s*([^{}]+)\{([^{}]*)\}/g, function (rule, boundary, selector, declarationText) {
        var normalizedSelector = selector.trim().toLowerCase();
        var declarations = {};
        var valid = true;
        declarationText.replace(/\/\*[\s\S]*?\*\//g, '').split(';').forEach(function (declaration) {
          declaration = declaration.trim();
          if (!declaration) { return; }
          var separator = declaration.indexOf(':');
          if (separator < 1) { valid = false; return; }
          declarations[declaration.slice(0, separator).trim().toLowerCase()] = declaration.slice(separator + 1).trim().toLowerCase();
        });
        if (!valid) { return rule; }
        var properties = Object.keys(declarations);
        var protectedBoxSizing = normalizedSelector === '*' && properties.length === 1 && declarations['box-sizing'] === 'border-box';
        var marginProperties = ['margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left'];
        var onlyMargins = properties.length > 0 && properties.every(function (property) { return marginProperties.indexOf(property) !== -1; });
        var allMarginsZero = onlyMargins && properties.every(function (property) { return isZeroCssValue(declarations[property]); });
        var hasCompleteMargin = Object.prototype.hasOwnProperty.call(declarations, 'margin') ||
          ['margin-top', 'margin-right', 'margin-bottom', 'margin-left'].every(function (property) {
            return Object.prototype.hasOwnProperty.call(declarations, property);
          });
        var protectedBodyMargin = normalizedSelector === 'body' && allMarginsZero && hasCompleteMargin;
        return protectedBoxSizing || protectedBodyMargin ? '' : rule;
      }).trim();
    }
    builder.css = normalizePricingCss(builder.css);
    Object.keys(toggleVariantStyles).forEach(function (key) {
      toggleVariantStyles[key] = normalizePricingCss(toggleVariantStyles[key]);
    });
    function getPricingCss() {
      return normalizePricingCss(editor.getCss({ avoidProtected: true }));
    }
    function pricingEditorComponents(editorInstance) {
      editorInstance.DomComponents.addType('pricing-woo-button', {
        isComponent: function (element) {
          return element && element.tagName === 'BUTTON' && element.classList.contains('st-pricing-woo-cart-button') ? { type: 'pricing-woo-button' } : false;
        },
        model: {
          defaults: {
            type: 'pricing-woo-button', tagName: 'button', droppable: false, editable: false,
            attributes: { type: 'button' }, components: []
          }
        }
      });
      editorInstance.DomComponents.addType('pricing-feature', {
        isComponent: function (element) {
          return element && element.tagName === 'LI' && element.classList.contains('st-pricing-feature') ? { type: 'pricing-feature' } : false;
        },
        model: { defaults: { type: 'pricing-feature', tagName: 'li', droppable: '.st-pricing-feature-content', editable: false } }
      });
      editorInstance.DomComponents.addType('pricing-feature-content', {
        isComponent: function (element) {
          return element && element.classList && element.classList.contains('st-pricing-feature-content') ? { type: 'pricing-feature-content' } : false;
        },
        model: { defaults: { type: 'pricing-feature-content', tagName: 'span', droppable: true, editable: false } }
      });
    }
    var editor = grapesjs.init({
      container: '#st-pricing-grapes',
      height: '72vh',
      fromElement: false,
      components: toggleVariants[activeToggleKey] || builder.html,
      style: normalizePricingCss(toggleVariantStyles[activeToggleKey] || builder.css),
      storageManager: { type: null },
      canvas: { styles: JSON.parse($('#st-pricing-canvas-css').text()) },
      colorPicker: { appendTo: 'body' },
      assetManager: { upload: false, assets: [] },
      avoidInlineStyle: 1,
      showOffsets: 1,
      plugins: [pricingEditorComponents, 'grapesjs-style-bg'],
      selectorManager: { componentFirst: true },
      panels: { defaults: [] },
      blockManager: { appendTo: '#st-pricing-blocks' },
      layerManager: { appendTo: '#st-pricing-layers', scrollLayers: false, showWrapper: false },
      traitManager: { appendTo: '#st-pricing-traits' },
      styleManager: {
        appendTo: '#st-pricing-styles',
        clearProperties: 1,
        sectors: [
          { name: 'General', open: true, buildProps: ['display', 'position', 'float', 'overflow'] },
          { name: 'Dimension', open: false, buildProps: ['width', 'height', 'max-width', 'min-height', 'margin', 'padding'] },
          { name: 'Typography', open: false, buildProps: ['font-family', 'font-size', 'font-weight', 'letter-spacing', 'color', 'line-height', 'text-align', 'text-decoration', 'text-shadow'] },
          { name: 'Decorations', open: false, buildProps: ['background-bg', 'background-color', 'opacity', 'border', 'border-radius', 'box-shadow'] },
          { name: 'Flex', open: false, buildProps: ['flex-direction', 'justify-content', 'align-items', 'flex-wrap'] }
        ]
      },
      deviceManager: {
        devices: [
          { name: 'Desktop', width: '' },
          { name: 'Tablet', width: '768px', widthMedia: '768px' },
          { name: 'Mobile', width: '375px', widthMedia: '375px' }
        ]
      }
    });

    editor.DomComponents.addType('st-video-embed', {
      model: {
        defaults: {
          tagName: 'iframe',
          droppable: false,
          stPricingTraits: true,
          attributes: { class: 'st-pricing-video', loading: 'lazy', allowfullscreen: true, title: 'Embedded video' },
          traits: [
            { type: 'text', name: 'src', label: 'Embed URL', placeholder: 'https://…' },
            { type: 'text', name: 'title', label: 'Accessible title' },
            { type: 'select', name: 'loading', label: 'Loading', options: [{ id: 'lazy', name: 'Lazy' }, { id: 'eager', name: 'Eager' }] },
            { type: 'checkbox', name: 'allowfullscreen', label: 'Allow fullscreen' },
            { type: 'checkbox', name: 'data-pause-out-of-view', label: 'Pause outside viewport' }
          ]
        }
      }
    });

    var planCardHtml = '<article class="st-pricing-card"><header class="st-pricing-header"><h3>New plan</h3><p>Plan description</p></header><div class="st-pricing-price"><strong>$29</strong><small>/ month</small></div><ul class="st-pricing-features"><li class="st-pricing-feature"><span class="st-pricing-feature-content"><span class="st-pricing-icon fa fa-check" data-icon-scale="1" aria-hidden="true"></span><span class="st-pricing-feature-text">Feature one</span></span></li><li class="st-pricing-feature"><span class="st-pricing-feature-content"><span class="st-pricing-icon fa fa-check" data-icon-scale="1" aria-hidden="true"></span><span class="st-pricing-feature-text">Feature two</span></span></li></ul><footer class="st-pricing-footer"><a class="st-pricing-button" href="#">Get started</a></footer></article>';
    function withDefaultAlignment(html) {
      return String(html || '').replace(/^<([a-z][a-z0-9-]*)(?=[\s>])/i, '<$1 data-horizontal-align="center" data-vertical-align="middle"');
    }
    var blocks = [
      ['heading', 'Heading', '<h3>Plan name</h3>'],
      ['description', 'Description', '<p>Describe this plan</p>'],
      ['price', 'Price', '<div class="st-pricing-price"><strong>$29</strong><small>/ month</small></div>'],
      ['cross-price', 'Cross price', '<div class="st-pricing-cross-price"><span class="st-pricing-cross-currency">$</span><del class="st-pricing-cross-value">59</del></div>'],
      ['feature', 'Feature', '<li class="st-pricing-feature"><span class="st-pricing-feature-content"><span class="st-pricing-icon fa fa-check" data-icon-scale="1" aria-hidden="true"></span><span class="st-pricing-feature-text">New feature</span></span></li>'],
      ['button', 'Button', '<a class="st-pricing-button" href="#">Choose plan</a>'],
      ['badge', 'Badge', '<span class="st-pricing-badge">Popular</span>'],
      ['icon', 'Icon', '<span class="st-pricing-icon fa fa-check" data-icon-scale="1" aria-hidden="true"></span>'],
      ['image', 'Image', '<img src="" alt="Plan illustration">'],
      ['countdown', 'Countdown', '<div class="st-pricing-countdown" data-st-countdown-mode="duration" data-st-countdown-duration="600" data-st-countdown-target="" data-st-countdown-label="Offer ends in" data-st-countdown-expired="Offer ended"><span class="st-pricing-countdown-label">Offer ends in</span><div class="st-pricing-countdown-parts"><span><strong data-st-countdown-unit="days">00</strong><small>Days</small></span><span><strong data-st-countdown-unit="hours">00</strong><small>Hours</small></span><span><strong data-st-countdown-unit="minutes">10</strong><small>Minutes</small></span><span><strong data-st-countdown-unit="seconds">00</strong><small>Seconds</small></span></div></div>']
    ];
    var blockIcons = {
      heading: 'fa-header', description: 'fa-align-left',
      price: 'fa-usd', 'cross-price': 'fa-tag', feature: 'fa-check', button: 'fa-mouse-pointer',
      badge: 'fa-star', icon: 'fa-smile-o', image: 'fa-image', countdown: 'fa-clock-o'
    };
    blocks.forEach(function (block) {
      editor.BlockManager.add('pricing-' + block[0], {
        label: block[1],
        category: 'Pricing',
        media: '<i class="fa ' + blockIcons[block[0]] + '" aria-hidden="true"></i>',
        content: withDefaultAlignment(block[2])
      });
    });
    editor.BlockManager.add('pricing-video', {
      label: 'Video', category: 'Pricing', media: '<i class="fa fa-play-circle" aria-hidden="true"></i>',
      content: { type: 'video', provider: 'yt', videoId: 'jNQXAC9IVRw', style: { display: 'inline-block', width: '100%', height: 'auto', 'min-height': '0', 'aspect-ratio': '16 / 9', 'object-fit': 'contain' }, attributes: { class: 'st-pricing-video', loading: 'lazy', controls: true, playsinline: true, 'data-horizontal-align': 'center', 'data-vertical-align': 'middle' } }
    });
    editor.BlockManager.add('pricing-woo-cart', {
      label: wooCommerceActive ? 'Woo cart' : 'Woo cart (inactive)', category: 'Pricing',
      media: '<i class="fa fa-shopping-cart" aria-hidden="true"></i>',
      content: wooCommerceActive ? '<button type="button" class="st-pricing-button st-pricing-woo-cart st-pricing-woo-cart-button" data-horizontal-align="center" data-vertical-align="middle" data-st-products="" data-st-coupon="" data-st-button-text="Add package to cart" data-st-redirect-url="" data-st-redirect-target="_self" aria-label="Add package to cart"></button>' : '',
      attributes: wooCommerceActive ? {} : { title: 'Activate WooCommerce to insert this block', class: 'st-pricing-block-disabled', 'aria-disabled': 'true' }
    });
    fontNames.forEach(function (font) { $('#st-pricing-font-list').append($('<option>').attr('value', font)); });
    if (!editor.StyleManager.getSectors().length) {
      editor.StyleManager.addSector('general', { name: 'General', open: true, buildProps: ['display', 'position', 'float', 'overflow'] });
      editor.StyleManager.addSector('dimension', { name: 'Dimension', open: false, buildProps: ['width', 'height', 'max-width', 'min-height', 'margin', 'padding'] });
      editor.StyleManager.addSector('typography', { name: 'Typography', open: false, buildProps: ['font-family', 'font-size', 'font-weight', 'letter-spacing', 'color', 'line-height', 'text-align', 'text-decoration', 'text-shadow'] });
      editor.StyleManager.addSector('decorations', { name: 'Decorations', open: false, buildProps: ['background-bg', 'background-color', 'opacity', 'border', 'border-radius', 'box-shadow'] });
      editor.StyleManager.addSector('flex', { name: 'Flex', open: false, buildProps: ['flex-direction', 'justify-content', 'align-items', 'flex-wrap'] });
    }
    function enhanceVideo(component) {
      if (!component) { return; }
      var type = component.get('type');
      var isEmbed = String(component.get('tagName') || '').toLowerCase() === 'iframe';
      if (type !== 'video' && !isEmbed) { return; }
      component.addStyle({ display: 'inline-block', width: '100%', height: 'auto', 'min-height': '0', 'aspect-ratio': '16 / 9', 'object-fit': 'contain' });
      if (component.get('stPricingTraits')) { return; }
      component.set('stPricingTraits', true, { silent: true });
      var extraTraits = isEmbed ? [
        { type: 'text', name: 'src', label: 'Embed URL', placeholder: 'https://…' },
        { type: 'text', name: 'title', label: 'Accessible title' },
        { type: 'select', name: 'loading', label: 'Loading', options: [{ id: 'lazy', name: 'Lazy' }, { id: 'eager', name: 'Eager' }] },
        { type: 'checkbox', name: 'allowfullscreen', label: 'Allow fullscreen' },
        { type: 'checkbox', name: 'data-pause-out-of-view', label: 'Pause outside viewport' }
      ] : [
        { type: 'checkbox', name: 'muted', label: 'Muted' },
        { type: 'checkbox', name: 'playsinline', label: 'Play inline' },
        { type: 'select', name: 'preload', label: 'Preload', options: [
          { id: 'metadata', name: 'Metadata' }, { id: 'none', name: 'None' }, { id: 'auto', name: 'Auto' }
        ] },
        { type: 'select', name: 'loading', label: 'Loading', options: [{ id: 'lazy', name: 'Lazy' }, { id: 'eager', name: 'Eager' }] },
        { type: 'checkbox', name: 'data-pause-out-of-view', label: 'Pause outside viewport' }
      ];
      component.addTrait(extraTraits);
    }
    function componentHasClass(component, className) {
      var element = component && component.getEl ? component.getEl() : null;
      if (element && element.classList) { return element.classList.contains(className); }
      var attrs = component && component.getAttributes ? component.getAttributes() : {};
      return (' ' + String(attrs.class || '') + ' ').indexOf(' ' + className + ' ') !== -1;
    }
    function componentLayerName(component) {
      if (!component || !component.get) { return ''; }
      var element = component.getEl ? component.getEl() : null;
      var tagName = String(component.get('tagName') || (element && element.tagName) || '').toLowerCase();
      var text = element && element.textContent ? element.textContent.replace(/\s+/g, ' ').trim() : '';
      if (component.get('type') === 'wrapper') { return 'Pricing table'; }
      if (tagName === 'body') { return 'Canvas'; }
      if (componentHasClass(component, 'st-pricing-grid')) { return 'Pricing table'; }
      if (componentHasClass(component, 'st-pricing-description-card')) { return 'Description column'; }
      if (componentHasClass(component, 'st-pricing-card')) {
        var heading = element && element.querySelector ? element.querySelector('h3') : null;
        return 'Plan: ' + ((heading && heading.textContent.trim()) || 'New plan');
      }
      if (componentHasClass(component, 'st-pricing-header')) { return 'Header'; }
      if (componentHasClass(component, 'st-pricing-price')) { return 'Price'; }
      if (componentHasClass(component, 'st-pricing-cross-price')) { return 'Cross price'; }
      if (componentHasClass(component, 'st-pricing-cross-currency')) { return 'Cross price currency'; }
      if (componentHasClass(component, 'st-pricing-cross-value')) { return 'Cross price value'; }
      if (componentHasClass(component, 'st-pricing-countdown')) { return 'Countdown timer'; }
      if (componentHasClass(component, 'st-pricing-woo-cart')) { return 'WooCommerce cart button'; }
      if (componentHasClass(component, 'st-pricing-features')) { return 'Features'; }
      if (componentHasClass(component, 'st-pricing-feature-value')) { return 'Feature check'; }
      if (componentHasClass(component, 'st-pricing-feature')) { return 'Feature'; }
      if (componentHasClass(component, 'st-pricing-feature-content')) { return 'Feature content'; }
      if (componentHasClass(component, 'st-pricing-feature-text')) { return 'Feature text'; }
      if (componentHasClass(component, 'st-pricing-footer')) { return 'Footer'; }
      if (componentHasClass(component, 'st-pricing-button')) { return 'Button: ' + (text || 'Choose plan'); }
      if (componentHasClass(component, 'st-pricing-badge')) { return 'Badge: ' + (text || 'Badge'); }
      if (componentHasClass(component, 'st-pricing-icon') || componentHasClass(component, 'fa')) { return 'Icon'; }
      if (componentHasClass(component, 'st-pricing-video') || tagName === 'video' || tagName === 'iframe') { return 'Video'; }
      if (tagName === 'img') { return 'Image'; }
      if (tagName === 'li') { return 'Feature: ' + (text || 'item'); }
      if (tagName === 'h3') { return 'Plan title'; }
      if (tagName === 'p') { return 'Description'; }
      if (tagName === 'strong') { return 'Price value'; }
      if (tagName === 'small') { return 'Billing period'; }
      if (tagName === 'a') { return 'Link: ' + (text || 'link'); }
      return '';
    }
    function assignLayerName(component) {
      var name = componentLayerName(component);
      if (name && component.get('name') !== name) { component.set('name', name); }
      if (name && component.viewLayer && component.viewLayer.getInputName) {
        component.viewLayer.getInputName().textContent = name;
      }
      var children = component && component.components ? component.components() : null;
      if (children && children.each) { children.each(assignLayerName); }
    }
    var normalizingNestedBlock = false;
    function componentChildrenHtml(component) {
      var html = [];
      var children = component && component.components ? component.components() : null;
      if (children && children.each) {
        children.each(function (child) { html.push(child.toHTML ? child.toHTML() : String(child.get('content') || '')); });
      }
      return html.join('');
    }
    function flattenNestedPricingBlock(component) {
      if (!component || normalizingNestedBlock) { return false; }
      var className = componentHasClass(component, 'st-pricing-feature') ? 'st-pricing-feature' :
        (componentHasClass(component, 'st-pricing-price') ? 'st-pricing-price' : '');
      if (!className) { return false; }
      var outer = componentAncestor(component.parent ? component.parent() : null, className);
      if (!outer || outer === component) { return false; }
      var source = className === 'st-pricing-feature' && component.find ? component.find('.st-pricing-feature-content')[0] : component;
      var target = className === 'st-pricing-feature' && outer.find ? outer.find('.st-pricing-feature-content')[0] : outer;
      var innerHtml = componentChildrenHtml(source);
      if (!target || !target.components || !innerHtml) { return false; }
      normalizingNestedBlock = true;
      target.components().add(innerHtml);
      component.remove();
      normalizingNestedBlock = false;
      window.setTimeout(function () { assignLayerName(outer); }, 0);
      return true;
    }
    function enhanceImage(component) {
      if (!component || String(component.get('tagName') || '').toLowerCase() !== 'img') { return; }
      if (component.get('stPricingResizable')) { return; }
      component.set('resizable', { tl: 1, tc: 1, tr: 1, cl: 1, cr: 1, bl: 1, bc: 1, br: 1 });
      component.set('stPricingResizable', true, { silent: true });
    }
    function normalizeDefaultIcon(component) {
      if (!componentHasClass(component, 'st-pricing-icon')) { return; }
      var attrs = $.extend({}, component.getAttributes ? component.getAttributes() : {});
      if (!attrs['data-icon-scale']) {
        attrs['data-icon-scale'] = '1';
        component.setAttributes(attrs);
      }
    }
    editor.on('component:add', function (component) {
      if (flattenNestedPricingBlock(component)) { return; }
      enhanceVideo(component);
      enhanceImage(component);
      normalizeDefaultIcon(component);
      window.setTimeout(function () { assignLayerName(component); }, 0);
    });

    function isKnownFont(font) { return fontNames.indexOf(font) !== -1; }
    function rememberFont(font) {
      if (isKnownFont(font) && usedFonts.indexOf(font) === -1) { usedFonts.push(font); }
      if ((fontLibrary.google || []).indexOf(font) !== -1) {
        var doc = editor.Canvas.getDocument();
        if (doc && !doc.querySelector('link[data-st-font="' + font.replace(/"/g, '') + '"]')) {
          var link = doc.createElement('link');
          link.rel = 'stylesheet';
          link.dataset.stFont = font;
          link.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(font).replace(/%20/g, '+') + ':wght@300;400;500;600;700;800&display=swap';
          doc.head.appendChild(link);
        }
      }
    }
    function syncFontClearButton(inputSelector, buttonSelector) {
      $(buttonSelector).prop('hidden', !$(inputSelector).val().trim());
    }
    function resetTableFont(announce) {
      var grid = editor.getWrapper().find('.st-pricing-grid')[0];
      if (grid && grid.removeStyle) { grid.removeStyle('font-family'); }
      $('#st-pricing-table-font').val('');
      syncFontClearButton('#st-pricing-table-font', '#st-pricing-clear-table-font');
      if (announce) { status.text('Table font reset to default'); }
    }
    function applyTableFont(font, announce) {
      if (!font) { resetTableFont(announce); return; }
      if (!isKnownFont(font)) { status.text('Choose a font from the library.'); return; }
      rememberFont(font);
      var grid = editor.getWrapper().find('.st-pricing-grid')[0];
      if (grid) { grid.addStyle({ 'font-family': "'" + font + "', sans-serif" }); }
      $('#st-pricing-table-font').val(font);
      syncFontClearButton('#st-pricing-table-font', '#st-pricing-clear-table-font');
      if (announce) { status.text('Table font applied'); }
    }
    function resetElementFont(announce) {
      var selected = editor.getSelected();
      if (!selected) { status.text('Select an element first.'); return; }
      if (selected.removeStyle) { selected.removeStyle('font-family'); }
      $('#st-pricing-element-font-input').val('');
      syncFontClearButton('#st-pricing-element-font-input', '#st-pricing-clear-element-font');
      if (announce) { status.text('Element font reset to inherited'); }
    }
    function applyElementFont() {
      var font = $('#st-pricing-element-font-input').val().trim();
      var selected = editor.getSelected();
      if (!selected) { status.text('Select an element first.'); return; }
      if (!font) { resetElementFont(true); return; }
      if (!isKnownFont(font)) { status.text('Choose a font from the library.'); return; }
      rememberFont(font);
      selected.addStyle({ 'font-family': "'" + font + "', sans-serif" });
      syncFontClearButton('#st-pricing-element-font-input', '#st-pricing-clear-element-font');
      status.text('Element font applied');
    }
    $('#st-pricing-apply-table-font').on('click', function () { applyTableFont($('#st-pricing-table-font').val().trim(), true); });
    $('#st-pricing-table-font').on('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); applyTableFont(this.value.trim(), true); } });
    $('#st-pricing-apply-element-font').on('click', applyElementFont);
    $('#st-pricing-element-font-input').on('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); applyElementFont(); } });
    $('#st-pricing-table-font').on('input change', function () { syncFontClearButton('#st-pricing-table-font', '#st-pricing-clear-table-font'); });
    $('#st-pricing-element-font-input').on('input change', function () { syncFontClearButton('#st-pricing-element-font-input', '#st-pricing-clear-element-font'); });
    $('#st-pricing-clear-table-font').on('click', function () { resetTableFont(true); });
    $('#st-pricing-clear-element-font').on('click', function () { resetElementFont(true); });

    function getGrid() { return editor.getWrapper().find('.st-pricing-grid')[0]; }
    function maxWidthLimits(unit) { return unit === 'px' ? { min: 240, max: 5000 } : { min: 10, max: 100 }; }
    function normalizeMaxWidth(value, unit) {
      var limits = maxWidthLimits(unit);
      var parsed = parseFloat(value);
      if (!isFinite(parsed)) { parsed = unit === 'px' ? 1200 : 100; }
      return Math.max(limits.min, Math.min(limits.max, parsed));
    }
    function syncMaxWidthInput() {
      var unit = $('#st-pricing-max-width-unit').val() === 'px' ? 'px' : '%';
      var limits = maxWidthLimits(unit);
      var $input = $('#st-pricing-max-width').attr({ min: limits.min, max: limits.max });
      $input.val(normalizeMaxWidth($input.val(), unit));
    }
    function applyTableMaxWidth(value, unit, announce) {
      unit = unit === 'px' ? 'px' : '%';
      value = normalizeMaxWidth(value, unit);
      var grid = getGrid();
      if (grid) {
        grid.addStyle({ width: '100%', 'max-width': value + unit, 'margin-left': 'auto', 'margin-right': 'auto' });
      }
      $('#st-pricing-max-width-unit').val(unit);
      $('#st-pricing-max-width').val(value);
      syncMaxWidthInput();
      if (announce) { status.text('Maximum table width applied'); }
    }
    $('#st-pricing-max-width-unit').on('change', syncMaxWidthInput);
    $('#st-pricing-apply-max-width').on('click', function () {
      applyTableMaxWidth($('#st-pricing-max-width').val(), $('#st-pricing-max-width-unit').val(), true);
    });
    $('#st-pricing-max-width').on('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        applyTableMaxWidth(this.value, $('#st-pricing-max-width-unit').val(), true);
      }
    });
    $('#st-pricing-add-column').on('click', function () {
      var grid = getGrid();
      if (!grid) { status.text('Pricing table canvas is not available.'); return; }
      var column = grid.components().add(planCardHtml);
      assignLayerName(column);
      editor.select(column);
      window.requestAnimationFrame(alignEditorFeatureRows);
      status.text('Column added to the end of the table');
    });
    function toggleGridClass(grid, className, enabled) { if (!grid) { return; } enabled ? grid.addClass(className) : grid.removeClass(className); }
    function applySectionSettings() {
      var grid = getGrid();
      toggleGridClass(grid, 'st-pricing-hide-header', !$('#st-pricing-header').is(':checked'));
      toggleGridClass(grid, 'st-pricing-hide-description', !$('#st-pricing-description').is(':checked'));
      toggleGridClass(grid, 'st-pricing-hide-footer', !$('#st-pricing-footer').is(':checked'));
      toggleGridClass(grid, 'st-pricing-animated', $('#st-pricing-animation').is(':checked'));
      setDescriptionColumn($('#st-pricing-description-column').is(':checked'));
    }
    function setDescriptionColumn(enabled) {
      var grid = getGrid();
      if (!grid) { return; }
      var existing = grid.find('.st-pricing-description-card')[0];
      toggleGridClass(grid, 'st-pricing-has-description', enabled);
      if (!enabled && existing) { existing.remove(); alignEditorFeatureRows(); return; }
      if (enabled && !existing) {
        var firstPlan = grid.find('.st-pricing-card:not(.st-pricing-description-card)')[0];
        var labels = ['Feature one', 'Feature two', 'Feature three'];
        if (firstPlan) {
          var items = firstPlan.find('.st-pricing-features li');
          if (items.length) { labels = items.map(function (item) { return item.getEl().textContent.trim(); }); }
        }
        var html = '<article class="st-pricing-card st-pricing-description-card"><header class="st-pricing-header"><h3>Features</h3><p>Compare plans</p></header><div class="st-pricing-price"></div><ul class="st-pricing-features">' + labels.map(function (label) { return '<li>' + $('<div>').text(label).html() + '</li>'; }).join('') + '</ul><footer class="st-pricing-footer"></footer></article>';
        grid.components().add(html, { at: 0 });
      }
      window.requestAnimationFrame(alignEditorFeatureRows);
    }
    function editorFeatureList(card) {
      return Array.prototype.find.call(card.children, function (child) { return child.classList.contains('st-pricing-features'); }) || card.querySelector('.st-pricing-features');
    }
    function editorFeatureRows(list) {
      return list ? Array.prototype.filter.call(list.children, function (child) { return child.tagName === 'LI'; }) : [];
    }
    function editorLayoutSelector(element) {
      var value = element.id || '';
      if (!value) { return '.st-pricing-grid'; }
      if (window.CSS && window.CSS.escape) { value = window.CSS.escape(value); }
      else { value = value.replace(/([^a-zA-Z0-9_-])/g, '\\$1'); }
      return '#' + value;
    }
    function editorRuntimeStyle(element, rules) {
      var doc = element.ownerDocument;
      var style = doc.getElementById('st-pricing-editor-comparison-layout');
      if (!style) {
        style = doc.createElement('style');
        style.id = 'st-pricing-editor-comparison-layout';
        doc.head.appendChild(style);
      }
      style.textContent = rules.join('\n');
    }
    function syncEditorFeatureLabels(element, descriptionCard, cards) {
      var descriptionList = editorFeatureList(descriptionCard);
      if (!descriptionList) { return; }
      var labels = editorFeatureRows(descriptionList).map(function (row) { return row.textContent.trim(); });
      cards.filter(function (card) { return card !== descriptionCard; }).forEach(function (card) {
        var list = editorFeatureList(card);
        if (!list) { return; }
        editorFeatureRows(list).forEach(function (row, index) {
          if (labels[index]) { row.setAttribute('data-st-feature-label', labels[index]); }
          else { row.removeAttribute('data-st-feature-label'); }
        });
      });
    }
    function alignEditorFeatureRows() {
      var grid = getGrid();
      var element = grid && grid.getEl ? grid.getEl() : null;
      if (!element) { return; }
      var descriptionCard = element.querySelector('.st-pricing-description-card');
      if (!descriptionCard) { element.classList.remove('st-pricing-responsive-comparison'); editorRuntimeStyle(element, []); return; }
      var cards = Array.prototype.filter.call(element.children, function (child) { return child.classList.contains('st-pricing-card'); });
      var lists = cards.map(editorFeatureList).filter(Boolean);
      var selector = editorLayoutSelector(element);
      var rules = [];
      editorRuntimeStyle(element, rules);
      syncEditorFeatureLabels(element, descriptionCard, cards);
      var view = element.ownerDocument.defaultView || window;
      element.classList.remove('st-pricing-responsive-comparison');
      var visibleCards = cards.filter(function (card) { return view.getComputedStyle(card).display !== 'none'; });
      var firstRowTop = visibleCards.length ? visibleCards[0].offsetTop : 0;
      var compact = visibleCards.some(function (card) { return Math.abs(card.offsetTop - firstRowTop) > 1; });
      if (compact) { element.classList.add('st-pricing-responsive-comparison'); return; }

      var headers = cards.map(function (card) { return card.querySelector('.st-pricing-header'); }).filter(Boolean);
      var prices = cards.map(function (card) { return card.querySelector('.st-pricing-price'); }).filter(Boolean);
      var footers = cards.map(function (card) { return card.querySelector('.st-pricing-footer'); }).filter(Boolean);
      var maxHeight = function (nodes) { return Math.ceil(nodes.reduce(function (max, node) { return Math.max(max, node.getBoundingClientRect().height); }, 0)); };
      var headerHeight = maxHeight(headers);
      var priceHeight = maxHeight(prices);
      var footerHeight = maxHeight(footers);
      if (headerHeight) { rules.push(selector + ' > .st-pricing-card > .st-pricing-header{min-height:' + headerHeight + 'px!important}'); }
      if (priceHeight) { rules.push(selector + ' > .st-pricing-card > .st-pricing-price{min-height:' + priceHeight + 'px!important}'); }
      if (footerHeight) { rules.push(selector + ' > .st-pricing-card > .st-pricing-footer{min-height:' + footerHeight + 'px!important}'); }
      editorRuntimeStyle(element, rules);

      var listTops = lists.map(function (list) { return list.getBoundingClientRect().top - list.closest('.st-pricing-card').getBoundingClientRect().top; });
      var maxTop = listTops.reduce(function (max, top) { return Math.max(max, top); }, 0);
      lists.forEach(function (list, listIndex) {
        var delta = Math.ceil(maxTop - listTops[listIndex]);
        if (!delta) { return; }
        var card = list.closest('.st-pricing-card');
        var childIndex = Array.prototype.indexOf.call(element.children, card) + 1;
        rules.push(selector + ' > .st-pricing-card:nth-child(' + childIndex + ') > .st-pricing-features{margin-top:' + delta + 'px!important}');
      });
      var rowsByList = lists.map(editorFeatureRows);
      var count = rowsByList.reduce(function (max, rows) { return Math.max(max, rows.length); }, 0);
      for (var index = 0; index < count; index += 1) {
        var rows = rowsByList.map(function (listRows) { return listRows[index]; }).filter(Boolean);
        var rowTops = rows.map(function (row) { return row.getBoundingClientRect().top - row.closest('.st-pricing-card').getBoundingClientRect().top; });
        var maxRowTop = rowTops.reduce(function (max, top) { return Math.max(max, top); }, 0);
        rows.forEach(function (row, rowIndex) {
          var delta = Math.ceil(maxRowTop - rowTops[rowIndex]);
          if (!delta) { return; }
          var childIndex = Array.prototype.indexOf.call(element.children, row.closest('.st-pricing-card')) + 1;
          rules.push(selector + ' > .st-pricing-card:nth-child(' + childIndex + ') > .st-pricing-features > li:nth-of-type(' + (index + 1) + '){margin-top:' + delta + 'px!important}');
        });
        editorRuntimeStyle(element, rules);
        var height = rows.reduce(function (max, row) { return Math.max(max, row.getBoundingClientRect().height); }, 0);
        if (height) { rules.push(selector + ' > .st-pricing-card > .st-pricing-features > li:nth-of-type(' + (index + 1) + '){min-height:' + Math.ceil(height) + 'px!important}'); }
        editorRuntimeStyle(element, rules);
      }
      editorRuntimeStyle(element, rules);
    }
    var alignTimer;
    editor.on('update', function () { clearTimeout(alignTimer); alignTimer = setTimeout(alignEditorFeatureRows, 80); });
    $('#st-pricing-header').on('change', function () { toggleGridClass(getGrid(), 'st-pricing-hide-header', !this.checked); });
    $('#st-pricing-description').on('change', function () { toggleGridClass(getGrid(), 'st-pricing-hide-description', !this.checked); });
    $('#st-pricing-footer').on('change', function () { toggleGridClass(getGrid(), 'st-pricing-hide-footer', !this.checked); });
    $('#st-pricing-animation').on('change', function () { toggleGridClass(getGrid(), 'st-pricing-animated', this.checked); });
    $('#st-pricing-description-column').on('change', function () { setDescriptionColumn(this.checked); });
    function syncAjaxToggleFields() {
      $('.st-pricing-ajax-text-setting').prop('hidden', !$('#st-pricing-ajax-toggle').is(':checked'));
    }
    $('#st-pricing-ajax-toggle').on('change', syncAjaxToggleFields);
    syncAjaxToggleFields();
    function syncPricingSwitcherFields() {
      $('.st-pricing-toggle-settings').prop('hidden', !$('#st-pricing-toggle').is(':checked'));
    }
    $('#st-pricing-toggle').on('change', syncPricingSwitcherFields);
    syncPricingSwitcherFields();
    var lastSectionState = '';
    function syncSectionSettings() {
      var sectionState = [
        $('#st-pricing-header').is(':checked'), $('#st-pricing-description').is(':checked'),
        $('#st-pricing-footer').is(':checked'), $('#st-pricing-animation').is(':checked'),
        $('#st-pricing-description-column').is(':checked')
      ].join(':');
      if (sectionState !== lastSectionState) {
        lastSectionState = sectionState;
        applySectionSettings();
      }
    }
    var sectionSyncTimer = window.setInterval(syncSectionSettings, 200);
    editor.on('destroy', function () { window.clearInterval(sectionSyncTimer); });

    function formatHtmlSource(html) {
      var blockTags = 'section|article|header|footer|main|aside|nav|div|ul|ol|li|h[1-6]|p|figure|figcaption|table|thead|tbody|tfoot|tr|th|td|video|iframe|blockquote';
      var pattern = new RegExp('(<\\/?(?:' + blockTags + ')\\b[^>]*>)', 'gi');
      var lines = String(html || '').replace(/>\s+</g, '><').replace(pattern, '\n$1\n').split(/\n+/).map(function (line) { return line.trim(); }).filter(Boolean);
      var depth = 0;
      return lines.map(function (line) {
        var closes = new RegExp('^<\\/(?:' + blockTags + ')\\b', 'i').test(line);
        var opens = new RegExp('^<(?:' + blockTags + ')\\b[^>]*>$', 'i').test(line) && !/\/>$/.test(line);
        if (closes) { depth = Math.max(0, depth - 1); }
        var output = new Array(depth + 1).join('  ') + line;
        if (opens) { depth += 1; }
        return output;
      }).join('\n');
    }
    function formatCssSource(css) {
      var input = String(css || '');
      var output = '';
      var depth = 0;
      var quote = '';
      var escaped = false;
      var comment = false;
      var parentheses = 0;
      var lineStart = true;
      function indent() { return new Array(depth + 1).join('  '); }
      function append(value) {
        if (lineStart && value !== '\n') { output += indent(); lineStart = false; }
        output += value;
      }
      for (var index = 0; index < input.length; index += 1) {
        var character = input[index];
        var next = input[index + 1];
        if (comment) {
          append(character);
          if (character === '*' && next === '/') { append('/'); index += 1; comment = false; }
          continue;
        }
        if (quote) {
          append(character);
          if (escaped) { escaped = false; }
          else if (character === '\\') { escaped = true; }
          else if (character === quote) { quote = ''; }
          continue;
        }
        if (character === '/' && next === '*') { append('/*'); index += 1; comment = true; continue; }
        if (character === '"' || character === "'") { quote = character; append(character); continue; }
        if (character === '(') { parentheses += 1; append(character); continue; }
        if (character === ')') { parentheses = Math.max(0, parentheses - 1); append(character); continue; }
        if (!parentheses && character === '{') {
          output = output.replace(/[ \t]+$/, '') + ' {\n';
          depth += 1;
          lineStart = true;
          continue;
        }
        if (!parentheses && character === ';') {
          append(';');
          output += '\n';
          lineStart = true;
          continue;
        }
        if (!parentheses && character === '}') {
          output = output.replace(/[ \t\n]+$/, '') + '\n';
          depth = Math.max(0, depth - 1);
          lineStart = true;
          append('}');
          output += '\n\n';
          lineStart = true;
          continue;
        }
        if (/\s/.test(character)) {
          if (!lineStart && !/[ \n]$/.test(output)) { output += ' '; }
          continue;
        }
        append(character);
      }
      return output.trim();
    }
    function formatSource(mode, value) {
      return mode === 'html' ? formatHtmlSource(value) : formatCssSource(value);
    }
    function sourceValue(mode) {
      return sourceEditors[mode] ? sourceEditors[mode].getValue() : $('#st-pricing-' + mode + '-source').val();
    }
    function setSourceValue(mode, value) {
      sourceSyncing = true;
      $('#st-pricing-' + mode + '-source').val(value);
      if (sourceEditors[mode]) { sourceEditors[mode].setValue(value); }
      sourceSyncing = false;
    }
    function initSourceEditor(mode, mime) {
      var id = 'st-pricing-' + mode + '-source';
      if (!document.getElementById(id) || !window.wp || !wp.codeEditor || typeof wp.codeEditor.initialize !== 'function') { return; }
      var instance = wp.codeEditor.initialize(id, {
        codemirror: { mode: mime, lineNumbers: true, lineWrapping: true, indentUnit: 2, tabSize: 2, indentWithTabs: false }
      });
      if (instance && instance.codemirror) {
        sourceEditors[mode] = instance.codemirror;
        instance.codemirror.on('change', function (cm) {
          if (sourceSyncing) { return; }
          $('#' + id).val(cm.getValue());
          sourceDirty[mode] = true;
        });
      }
    }
    initSourceEditor('html', 'text/html');
    initSourceEditor('css', 'text/css');
    function setSourceMode(mode) {
      sourceMode = mode || null;
      if (sourceMode === 'html' && !sourceDirty.html) { setSourceValue('html', formatSource('html', editor.getHtml())); }
      if (sourceMode === 'css' && !sourceDirty.css) { setSourceValue('css', formatSource('css', getPricingCss())); }
      $root.toggleClass('is-source-mode', !!sourceMode).attr('data-source-mode', sourceMode || '');
      $('.st-pricing-source-workspace').each(function () {
        $(this).prop('hidden', $(this).data('source-pane') !== sourceMode);
      });
      $('.st-pricing-source-tab').each(function () {
        var active = $(this).data('source-mode') === sourceMode;
        $(this).toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false');
      });
      if (sourceMode && sourceEditors[sourceMode]) {
        window.setTimeout(function () { sourceEditors[sourceMode].refresh(); sourceEditors[sourceMode].focus(); }, 0);
      } else {
        window.setTimeout(function () { editor.refresh(); }, 0);
      }
    }
    $('.st-pricing-source-tab').on('click', function () {
      var requested = $(this).data('source-mode');
      setSourceMode(sourceMode === requested ? null : requested);
    });
    $('.st-pricing-close-source').on('click', function () { setSourceMode(null); });
    function toolbarStorageKey(name) { return 'supsysticPricing.toolbar.' + name; }
    function setToolbarCollapsed(name, collapsed, persist) {
      var $toolbar = $('[data-collapsible-toolbar="' + name + '"]');
      $toolbar.toggleClass('is-collapsed', collapsed);
      $toolbar.find('[data-collapse-toolbar="' + name + '"]').attr('aria-expanded', collapsed ? 'false' : 'true');
      $toolbar.find('.st-pricing-collapse-icon').toggleClass('fa-chevron-up', !collapsed).toggleClass('fa-chevron-down', collapsed);
      if (persist) {
        try { window.localStorage.setItem(toolbarStorageKey(name), collapsed ? '1' : '0'); } catch (ignore) {}
      }
      window.setTimeout(function () { editor.refresh(); }, 0);
    }
    [{ name: 'global', collapsed: false }, { name: 'blocks', collapsed: false }, { name: 'layers', collapsed: true }].forEach(function (item) {
      var name = item.name;
      var collapsed = item.collapsed;
      try {
        var stored = window.localStorage.getItem(toolbarStorageKey(name));
        if (stored !== null) { collapsed = stored === '1'; }
      } catch (ignore) {}
      setToolbarCollapsed(name, collapsed, false);
    });
    $('[data-collapse-toolbar]').on('click', function () {
      var name = $(this).data('collapse-toolbar');
      setToolbarCollapsed(name, !$('[data-collapsible-toolbar="' + name + '"]').hasClass('is-collapsed'), true);
    });
    $(document).on('click', '.gjs-field-color-picker', function () {
      window.setTimeout(function () {
        $('.sp-container:visible').each(function () {
          var rect = this.getBoundingClientRect();
          var left = rect.left;
          var top = rect.top;
          if (rect.right > window.innerWidth - 10) { left = Math.max(10, window.innerWidth - rect.width - 10); }
          if (rect.bottom > window.innerHeight - 10) { top = Math.max(10, window.innerHeight - rect.height - 10); }
          $(this).css({ left: left + 'px', top: top + 'px' });
        });
      }, 0);
    });
    $('#st-pricing-html-source').on('input', function () { if (!sourceSyncing) { sourceDirty.html = true; } });
    $('#st-pricing-css-source').on('input', function () { if (!sourceSyncing) { sourceDirty.css = true; } });
    $('.st-pricing-devices').on('click', 'button', function () {
      editor.setDevice($(this).data('device'));
      $('.st-pricing-devices button').removeClass('is-active');
      $(this).addClass('is-active');
      window.setTimeout(alignEditorFeatureRows, 120);
    });
    var undoManager = editor.UndoManager;
    function updateHistoryButtons() {
      $('#st-pricing-undo').prop('disabled', !undoManager.hasUndo());
      $('#st-pricing-redo').prop('disabled', !undoManager.hasRedo());
    }
    editor.on('update', updateHistoryButtons);
    $('#st-pricing-undo').on('click', function () { undoManager.undo(); updateHistoryButtons(); });
    $('#st-pricing-redo').on('click', function () { undoManager.redo(); updateHistoryButtons(); });

    function setTemplate(name) {
      var $template = $('.st-pricing-template[data-template="' + name + '"]');
      if (!$template.length || $template.is(':disabled') || $template.hasClass('is-locked')) { return; }
      currentTemplate = name;
      $('.st-pricing-template').toggleClass('is-active', false);
      $template.addClass('is-active');
      var section = editor.getWrapper().find('.st-pricing-grid')[0];
      if (section) {
        $('.st-pricing-template[data-template]').each(function () { section.removeClass('st-pricing-' + $(this).data('template')); });
        section.addClass('st-pricing-' + name);
      }
    }
    setTemplate(currentTemplate);
    editor.on('load', function () {
      normalizeWooButtonComponents();
      normalizeFeatureComponents();
      normalizeIconComponents();
      setTemplate(currentTemplate);
      usedFonts.forEach(rememberFont);
      applyTableFont(builder.fontFamily || '', false);
      applyTableMaxWidth(builder.maxWidth || 100, builder.maxWidthUnit || '%', false);
      applySectionSettings();
      assignLayerName(editor.getWrapper());
      editor.getWrapper().find('.st-pricing-video').forEach(enhanceVideo);
      editor.getWrapper().find('img').forEach(enhanceImage);
      normalizeAlignmentComponents();
      editor.LayerManager.render();
    });
    editor.on('rteToolbarPosUpdate', function (position) {
      var selected = editor.getSelected();
      var element = selected && selected.getEl ? selected.getEl() : null;
      if (!element || !position) { return; }
      var elementPosition = editor.Canvas.getElementPos(element);
      position.top = elementPosition.height + 8;
    });
    $('.st-pricing-template').on('click', function () { setTemplate($(this).data('template')); });

    function showPriceFields(component) {
      var $fields = $('#st-pricing-price-fields');
      if (!$fields.length) {
        return;
      }
      var element = component && component.getEl ? component.getEl() : null;
      var isPrice = element && element.matches && element.matches('.st-pricing-price strong');
      $fields.prop('hidden', !isPrice);
      if (isPrice) {
        var attrs = component.getAttributes();
        $('#st-pricing-monthly').val(attrs['data-price-monthly'] || $(element).text());
        $('#st-pricing-yearly').val(attrs['data-price-yearly'] || '');
      }
    }
    editor.on('component:selected', showPriceFields);
    editor.on('component:selected', function (component) {
      enhanceVideo(component);
      enhanceImage(component);
      assignLayerName(component);
      setContextToolbar(component);
      var name = component && component.getName ? component.getName() : (component && component.get('tagName')) || 'Element';
      $('#st-pricing-selected-name').text('— ' + name);
      $('#st-pricing-settings-hint').prop('hidden', true);
      $('#st-pricing-style-hint').prop('hidden', true);
      $('#st-pricing-element-font').prop('hidden', false);
      var selectedFont = (component.getStyle && component.getStyle()['font-family']) || '';
      $('#st-pricing-element-font-input').val(selectedFont.replace(/["']/g, '').split(',')[0].trim());
      syncFontClearButton('#st-pricing-element-font-input', '#st-pricing-clear-element-font');
    });
    editor.on('component:deselected', function () {
      $root.removeClass('has-selected-pricing-column');
      $('#st-pricing-selected-name').text('');
      $('#st-pricing-settings-hint').prop('hidden', false);
      $('#st-pricing-style-hint').prop('hidden', false);
      $('#st-pricing-element-font').prop('hidden', true);
      $('#st-pricing-media,#st-pricing-icon-library').prop({ hidden: true, disabled: true });
      $('#st-pricing-image-size-wrap,#st-pricing-price-fields,#st-pricing-schedule-section,#st-pricing-schedule-fields,#st-pricing-woo-settings,#st-pricing-countdown-settings,#st-pricing-featured-setting').prop('hidden', true);
    });
    editor.on('component:selected', function (component) {
      var $fields = $('#st-pricing-schedule-fields');
      var $section = $('#st-pricing-schedule-section');
      if (!$fields.length) { return; }
      var element = component && component.getEl ? component.getEl() : null;
      var isCard = element && element.matches && element.matches('.st-pricing-card');
      $section.prop('hidden', !isCard);
      $fields.prop('hidden', !isCard);
      if (isCard) {
        var attrs = component.getAttributes();
        $('#st-pricing-visible-from').val(attrs['data-visible-from'] || '');
        $('#st-pricing-visible-until').val(attrs['data-visible-until'] || '');
      }
    });
    $('#st-pricing-visible-from, #st-pricing-visible-until').on('change', function () {
      var selected = editor.getSelected();
      var element = selected && selected.getEl ? selected.getEl() : null;
      if (!element || !element.matches('.st-pricing-card')) { return; }
      var attrs = selected.getAttributes();
      var from = $('#st-pricing-visible-from').val();
      var until = $('#st-pricing-visible-until').val();
      if (from) { attrs['data-visible-from'] = from; } else { delete attrs['data-visible-from']; }
      if (until) { attrs['data-visible-until'] = until; } else { delete attrs['data-visible-until']; }
      selected.setAttributes(attrs);
    });
    function componentAncestor(component, className) {
      var current = component;
      while (current) {
        if (componentHasClass(current, className)) { return current; }
        current = current.parent ? current.parent() : null;
      }
      return null;
    }
    function selectedPlanCard(component) {
      var parent = component && component.parent ? component.parent() : null;
      return component && componentHasClass(component, 'st-pricing-card') &&
        !componentHasClass(component, 'st-pricing-description-card') &&
        parent && componentHasClass(parent, 'st-pricing-grid') ? component : null;
    }
    function syncFeaturedSetting(component) {
      var card = selectedPlanCard(component);
      $root.toggleClass('has-selected-pricing-column', !!card);
      $('#st-pricing-featured-setting').prop('hidden', !card).attr('aria-hidden', card ? 'false' : 'true');
      $('#st-pricing-featured').prop('checked', !!(card && componentHasClass(card, 'st-pricing-featured')));
    }
    editor.on('component:selected', function (component) {
      $root.removeClass('has-selected-pricing-column');
      $('#st-pricing-featured-setting').prop('hidden', true).attr('aria-hidden', 'true');
      window.setTimeout(function () {
        syncFeaturedSetting(editor.getSelected() || component);
      }, 0);
    });
    $('#st-pricing-featured').on('change', function () {
      var card = selectedPlanCard(editor.getSelected());
      if (!card) { return; }
      if (this.checked) { card.addClass('st-pricing-featured'); }
      else { card.removeClass('st-pricing-featured'); }
      status.text(this.checked ? 'Column highlighted' : 'Column highlight removed');
    });
    function parseWooLines(value) {
      var lines = {};
      String(value || '').split(',').forEach(function (line) {
        var parts = line.split(':');
        var id = parseInt(parts[0], 10);
        if (id) { lines[id] = Math.max(1, parseInt(parts[1], 10) || 1); }
      });
      return lines;
    }
    function renderWooQuantities(lines) {
      $('#st-pricing-woo-quantities').html(($('#st-pricing-woo-products').val() || []).map(function (id) {
        var product = wooProducts.filter(function (item) { return String(item.id) === String(id); })[0];
        return '<label data-product-id="' + id + '"><span>' + $('<div>').text(product ? product.name : ('Product #' + id)).html() + '</span><input type="number" min="1" max="100" value="' + (lines[id] || 1) + '" aria-label="Quantity"></label>';
      }).join(''));
    }
    function selectedWooComponent() { return componentAncestor(editor.getSelected(), 'st-pricing-woo-cart'); }
    function collapseRepeatedButtonText(value) {
      value = String(value || '').trim();
      for (var length = 4; length <= Math.floor(value.length / 2); length += 1) {
        if (value.length % length !== 0) { continue; }
        var unit = value.slice(0, length);
        if (unit.repeat(value.length / length) === value) { return unit.trim(); }
      }
      return value;
    }
    function componentText(component, fallback) {
      if (!component) { return fallback; }
      var attrs = component.getAttributes ? component.getAttributes() : {};
      var attributeText = collapseRepeatedButtonText(attrs['data-st-button-text']);
      if (attributeText) { return attributeText; }
      var ownContent = component.get ? collapseRepeatedButtonText(component.get('content')) : '';
      if (ownContent) { return ownContent; }
      var children = component.components ? component.components() : null;
      var parts = [];
      if (children && children.each) {
        children.each(function (child) {
          var content = child.get ? child.get('content') : '';
          if (typeof content === 'string') { parts.push(content); }
          else if (child.getEl && child.getEl()) { parts.push(child.getEl().textContent || ''); }
        });
      }
      var value = parts.join('').trim();
      if (!value && component.getEl && component.getEl()) { value = component.getEl().textContent.trim(); }
      return collapseRepeatedButtonText(value) || fallback;
    }
    function setComponentText(component, value) {
      if (!component) { return; }
      value = collapseRepeatedButtonText(value) || 'Add package to cart';
      var attrs = $.extend({}, component.getAttributes ? component.getAttributes() : {}, {
        'data-st-button-text': value, 'aria-label': value, type: 'button'
      });
      var classes = String(attrs.class || '').split(/\s+/).filter(Boolean);
      ['st-pricing-button', 'st-pricing-woo-cart', 'st-pricing-woo-cart-button'].forEach(function (className) {
        if (classes.indexOf(className) === -1) { classes.push(className); }
      });
      attrs.class = classes.join(' ');
      component.setAttributes(attrs);
      if (component.set) { component.set('content', ''); }
      if (component.components) { component.components().reset(); }
    }
    function normalizeWooButtonComponents() {
      var wrapper = editor.getWrapper ? editor.getWrapper() : null;
      if (!wrapper || !wrapper.find) { return; }
      wrapper.find('.st-pricing-woo-cart').slice().forEach(function (block) {
        if (componentHasClass(block, 'st-pricing-woo-cart-button')) {
          setComponentText(block, componentText(block, 'Add package to cart'));
          return;
        }
        var button = block.find && block.find('.st-pricing-woo-cart-button')[0];
        var parent = block.parent ? block.parent() : null;
        if (!button || !parent || !parent.components) { return; }
        var label = componentText(button, 'Add package to cart');
        var blockAttrs = block.getAttributes ? block.getAttributes() : {};
        var buttonAttrs = button.getAttributes ? button.getAttributes() : {};
        var attrs = $.extend({}, buttonAttrs, {
          type: 'button', 'data-st-products': blockAttrs['data-st-products'] || '',
           'data-st-coupon': blockAttrs['data-st-coupon'] || '', 'data-st-button-text': label,
          'data-st-redirect-url': blockAttrs['data-st-redirect-url'] || '',
          'data-st-redirect-target': blockAttrs['data-st-redirect-target'] === '_blank' ? '_blank' : '_self',
          'aria-label': label, 'class': 'st-pricing-button st-pricing-woo-cart st-pricing-woo-cart-button'
        });
        var collection = parent.components();
        var index = collection.indexOf(block);
        collection.add({
          type: 'pricing-woo-button', tagName: 'button', attributes: attrs, components: [],
          style: $.extend({}, block.getStyle ? block.getStyle() : {}, button.getStyle ? button.getStyle() : {})
        }, { at: Math.max(0, index) });
        block.remove();
      });
    }
    function normalizeFeatureComponents() {
      var wrapper = editor.getWrapper ? editor.getWrapper() : null;
      if (!wrapper || !wrapper.find) { return; }
      wrapper.find('.st-pricing-features li').slice().forEach(function (feature) {
        if (componentAncestor(feature, 'st-pricing-description-card')) { return; }
        if (!componentHasClass(feature, 'st-pricing-feature')) { feature.addClass('st-pricing-feature'); }
        if (feature.find && feature.find('.st-pricing-feature-content').length) { return; }
        var childHtml = [];
        var children = feature.components ? feature.components() : null;
        if (children && children.each) {
          children.each(function (child) { childHtml.push(child.toHTML ? child.toHTML() : String(child.get('content') || '')); });
        }
        var originalHtml = childHtml.join('').trim();
        var text = feature.getEl && feature.getEl() ? feature.getEl().textContent.replace(/\s+/g, ' ').trim() : $('<div>').html(originalHtml).text().replace(/\s+/g, ' ').trim();
        var isCheck = componentHasClass(feature, 'st-pricing-feature-value');
        var richContent = /<[^>]+>/.test(originalHtml);
        var content = (isCheck || richContent)
          ? '<span class="st-pricing-feature-content">' + (originalHtml || '<i class="fa fa-check" aria-hidden="true"></i>') + '</span>'
          : '<span class="st-pricing-feature-content"><span class="st-pricing-icon fa fa-check" data-icon-scale="1" aria-hidden="true"></span><span class="st-pricing-feature-text">' + $('<div>').text(text || 'New feature').html() + '</span></span>';
        feature.components(content);
      });
    }
    function normalizeIconComponents() {
      var wrapper = editor.getWrapper ? editor.getWrapper() : null;
      if (!wrapper || !wrapper.find) { return; }
      wrapper.find('.st-pricing-icon').forEach(normalizeDefaultIcon);
    }
    function loadPricingComponents(html) {
      editor.setComponents(html);
      normalizeWooButtonComponents();
      normalizeFeatureComponents();
      normalizeIconComponents();
      window.setTimeout(normalizeAlignmentComponents, 0);
    }
    function syncWooComponent() {
      var component = selectedWooComponent();
      if (!component || !wooCommerceActive) { return; }
      var lines = [];
      $('#st-pricing-woo-quantities label').each(function () {
        lines.push($(this).data('product-id') + ':' + Math.max(1, parseInt($(this).find('input').val(), 10) || 1));
      });
      var attrs = $.extend({}, component.getAttributes(), {
        'data-st-products': lines.join(','), 'data-st-coupon': $('#st-pricing-woo-coupon').val().trim(),
        'data-st-redirect-url': $('#st-pricing-woo-redirect').val() || '',
        'data-st-redirect-target': $('#st-pricing-woo-redirect-target').val() === '_blank' ? '_blank' : '_self'
      });
      component.setAttributes(attrs);
      var button = componentHasClass(component, 'st-pricing-woo-cart-button') ? component : component.find('.st-pricing-woo-cart-button')[0];
      if (button) { setComponentText(button, $('#st-pricing-woo-button-text').val()); }
    }
    wooProducts.forEach(function (product) {
      $('#st-pricing-woo-products').append($('<option>').val(product.id).text(product.name + (product.price ? ' — ' + product.price : '')));
    });
    if ($.fn.chosen && $('#st-pricing-woo-products').length) {
      $('#st-pricing-woo-products').chosen({
        width: '100%', search_contains: true, display_selected_options: false,
        placeholder_text_multiple: 'Search and select products', no_results_text: 'No products found'
      });
    }
    editor.on('component:selected', function (component) {
      var woo = componentAncestor(component, 'st-pricing-woo-cart');
      $('#st-pricing-woo-settings').prop('hidden', !woo);
      if (!woo || !wooCommerceActive) { return; }
      var attrs = woo.getAttributes();
      var lines = parseWooLines(attrs['data-st-products']);
      $('#st-pricing-woo-products').val(Object.keys(lines)).trigger('chosen:updated');
      $('#st-pricing-woo-coupon').val(attrs['data-st-coupon'] || '');
      $('#st-pricing-woo-redirect').val(attrs['data-st-redirect-url'] || '');
      $('#st-pricing-woo-redirect-target').val(attrs['data-st-redirect-target'] === '_blank' ? '_blank' : '_self');
      var button = componentHasClass(woo, 'st-pricing-woo-cart-button') ? woo : woo.find('.st-pricing-woo-cart-button')[0];
      var buttonText = componentText(button, 'Add package to cart');
      setComponentText(button, buttonText);
      $('#st-pricing-woo-button-text').val(buttonText);
      renderWooQuantities(lines);
    });
    $('#st-pricing-woo-products').on('change', function () { renderWooQuantities(parseWooLines(selectedWooComponent() ? selectedWooComponent().getAttributes()['data-st-products'] : '')); syncWooComponent(); });
    $('#st-pricing-woo-quantities').on('input change', 'input', syncWooComponent);
    $('#st-pricing-woo-coupon,#st-pricing-woo-button-text,#st-pricing-woo-redirect,#st-pricing-woo-redirect-target').on('input change', syncWooComponent);
    window.setTimeout(function () { normalizeWooButtonComponents(); normalizeFeatureComponents(); }, 0);

    function selectedCountdownComponent() { return componentAncestor(editor.getSelected(), 'st-pricing-countdown'); }
    function syncCountdownMode() {
      var duration = $('#st-pricing-countdown-mode').val() === 'duration';
      $('#st-pricing-countdown-duration-fields').prop('hidden', !duration);
      $('#st-pricing-countdown-target-wrap').prop('hidden', duration);
    }
    function syncCountdownComponent() {
      var component = selectedCountdownComponent();
      if (!component) { return; }
      var multiplier = parseInt($('#st-pricing-countdown-duration-unit').val(), 10) || 1;
      var duration = Math.max(1, parseInt($('#st-pricing-countdown-duration').val(), 10) || 1) * multiplier;
      var attrs = $.extend({}, component.getAttributes(), {
        'data-st-countdown-mode': $('#st-pricing-countdown-mode').val(),
        'data-st-countdown-duration': duration,
        'data-st-countdown-target': $('#st-pricing-countdown-target').val(),
        'data-st-countdown-label': $('#st-pricing-countdown-label').val().trim(),
        'data-st-countdown-expired': $('#st-pricing-countdown-expired').val().trim()
      });
      component.setAttributes(attrs);
      var label = component.find('.st-pricing-countdown-label')[0];
      if (label) { label.components(attrs['data-st-countdown-label'] || 'Offer ends in'); }
      if (attrs['data-st-countdown-mode'] === 'duration') {
        var preview = { days: Math.floor(duration / 86400), hours: Math.floor((duration % 86400) / 3600), minutes: Math.floor((duration % 3600) / 60), seconds: duration % 60 };
        Object.keys(preview).forEach(function (unitName) {
          var unitComponent = component.find('[data-st-countdown-unit="' + unitName + '"]')[0];
          if (unitComponent) { unitComponent.components(String(preview[unitName]).padStart(2, '0')); }
        });
      }
      syncCountdownMode();
    }
    editor.on('component:selected', function (component) {
      var timer = componentAncestor(component, 'st-pricing-countdown');
      $('#st-pricing-countdown-settings').prop('hidden', !timer);
      if (!timer) { return; }
      var attrs = timer.getAttributes();
      var seconds = Math.max(1, parseInt(attrs['data-st-countdown-duration'], 10) || 600);
      var unit = seconds % 86400 === 0 ? 86400 : (seconds % 3600 === 0 ? 3600 : (seconds % 60 === 0 ? 60 : 1));
      $('#st-pricing-countdown-mode').val(attrs['data-st-countdown-mode'] === 'date' ? 'date' : 'duration');
      $('#st-pricing-countdown-duration-unit').val(String(unit));
      $('#st-pricing-countdown-duration').val(seconds / unit);
      $('#st-pricing-countdown-target').val(attrs['data-st-countdown-target'] || '');
      $('#st-pricing-countdown-label').val(attrs['data-st-countdown-label'] || 'Offer ends in');
      $('#st-pricing-countdown-expired').val(attrs['data-st-countdown-expired'] || 'Offer ended');
      syncCountdownMode();
    });
    $('#st-pricing-countdown-mode,#st-pricing-countdown-duration-unit,#st-pricing-countdown-target').on('change', syncCountdownComponent);
    $('#st-pricing-countdown-duration,#st-pricing-countdown-label,#st-pricing-countdown-expired').on('input change', syncCountdownComponent);
    editor.on('component:selected', function (component) {
      var element = component && component.getEl ? component.getEl() : null;
      var isImage = element && element.tagName === 'IMG';
      $('#st-pricing-media').prop({ hidden: true, disabled: true });
      $('#st-pricing-image-size-wrap').prop('hidden', !isImage);
      $('#st-pricing-icon-library').prop({ hidden: true, disabled: true });
      if (isImage) { loadSelectedImageSizes(component); }
    });
    var imageMediaOpening = false;
    function normalizeMediaUrl(url) {
      if (!url) { return ''; }
      try {
        var parsed = new URL(url, window.location.href);
        if (parsed.host === window.location.host) { parsed.protocol = window.location.protocol; }
        return parsed.toString();
      } catch (error) {
        return url;
      }
    }
    function populateImageSizes(image, selectedSize) {
      imageSizes = $.extend({}, image.sizes || {});
      imageSizes.full = { url: image.url, width: image.width, height: image.height };
      var $size = $('#st-pricing-image-size').empty();
      Object.keys(imageSizes).forEach(function (key) {
        var size = imageSizes[key];
        size.url = normalizeMediaUrl(size.url);
        $size.append($('<option>').val(key).text(key + (size.width ? ' (' + size.width + '×' + size.height + ')' : '')));
      });
      $size.val(imageSizes[selectedSize] ? selectedSize : 'full');
    }
    function applyImageSource(component, image, sizeName) {
      sizeName = image.sizes && image.sizes[sizeName] ? sizeName : 'full';
      var size = sizeName === 'full' ? { url: image.url, width: image.width, height: image.height } : image.sizes[sizeName];
      var url = normalizeMediaUrl(size.url || image.url);
      var attrs = $.extend({}, component.getAttributes(), {
        src: url,
        alt: image.alt || component.getAttributes().alt || '',
        'data-attachment-id': image.id || '',
        'data-attachment-size': sizeName
      });
      if (size.width) { attrs.width = size.width; } else { delete attrs.width; }
      if (size.height) { attrs.height = size.height; } else { delete attrs.height; }

      // GrapesJS keeps the image source in a model property as well as in the
      // attributes collection. Updating attributes alone looks correct in the
      // canvas, but getHtml() serializes the old placeholder from this property.
      component.set('src', url);
      component.setAttributes(attrs);
    }
    function loadSelectedImageSizes(component) {
      var attrs = component.getAttributes();
      var attachmentId = parseInt(attrs['data-attachment-id'], 10);
      if (!attachmentId || !window.wp || !wp.media || !wp.media.attachment) {
        imageSizes = {};
        $('#st-pricing-image-size').empty();
        return;
      }
      var attachment = wp.media.attachment(attachmentId);
      var update = function () {
        var image = attachment.toJSON();
        if (!image || !image.url) { return; }
        populateImageSizes(image, attrs['data-attachment-size'] || 'full');
      };
      if (attachment.get('url')) { update(); } else { attachment.fetch().done(update); }
    }
    function openMediaLibrary() {
      var selected = editor.getSelected();
      var element = selected && selected.getEl ? selected.getEl() : null;
      if (!selected || !element || element.tagName !== 'IMG' || !window.wp || !wp.media || imageMediaOpening) { return; }
      imageMediaOpening = true;
      var media = wp.media({ title: 'Choose plan image', button: { text: 'Use image' }, library: { type: 'image' }, multiple: false });
      media.on('select', function () {
        var image = media.state().get('selection').first().toJSON();
        populateImageSizes(image, 'full');
        applyImageSource(selected, image, 'full');
      });
      media.on('close', function () { imageMediaOpening = false; });
      media.open();
    }
    $('#st-pricing-media').on('click', openMediaLibrary);
    $('#st-pricing-image-size').on('change', function () {
      var selected = editor.getSelected();
      var size = imageSizes[this.value];
      if (selected && size) {
        var attrs = $.extend({}, selected.getAttributes(), {
          src: normalizeMediaUrl(size.url),
          width: size.width || '',
          height: size.height || '',
          'data-attachment-size': this.value
        });
        selected.set('src', attrs.src);
        selected.setAttributes(attrs);
      }
    });
    editor.on('run:open-assets:before', function (options) {
      options.abort = 1;
      editor.Modal.close();
      openMediaLibrary();
    });
    function renderIcons(query) {
      query = (query || '').toLowerCase();
      var matches = iconLibrary.filter(function (icon) { return !query || icon.indexOf(query) !== -1; }).slice(0, 350);
      $('#st-pricing-icon-grid').html(matches.map(function (icon) { return '<button type="button" data-icon="' + icon + '" title="' + icon + '"><i class="fa fa-' + icon + '" aria-hidden="true"></i></button>'; }).join(''));
    }
    function closeIconLibrary() { $('#st-pricing-icon-dialog').prop('hidden', true); }
    function openIconLibrary() {
      var selected = editor.getSelected();
      var element = selected && selected.getEl ? selected.getEl() : null;
      if (!element || !(element.matches('.st-pricing-icon') || element.classList.contains('fa'))) { return; }
      renderIcons('');
      $('#st-pricing-icon-dialog').prop('hidden', false);
      $('#st-pricing-icon-search').val('').trigger('focus');
    }
    $('#st-pricing-icon-library').on('click', openIconLibrary);
    $('#st-pricing-icon-search').on('input', function () { renderIcons(this.value); });
    $('#st-pricing-icon-cancel').on('click', closeIconLibrary);
    $('#st-pricing-icon-dialog').on('click', function (event) { if (event.target === this) { closeIconLibrary(); } });
    $('#st-pricing-icon-grid').on('click', 'button', function () {
      var selected = editor.getSelected();
      if (!selected) { return; }
      var attrs = selected.getAttributes();
      attrs.class = (attrs.class || '').split(/\s+/).filter(function (name) { return name && name.indexOf('fa-') !== 0 && name !== 'fa'; }).concat(['st-pricing-icon', 'fa', 'fa-' + $(this).data('icon')]).join(' ');
      attrs['aria-hidden'] = 'true';
      selected.setAttributes(attrs);
      selected.components('');
      closeIconLibrary();
    });

    function isVideoComponent(component) {
      if (!component) { return false; }
      var element = component.getEl ? component.getEl() : null;
      var tagName = String(component.get('tagName') || (element && element.tagName) || '').toLowerCase();
      return component.get('type') === 'video' || tagName === 'video' || tagName === 'iframe';
    }
    function selectedVideoUrl(component) {
      var attrs = component && component.getAttributes ? component.getAttributes() : {};
      var provider = component && component.get ? component.get('provider') : '';
      var id = component && component.get ? component.get('videoId') : '';
      if ((provider === 'yt' || provider === 'ytnc') && id) { return 'https://www.youtube.com/watch?v=' + id; }
      if (provider === 'vi' && id) { return 'https://vimeo.com/' + id; }
      return (component && component.get && component.get('src')) || attrs.src || '';
    }
    function openVideoDialog() {
      var selected = editor.getSelected();
      if (!isVideoComponent(selected)) { return; }
      $('#st-pricing-video-url').val(selectedVideoUrl(selected));
      $('#st-pricing-video-dialog').prop('hidden', false);
      window.setTimeout(function () { $('#st-pricing-video-url').trigger('focus'); }, 0);
    }
    function closeVideoDialog() { $('#st-pricing-video-dialog').prop('hidden', true); }
    function youtubeVideoId(url) {
      var match = String(url || '').match(/(?:youtu\.be\/|youtube(?:-nocookie)?\.com\/(?:watch\?(?:[^#]*&)?v=|embed\/|shorts\/))([\w-]{6,})/i);
      return match ? match[1] : '';
    }
    function vimeoVideoId(url) {
      var match = String(url || '').match(/vimeo\.com\/(?:video\/)?(\d+)/i);
      return match ? match[1] : '';
    }
    function applyVideoUrl(url) {
      var selected = editor.getSelected();
      if (!isVideoComponent(selected) || !url) { return; }
      var type = selected.get('type');
      var ytId = youtubeVideoId(url);
      var viId = vimeoVideoId(url);
      if (type === 'video') {
        if (ytId) {
          selected.set('provider', /youtube-nocookie\.com/i.test(url) ? 'ytnc' : 'yt');
          selected.set('videoId', ytId);
        } else if (viId) {
          selected.set('provider', 'vi');
          selected.set('videoId', viId);
        } else {
          selected.set('provider', 'so');
          selected.set('src', url);
        }
      } else {
        var source = ytId ? 'https://www.youtube.com/embed/' + ytId : (viId ? 'https://player.vimeo.com/video/' + viId : url);
        selected.setAttributes($.extend({}, selected.getAttributes(), { src: source, loading: 'lazy', allowfullscreen: 'allowfullscreen' }));
      }
      selected.addStyle({ display: 'inline-block', width: '100%', height: 'auto', 'min-height': '0', 'aspect-ratio': '16 / 9', 'object-fit': 'contain' });
      status.text('Video source applied');
    }
    $('#st-pricing-video-cancel').on('click', closeVideoDialog);
    $('#st-pricing-video-dialog').on('click', function (event) { if (event.target === this) { closeVideoDialog(); } });
    $('#st-pricing-video-apply').on('click', function () {
      applyVideoUrl($('#st-pricing-video-url').val().trim());
      closeVideoDialog();
    });
    $('#st-pricing-video-url').on('keydown', function (event) {
      if (event.key === 'Enter') { event.preventDefault(); $('#st-pricing-video-apply').trigger('click'); }
    });
    $('#st-pricing-video-media').on('click', function () {
      if (!window.wp || !wp.media || !isVideoComponent(editor.getSelected())) { return; }
      var media = wp.media({ title: 'Choose pricing video', button: { text: 'Use video' }, library: { type: 'video' }, multiple: false });
      media.on('select', function () {
        var video = media.state().get('selection').first().toJSON();
        $('#st-pricing-video-url').val(video.url || '');
        applyVideoUrl(video.url || '');
        closeVideoDialog();
      });
      media.open();
    });

    function isTextComponent(component) {
      if (!component) { return false; }
      var tagName = String(component.get('tagName') || '').toLowerCase();
      return ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'strong', 'small', 'del', 'li', 'a', 'button', 'label'].indexOf(tagName) !== -1;
    }
    function isImageComponent(component) {
      var element = component && component.getEl ? component.getEl() : null;
      return !!(element && element.tagName === 'IMG');
    }
    function isIconComponent(component) {
      var element = component && component.getEl ? component.getEl() : null;
      return !!(element && element.nodeType === 1 && element.matches && element.classList && (element.matches('.st-pricing-icon') || element.classList.contains('fa')));
    }
    function isBadgeComponent(component) {
      return componentHasClass(component, 'st-pricing-badge');
    }
    function isContentContainer(component) {
      return ['st-pricing-header', 'st-pricing-price', 'st-pricing-footer'].some(function (className) {
        return componentHasClass(component, className);
      });
    }
    function toolbarItem(action, icon, title, command, label) {
      return {
        attributes: { class: icon ? 'fa ' + icon : '', title: title, 'data-st-pricing-action': action },
        command: command || function () {},
        label: label || ''
      };
    }
    function toolbarTextValues(component) {
      var element = component && component.getEl ? component.getEl() : null;
      var elementWindow = element && element.ownerDocument ? element.ownerDocument.defaultView : window;
      var computed = element ? elementWindow.getComputedStyle(element) : {};
      var style = component && component.getStyle ? component.getStyle() : {};
      var fontSize = String(style['font-size'] || '').trim();
      var fontSizeMatch = fontSize.match(/^([0-9]*\.?[0-9]+)\s*(px|em|rem|%|vw|vh|vmin|vmax|pt)$/i);
      var size = fontSizeMatch ? parseFloat(fontSizeMatch[1]) : (parseFloat(computed.fontSize) || 16);
      var unit = fontSizeMatch ? fontSizeMatch[2].toLowerCase() : 'px';
      var color = componentColorValue(component, 'color', '#1f2937');
      return { size: Math.round(size * 1000) / 1000, unit: unit, color: color };
    }
    function componentColorValue(component, property, fallback) {
      var element = component && component.getEl ? component.getEl() : null;
      var elementWindow = element && element.ownerDocument ? element.ownerDocument.defaultView : window;
      var computed = element ? elementWindow.getComputedStyle(element) : null;
      var style = component && component.getStyle ? component.getStyle() : {};
      var value = String(style[property] || (computed && computed.getPropertyValue(property)) || '').trim();
      return value && value !== 'initial' && value !== 'inherit' ? value : fallback;
    }
    function normalizedAlignment(value, allowed, fallback) {
      value = String(value || '').trim().toLowerCase();
      return allowed.indexOf(value) !== -1 ? value : fallback;
    }
    function flexAlignment(value) {
      return ({ 'flex-start': 'left', center: 'center', 'flex-end': 'right', 'space-between': 'justify', stretch: 'stretch', baseline: 'baseline' })[String(value || '').trim()] || '';
    }
    function componentAlignmentValues(component) {
      var attrs = component && component.getAttributes ? component.getAttributes() : {};
      var style = component && component.getStyle ? component.getStyle() : {};
      var element = component && component.getEl ? component.getEl() : null;
      var computed = element && element.ownerDocument ? element.ownerDocument.defaultView.getComputedStyle(element) : null;
      var horizontalAllowed = ['left', 'center', 'right', 'justify'];
      var verticalAllowed = ['top', 'middle', 'bottom', 'baseline', 'stretch'];
      var horizontal = normalizedAlignment(attrs['data-horizontal-align'], horizontalAllowed, '');
      var vertical = normalizedAlignment(attrs['data-vertical-align'], verticalAllowed, '');
      var isBadge = isBadgeComponent(component);
      var isText = isTextComponent(component) && !isIconComponent(component) && !isBadge;
      var isMedia = isImageComponent(component) || isIconComponent(component) || isVideoComponent(component);
      var isContainer = isContentContainer(component);
      var hasExplicitVerticalStyle = !!String(style['align-items'] || style['vertical-align'] || style['align-self'] || '').trim();
      if (vertical === 'baseline' && (isText || isMedia) && !hasExplicitVerticalStyle) {
        // Older builds wrote the browser's implicit baseline into data-* on
        // load. It was never a user choice and must migrate to the default.
        vertical = '';
      }
      if (!horizontal && isContainer) {
        horizontal = flexAlignment(componentHasClass(component, 'st-pricing-price') ? (style['justify-content'] || (computed && computed.justifyContent)) : (style['align-items'] || (computed && computed.alignItems)));
      }
      if (!horizontal && isBadge && computed) {
        horizontal = String(computed.left || '').toLowerCase() !== 'auto' ? 'left' : 'right';
      }
      if (!horizontal && isText) {
        horizontal = normalizedAlignment(style['text-align'] || (computed && computed.textAlign), horizontalAllowed, '');
        if (!horizontal && computed && computed.textAlign === 'start') { horizontal = 'left'; }
        if (!horizontal && computed && computed.textAlign === 'end') { horizontal = 'right'; }
      }
      if (!horizontal && isMedia && computed) {
        var marginLeft = String(style['margin-left'] || computed.marginLeft || '');
        var marginRight = String(style['margin-right'] || computed.marginRight || '');
        if (marginLeft === 'auto' && marginRight === 'auto') { horizontal = 'center'; }
        else if (marginLeft === 'auto') { horizontal = 'right'; }
        else if (String(style.width || computed.width) === '100%') { horizontal = 'justify'; }
        else { horizontal = 'left'; }
      }
      if (!vertical && isContainer) {
        var containerVertical = componentHasClass(component, 'st-pricing-price') ? (style['align-items'] || (computed && computed.alignItems)) : (style['justify-content'] || (computed && computed.justifyContent));
        vertical = ({ 'flex-start': 'top', center: 'middle', 'flex-end': 'bottom', baseline: 'baseline', stretch: 'stretch' })[String(containerVertical || '').trim()] || '';
      }
      if (!vertical && isBadge && computed) {
        vertical = String(computed.bottom || '').toLowerCase() !== 'auto' ? 'bottom' : 'top';
      }
      if (!vertical && (isText || isMedia)) {
        // Browser defaults such as `vertical-align: baseline` do not describe a
        // user choice. Only restore an explicit component style; otherwise the
        // editor's documented default is middle.
        var alignItems = String(style['align-items'] || '').trim();
        var verticalAlign = String(style['vertical-align'] || '').trim();
        vertical = ({ 'flex-start': 'top', center: 'middle', 'flex-end': 'bottom', baseline: 'baseline', stretch: 'stretch' })[alignItems] ||
          normalizedAlignment(verticalAlign, verticalAllowed, '');
      }
      return { horizontal: normalizedAlignment(horizontal, horizontalAllowed, 'center'), vertical: normalizedAlignment(vertical, verticalAllowed, 'middle') };
    }
    function normalizeAlignmentComponents() {
      var wrapper = editor.getWrapper ? editor.getWrapper() : null;
      function walk(component) {
        if (!component) { return; }
        var eligible = (isTextComponent(component) && !isIconComponent(component)) || isImageComponent(component) || isIconComponent(component) || isVideoComponent(component) || isContentContainer(component);
        if (eligible) {
          var attrs = $.extend({}, component.getAttributes ? component.getAttributes() : {});
          var values = componentAlignmentValues(component);
          var changed = false;
          if (String(attrs['data-horizontal-align'] || '').toLowerCase() !== values.horizontal) { attrs['data-horizontal-align'] = values.horizontal; changed = true; }
          if (String(attrs['data-vertical-align'] || '').toLowerCase() !== values.vertical) { attrs['data-vertical-align'] = values.vertical; changed = true; }
          if (changed) { component.setAttributes(attrs); }
        }
        var children = component.components ? component.components() : null;
        if (children && children.each) { children.each(walk); }
      }
      walk(wrapper);
    }
    function colorAttribute(value) {
      return $('<span>').text(String(value || '')).html().replace(/"/g, '&quot;');
    }
    function quickColorToolbar(action, property, value, title) {
      return toolbarItem(action, '', title, null,
        '<input type="text" class="st-pricing-quick-color-picker" data-color-property="' + property + '" value="' + colorAttribute(value) + '" aria-label="' + title + '">');
    }
    function supportsQuickBackground(component) {
      return ['st-pricing-header', 'st-pricing-price', 'st-pricing-footer', 'st-pricing-button', 'st-pricing-icon', 'st-pricing-badge'].some(function (className) {
        return componentHasClass(component, className);
      });
    }
    function spectrumColorValue(color) {
      if (!color) { return 'transparent'; }
      return color.getAlpha && color.getAlpha() < 1 ? color.toRgbString() : color.toHexString();
    }
    function destroyQuickColorPickers() {
      var grapesQuery = grapesjs && grapesjs.$;
      $root.find('.st-pricing-quick-color-picker').each(function () {
        if (grapesQuery && grapesQuery.fn && typeof grapesQuery.fn.spectrum === 'function') { grapesQuery(this).spectrum('destroy'); }
      });
      $('.st-pricing-quick-spectrum').remove();
    }
    function initQuickColorPickers(component) {
      var grapesQuery = grapesjs && grapesjs.$;
      if (!grapesQuery || !grapesQuery.fn || typeof grapesQuery.fn.spectrum !== 'function' || editor.getSelected() !== component) { return; }
      $root.find('#st-pricing-grapes .st-pricing-quick-color-picker').each(function () {
        if (this.getAttribute('data-spectrum-bound') === '1') { return; }
        var pickerElement = this;
        var $picker = $(pickerElement);
        var property = $picker.attr('data-color-property');
        var title = property === 'background-color' ? 'Background' : 'Text color';
        var applyColor = function (color) {
          var selected = editor.getSelected();
          if (!selected) { return; }
          var value = spectrumColorValue(color);
          var styles = {};
          styles[property] = value;
          selected.addStyle(styles);
          status.text(title + ': ' + value);
        };
        grapesQuery(pickerElement).spectrum({
          appendTo: 'body',
          containerClassName: 'gjs-one-bg gjs-two-color st-pricing-quick-spectrum',
          replacerClassName: 'st-pricing-quick-color-replacer ' + (property === 'background-color' ? 'is-background' : 'is-text'),
          allowEmpty: true,
          showAlpha: true,
          showInput: true,
          showPalette: true,
          preferredFormat: 'rgb',
          chooseText: 'Apply',
          cancelText: '×',
          maxSelectionSize: 8,
          palette: [
            ['#ffffff', '#f8fafc', '#cbd5e1', '#64748b', '#223146', '#0f172a'],
            ['#ef4444', '#f97316', '#f59e0b', '#22c55e', '#06b6d4', '#3477e5'],
            ['#6366f1', '#8b5cf6', '#d946ef', '#ec4899', '#111827', 'transparent']
          ],
          move: applyColor,
          change: applyColor
        });
        pickerElement.setAttribute('data-spectrum-bound', '1');
      });
    }
    function fontSizeInUnit(pixelSize, unit, element) {
      var elementDocument = element && element.ownerDocument ? element.ownerDocument : document;
      var elementWindow = elementDocument.defaultView || window;
      var parent = element && element.parentElement ? element.parentElement : elementDocument.body;
      var parentSize = parent ? parseFloat(elementWindow.getComputedStyle(parent).fontSize) || 16 : 16;
      var rootSize = parseFloat(elementWindow.getComputedStyle(elementDocument.documentElement).fontSize) || 16;
      var viewportWidth = elementWindow.innerWidth || elementDocument.documentElement.clientWidth || 1;
      var viewportHeight = elementWindow.innerHeight || elementDocument.documentElement.clientHeight || 1;
      var value = pixelSize;
      if (unit === 'em') { value = pixelSize / parentSize; }
      if (unit === 'rem') { value = pixelSize / rootSize; }
      if (unit === '%') { value = (pixelSize / parentSize) * 100; }
      if (unit === 'vw') { value = (pixelSize / viewportWidth) * 100; }
      if (unit === 'vh') { value = (pixelSize / viewportHeight) * 100; }
      if (unit === 'vmin') { value = (pixelSize / Math.min(viewportWidth, viewportHeight)) * 100; }
      if (unit === 'vmax') { value = (pixelSize / Math.max(viewportWidth, viewportHeight)) * 100; }
      if (unit === 'pt') { value = pixelSize * .75; }
      return Math.max(.1, Math.round(value * 1000) / 1000);
    }
    function alignSelectedHorizontal() {
      var selected = editor.getSelected();
      if (selected) { selected.addStyle({ display: 'block', 'margin-left': 'auto', 'margin-right': 'auto' }); status.text('Centered horizontally'); }
    }
    function alignSelectedVertical() {
      var selected = editor.getSelected();
      if (selected) { selected.addStyle({ display: 'inline-block', 'vertical-align': 'middle' }); status.text('Aligned vertically'); }
    }
    function centerSelectedText() {
      var selected = editor.getSelected();
      if (selected) { selected.addStyle({ 'text-align': 'center' }); status.text('Text centered'); }
    }
    function centerSelectedTextVertically() {
      var selected = editor.getSelected();
      if (!selected) { return; }
      var tagName = String(selected.get('tagName') || '').toLowerCase();
      selected.addStyle({ display: ['span', 'strong', 'small', 'a'].indexOf(tagName) !== -1 ? 'inline-flex' : 'flex', 'align-items': 'center' });
      status.text('Text centered vertically');
    }
    function bindQuickToolbarControls(component) {
      if (editor.getSelected() !== component) { return; }
      var values = toolbarTextValues(component);
      var alignment = componentAlignmentValues(component);
      $root.find('.st-pricing-quick-size-input').val(values.size);
      $root.find('.st-pricing-quick-size-unit').val(values.unit);
      $root.find('.st-pricing-quick-align-option').each(function () {
        var isActive = $(this).data('align-value') === alignment[$(this).data('align-axis')];
        $(this).toggleClass('is-active', isActive).attr('aria-pressed', isActive ? 'true' : 'false');
      });
      initQuickColorPickers(component);
    }
    function restoreQuickPopover($popover) {
      var $origin = $popover.data('stPricingOrigin');
      $popover.prop('hidden', true).removeClass('is-floating').removeAttr('style');
      if ($origin && $origin.length && $.contains(document, $origin[0])) { $popover.appendTo($origin); }
    }
    function floatQuickPopover($button, $popover) {
      $popover.data('stPricingOrigin', $popover.parent()).prop('hidden', false).addClass('is-floating').appendTo($root);
      var buttonRect = $button[0].getBoundingClientRect();
      var width = $popover.outerWidth();
      var height = $popover.outerHeight();
      var left = buttonRect.left + (buttonRect.width / 2);
      left = Math.max((width / 2) + 8, Math.min(window.innerWidth - (width / 2) - 8, left));
      var top = buttonRect.top - height - 9;
      if (top < 8) { top = buttonRect.bottom + 9; }
      $popover.css({ top: top + 'px', left: left + 'px' });
    }
    function closeQuickSizeToolbar() {
      $root.find('#st-pricing-grapes .st-pricing-quick-size').attr('aria-expanded', 'false');
      $root.find('.st-pricing-quick-size-popover').each(function () { restoreQuickPopover($(this)); });
    }
    function iconScaleValue(component) {
      var attrs = component && component.getAttributes ? component.getAttributes() : {};
      var scale = parseInt(attrs['data-icon-scale'], 10);
      if (scale >= 1 && scale <= 5) { return scale; }
      var element = component && component.getEl ? component.getEl() : null;
      var elementWindow = element && element.ownerDocument ? element.ownerDocument.defaultView : window;
      var parent = element && element.parentElement ? element.parentElement : null;
      var iconSize = element ? parseFloat(elementWindow.getComputedStyle(element).fontSize) : 0;
      var parentSize = parent ? parseFloat(elementWindow.getComputedStyle(parent).fontSize) : 0;
      return Math.max(1, Math.min(5, Math.round(iconSize / (parentSize || iconSize || 16)) || 1));
    }
    function closeQuickIconSizeToolbar() {
      $root.find('#st-pricing-grapes .st-pricing-quick-icon-size').attr('aria-expanded', 'false');
      $root.find('.st-pricing-quick-icon-size-popover').each(function () { restoreQuickPopover($(this)); });
    }
    function closeQuickAlignmentToolbars() {
      $root.find('#st-pricing-grapes .st-pricing-quick-align-trigger').attr('aria-expanded', 'false');
      $root.find('.st-pricing-quick-align-popover').each(function () { restoreQuickPopover($(this)); });
    }
    function applyQuickIconSize(scale) {
      var selected = editor.getSelected();
      if (!selected || !isIconComponent(selected)) { return; }
      scale = Math.max(1, Math.min(5, parseInt(scale, 10) || 1));
      var attrs = $.extend({}, selected.getAttributes(), { 'data-icon-scale': scale });
      selected.setAttributes(attrs);
      selected.addStyle({ 'font-size': scale + 'em' });
      $root.find('.st-pricing-quick-icon-size-option').removeClass('is-active').attr('aria-pressed', 'false').filter('[data-icon-scale="' + scale + '"]').addClass('is-active').attr('aria-pressed', 'true');
      status.text('Icon size: ×' + scale);
    }
    function applyMediaHorizontalAlignment(alignment) {
      var selected = editor.getSelected();
      if (!selected) { return; }
      var isBadge = isBadgeComponent(selected);
      var isText = isTextComponent(selected) && !isIconComponent(selected) && !isBadge;
      var isMedia = isImageComponent(selected) || isIconComponent(selected) || isVideoComponent(selected);
      var isContainer = isContentContainer(selected);
      if (!isBadge && !isText && !isMedia && !isContainer) { return; }
      if (isBadge) {
        alignment = ['left', 'center', 'right'].indexOf(alignment) !== -1 ? alignment : 'center';
        ['left', 'right', 'top', 'bottom', 'width', 'transform', 'margin-left', 'margin-right', 'align-self', 'vertical-align'].forEach(function (property) {
          if (selected.removeStyle) { selected.removeStyle(property); }
        });
        selected.setAttributes($.extend({}, selected.getAttributes(), { 'data-horizontal-align': alignment }));
        selected.addStyle({ position: 'absolute', 'text-align': 'center' });
        status.text('Badge horizontal position: ' + alignment);
        return;
      }
      var styles;
      if (isContainer) {
        var horizontalValue = { left: 'flex-start', center: 'center', right: 'flex-end', justify: 'space-between' };
        styles = {
          display: 'flex', 'flex-direction': componentHasClass(selected, 'st-pricing-price') ? 'row' : 'column',
          'justify-content': componentHasClass(selected, 'st-pricing-price') ? (horizontalValue[alignment] || 'center') : '',
          'align-items': componentHasClass(selected, 'st-pricing-price') ? '' : (horizontalValue[alignment] || 'center'),
          'text-align': alignment === 'justify' ? 'justify' : alignment
        };
      } else if (isText) {
        styles = { 'text-align': alignment };
      } else {
        styles = { display: isIconComponent(selected) ? 'flex' : 'block', width: '', 'margin-left': '0', 'margin-right': 'auto' };
        if (isIconComponent(selected)) { styles['align-items'] = 'center'; styles['justify-content'] = 'center'; styles['line-height'] = '1'; }
        if (alignment === 'center') { styles['margin-left'] = 'auto'; styles['margin-right'] = 'auto'; }
        if (alignment === 'right') { styles['margin-left'] = 'auto'; styles['margin-right'] = '0'; }
        if (alignment === 'justify') { styles.width = '100%'; styles['margin-left'] = '0'; styles['margin-right'] = '0'; }
      }
      selected.setAttributes($.extend({}, selected.getAttributes(), { 'data-horizontal-align': alignment }));
      selected.addStyle(styles);
      status.text('Horizontal position: ' + alignment);
    }
    function applyMediaVerticalAlignment(alignment) {
      var selected = editor.getSelected();
      if (!selected) { return; }
      var isBadge = isBadgeComponent(selected);
      var isText = isTextComponent(selected) && !isIconComponent(selected) && !isBadge;
      var isMedia = isImageComponent(selected) || isIconComponent(selected) || isVideoComponent(selected);
      var isContainer = isContentContainer(selected);
      if (!isBadge && !isText && !isMedia && !isContainer) { return; }
      if (isBadge) {
        alignment = ['top', 'middle', 'bottom'].indexOf(alignment) !== -1 ? alignment : 'middle';
        ['left', 'right', 'top', 'bottom', 'width', 'transform', 'margin-left', 'margin-right', 'align-self', 'vertical-align'].forEach(function (property) {
          if (selected.removeStyle) { selected.removeStyle(property); }
        });
        selected.setAttributes($.extend({}, selected.getAttributes(), { 'data-vertical-align': alignment }));
        selected.addStyle({ position: 'absolute', 'text-align': 'center' });
        status.text('Badge vertical position: ' + alignment);
        return;
      }
      var verticalAlign = { top: 'top', middle: 'middle', bottom: 'bottom', baseline: 'baseline', stretch: 'middle' };
      var alignmentValue = { top: 'flex-start', middle: 'center', bottom: 'flex-end', baseline: 'baseline', stretch: 'stretch' };
      selected.setAttributes($.extend({}, selected.getAttributes(), { 'data-vertical-align': alignment }));
      if (isContainer) {
        var isPrice = componentHasClass(selected, 'st-pricing-price');
        var verticalValue = alignmentValue[alignment] || 'center';
        var containerStyle = { display: 'flex', 'flex-direction': isPrice ? 'row' : 'column' };
        containerStyle[isPrice ? 'align-items' : 'justify-content'] = verticalValue;
        selected.addStyle(containerStyle);
      } else if (isText) {
        var tagName = String(selected.get('tagName') || '').toLowerCase();
        var inlineText = ['span', 'strong', 'small', 'a', 'label'].indexOf(tagName) !== -1;
        selected.addStyle({ display: inlineText ? 'inline-flex' : 'flex', 'align-items': alignmentValue[alignment] || 'center', 'vertical-align': verticalAlign[alignment] || 'middle' });
      } else {
        selected.addStyle({ 'vertical-align': verticalAlign[alignment] || 'middle', 'align-self': alignmentValue[alignment] || 'center' });
      }
      status.text('Vertical position: ' + alignment);
    }
    function mediaAlignmentToolbar(component) {
      var alignment = componentAlignmentValues(component);
      var horizontal = alignment.horizontal;
      var vertical = alignment.vertical;
      var badge = isBadgeComponent(component);
      var horizontalOptions = badge ? [
        ['left', 'fa-align-left', 'Left'], ['center', 'fa-align-center', 'Center'], ['right', 'fa-align-right', 'Right']
      ] : [
        ['left', 'fa-align-left', 'Left'], ['center', 'fa-align-center', 'Center'],
        ['right', 'fa-align-right', 'Right'], ['justify', 'fa-align-justify', 'Justify']
      ];
      var verticalOptions = badge ? [
        ['top', 'fa-long-arrow-up', 'Top'], ['middle', 'fa-arrows-v', 'Middle'], ['bottom', 'fa-long-arrow-down', 'Bottom']
      ] : [
        ['top', 'fa-long-arrow-up', 'Top'], ['middle', 'fa-arrows-v', 'Middle'],
        ['bottom', 'fa-long-arrow-down', 'Bottom'], ['baseline', 'fa-font', 'Baseline'],
        ['stretch', 'fa-expand', 'Stretch']
      ];
      function alignmentOptions(options, current, axis) {
        return options.map(function (option) {
          return '<button type="button" class="st-pricing-quick-align-option' + (option[0] === current ? ' is-active' : '') + '" data-align-axis="' + axis + '" data-align-value="' + option[0] + '" aria-pressed="' + (option[0] === current ? 'true' : 'false') + '"><i class="fa ' + option[1] + '" aria-hidden="true"></i><span>' + option[2] + '</span></button>';
        }).join('');
      }
      var horizontalToolbar = '<button type="button" class="st-pricing-quick-align-trigger st-pricing-quick-horizontal-align" aria-label="Horizontal position" aria-haspopup="true" aria-expanded="false"><i class="fa fa-align-center" aria-hidden="true"></i></button>' +
        '<div class="st-pricing-quick-align-popover st-pricing-horizontal-align-popover" role="dialog" aria-label="Horizontal position" hidden><span>Horizontal position</span><div>' + alignmentOptions(horizontalOptions, horizontal, 'horizontal') + '</div></div>';
      var verticalToolbar = '<button type="button" class="st-pricing-quick-align-trigger st-pricing-quick-vertical-align" aria-label="Vertical position" aria-haspopup="true" aria-expanded="false"><i class="fa fa-arrows-v" aria-hidden="true"></i></button>' +
        '<div class="st-pricing-quick-align-popover st-pricing-vertical-align-popover" role="dialog" aria-label="Vertical position" hidden><span>Vertical position</span><div>' + alignmentOptions(verticalOptions, vertical, 'vertical') + '</div></div>';
      return [
        toolbarItem('media-horizontal', '', 'Horizontal position', null, horizontalToolbar),
        toolbarItem('media-vertical', '', 'Vertical position', null, verticalToolbar)
      ];
    }
    function applyQuickFontSize(value, unit) {
      var selected = editor.getSelected();
      if (!selected || !isTextComponent(selected)) { return; }
      value = Math.max(.1, Math.min(1000, parseFloat(value) || 16));
      value = Math.round(value * 1000) / 1000;
      selected.addStyle({ 'font-size': value + unit });
      status.text('Text size: ' + value + unit);
    }
    $root.on('click', '#st-pricing-grapes .st-pricing-quick-size', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      var $button = $(this);
      var $popover = $button.siblings('.st-pricing-quick-size-popover');
      var willOpen = $button.attr('aria-expanded') !== 'true';
      closeQuickSizeToolbar();
      closeQuickIconSizeToolbar();
      closeQuickAlignmentToolbars();
      if (willOpen && $popover.length) {
        $button.attr('aria-expanded', 'true');
        floatQuickPopover($button, $popover);
        window.setTimeout(function () { $popover.find('.st-pricing-quick-size-input').trigger('focus').trigger('select'); }, 0);
      }
    });
    $root.on('click', '.st-pricing-quick-size-popover, #st-pricing-grapes .st-pricing-quick-color-picker, #st-pricing-grapes .st-pricing-quick-color-replacer', function (event) { event.stopPropagation(); });
    $root.on('click', '#st-pricing-grapes .st-pricing-quick-icon-size', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      var $button = $(this);
      var $popover = $button.siblings('.st-pricing-quick-icon-size-popover');
      var willOpen = $button.attr('aria-expanded') !== 'true';
      closeQuickSizeToolbar();
      closeQuickIconSizeToolbar();
      closeQuickAlignmentToolbars();
      if (willOpen && $popover.length) {
        $button.attr('aria-expanded', 'true');
        floatQuickPopover($button, $popover);
      }
    });
    $root.on('click', '.st-pricing-quick-icon-size-popover', function (event) { event.stopPropagation(); });
    $root.on('click', '.st-pricing-quick-icon-size-option', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      applyQuickIconSize($(this).data('icon-scale'));
      closeQuickIconSizeToolbar();
    });
    $root.on('click', '#st-pricing-grapes .st-pricing-quick-align-trigger', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      var $button = $(this);
      var $popover = $button.siblings('.st-pricing-quick-align-popover');
      var willOpen = $button.attr('aria-expanded') !== 'true';
      closeQuickSizeToolbar();
      closeQuickIconSizeToolbar();
      closeQuickAlignmentToolbars();
      if (willOpen && $popover.length) {
        $button.attr('aria-expanded', 'true');
        floatQuickPopover($button, $popover);
      }
    });
    $root.on('click', '.st-pricing-quick-align-popover', function (event) { event.stopPropagation(); });
    $root.on('click', '.st-pricing-quick-align-option', function (event) {
      event.preventDefault();
      event.stopImmediatePropagation();
      var axis = $(this).data('align-axis');
      var alignment = $(this).data('align-value');
      if (axis === 'horizontal') { applyMediaHorizontalAlignment(alignment); }
      if (axis === 'vertical') { applyMediaVerticalAlignment(alignment); }
      bindQuickToolbarControls(editor.getSelected());
      closeQuickAlignmentToolbars();
    });
    $root.on('click', function (event) {
      if (!$(event.target).closest('.st-pricing-quick-size, .st-pricing-quick-size-popover').length) { closeQuickSizeToolbar(); }
      if (!$(event.target).closest('.st-pricing-quick-icon-size, .st-pricing-quick-icon-size-popover').length) { closeQuickIconSizeToolbar(); }
      if (!$(event.target).closest('.st-pricing-quick-align-trigger, .st-pricing-quick-align-popover').length) { closeQuickAlignmentToolbars(); }
    });
    $root.on('input change', '.st-pricing-quick-size-input', function (event) {
      event.stopPropagation();
      applyQuickFontSize(this.value, $(this).closest('.st-pricing-quick-size-popover').find('.st-pricing-quick-size-unit').val() || 'px');
    });
    $root.on('change', '.st-pricing-quick-size-unit', function (event) {
      event.stopPropagation();
      var selected = editor.getSelected();
      var element = selected && selected.getEl ? selected.getEl() : null;
      if (!selected || !element || !isTextComponent(selected)) { return; }
      var elementWindow = element.ownerDocument.defaultView || window;
      var pixelSize = parseFloat(elementWindow.getComputedStyle(element).fontSize) || 16;
      var value = fontSizeInUnit(pixelSize, this.value, element);
      var $input = $(this).closest('.st-pricing-quick-size-popover').find('.st-pricing-quick-size-input');
      $input.val(value);
      applyQuickFontSize(value, this.value);
    });
    function setContextToolbar(component) {
      if (!component) { return; }
      destroyQuickColorPickers();
      closeQuickSizeToolbar();
      closeQuickIconSizeToolbar();
      closeQuickAlignmentToolbars();
      var toolbar = (component.get('toolbar') || []).filter(function (item) {
        return !(item && item.attributes && item.attributes['data-st-pricing-action']);
      });
      var custom = [];
      if (isIconComponent(component)) {
        component.addStyle({ display: 'inline-flex', 'align-items': 'center', 'justify-content': 'center', 'line-height': '1', 'text-align': 'center' });
        var iconScale = iconScaleValue(component);
        var iconSizeOptions = [1, 2, 3, 4, 5].map(function (scale) { return '<button type="button" class="st-pricing-quick-icon-size-option' + (scale === iconScale ? ' is-active' : '') + '" data-icon-scale="' + scale + '" aria-pressed="' + (scale === iconScale ? 'true' : 'false') + '">×' + scale + '</button>'; }).join('');
        var iconSizeToolbar = '<button type="button" class="st-pricing-quick-icon-size" aria-label="Icon size" aria-haspopup="true" aria-expanded="false"><i class="fa fa-text-height" aria-hidden="true"></i></button>' +
          '<div class="st-pricing-quick-icon-size-popover" role="dialog" aria-label="Icon size" hidden><span>Icon size</span><div>' + iconSizeOptions + '</div></div>';
        custom.push(toolbarItem('icon', 'fa-smile-o', 'Choose icon', openIconLibrary));
        custom.push(toolbarItem('icon-size', '', 'Icon size', null, iconSizeToolbar));
        custom.push(quickColorToolbar('icon-color', 'color', componentColorValue(component, 'color', '#ffffff'), 'Icon color'));
        custom.push(quickColorToolbar('background-color', 'background-color', componentColorValue(component, 'background-color', 'transparent'), 'Background'));
        custom = custom.concat(mediaAlignmentToolbar(component));
      } else if (isTextComponent(component)) {
        var textValues = toolbarTextValues(component);
        var sizeUnits = ['px', 'em', 'rem', '%', 'vw', 'vh', 'vmin', 'vmax', 'pt'];
        var sizeOptions = sizeUnits.map(function (unit) { return '<option value="' + unit + '"' + (unit === textValues.unit ? ' selected' : '') + '>' + unit + '</option>'; }).join('');
        var sizeToolbar = '<button type="button" class="st-pricing-quick-size" aria-label="Font size" aria-haspopup="true" aria-expanded="false"><span class="st-pricing-font-size-icon" aria-hidden="true">A</span></button>' +
          '<div class="st-pricing-quick-size-popover" role="dialog" aria-label="Text size" hidden><label><span>Size</span><input class="st-pricing-quick-size-input" type="number" min="0.1" max="1000" step="0.1" value="' + textValues.size + '" aria-label="Text size value"></label><label><span>Unit</span><select class="st-pricing-quick-size-unit" aria-label="Text size unit">' + sizeOptions + '</select></label></div>';
        custom.push(toolbarItem('font-size', '', 'Font size', null, sizeToolbar));
        custom.push(quickColorToolbar('font-color', 'color', textValues.color, 'Text color'));
        if (supportsQuickBackground(component)) {
          custom.push(quickColorToolbar('background-color', 'background-color', componentColorValue(component, 'background-color', 'transparent'), 'Background'));
        }
        custom = custom.concat(mediaAlignmentToolbar(component));
      } else if (isImageComponent(component)) {
        component.addStyle({ display: 'inline-block' });
        custom.push(toolbarItem('image', 'fa-image', 'Choose image from WordPress Media Library', openMediaLibrary));
        custom = custom.concat(mediaAlignmentToolbar(component));
      } else if (isVideoComponent(component)) {
        component.addStyle({ display: 'inline-block' });
        custom.push(toolbarItem('video', 'fa-film', 'Choose video source', openVideoDialog));
        custom = custom.concat(mediaAlignmentToolbar(component));
      } else if (isContentContainer(component)) {
        custom.push(quickColorToolbar('background-color', 'background-color', componentColorValue(component, 'background-color', 'transparent'), 'Background'));
        custom = custom.concat(mediaAlignmentToolbar(component));
      } else if (supportsQuickBackground(component)) {
        custom.push(quickColorToolbar('background-color', 'background-color', componentColorValue(component, 'background-color', 'transparent'), 'Background'));
      }
      component.set('toolbar', toolbar.concat(custom));
      window.setTimeout(function () { bindQuickToolbarControls(component); }, 80);
    }
    $('#st-pricing-monthly, #st-pricing-yearly').on('change', function () {
      var selected = editor.getSelected();
      if (!selected) { return; }
      var attrs = selected.getAttributes();
      attrs['data-price-monthly'] = $('#st-pricing-monthly').val();
      attrs['data-price-yearly'] = $('#st-pricing-yearly').val();
      selected.setAttributes(attrs);
      selected.components($('#st-pricing-monthly').val());
    });

    function saveActiveVariant() {
      if (activeToggleKey) {
        toggleVariants[activeToggleKey] = editor.getHtml();
        toggleVariantStyles[activeToggleKey] = getPricingCss();
      }
    }
    function renderToggleOptions() {
      if (!$('#st-pricing-toggle-options').length) { return; }
      var defaultKey = builder.toggleDefault || toggleOptions[0].key;
      $('#st-pricing-toggle-options').html(toggleOptions.map(function (option) {
        return '<div class="st-pricing-toggle-option' + (option.key === activeToggleKey ? ' is-editing' : '') + '" data-key="' + option.key + '"><input type="radio" name="st-pricing-toggle-default" value="' + option.key + '"' + (option.key === defaultKey ? ' checked' : '') + ' title="Default option"><input type="text" value="' + $('<div>').text(option.label).html() + '" aria-label="Option name"><button type="button" class="st-pricing-edit-toggle" title="Edit this version"><i class="fa fa-pencil"></i></button><button type="button" class="st-pricing-remove-toggle" title="Remove option"><i class="fa fa-times"></i></button></div>';
      }).join(''));
    }
    if (!toggleVariants[activeToggleKey]) { toggleVariants[activeToggleKey] = builder.html; }
    if (!toggleVariantStyles[activeToggleKey]) { toggleVariantStyles[activeToggleKey] = builder.css || ''; }
    renderToggleOptions();
    $('#st-pricing-toggle-options').on('change', 'input[type=radio]', function () { builder.toggleDefault = this.value; });
    $('#st-pricing-toggle-options').on('change', 'input[type=text]', function () {
      var key = $(this).closest('.st-pricing-toggle-option').data('key');
      var option = toggleOptions.filter(function (item) { return item.key === key; })[0];
      if (option && this.value.trim()) { option.label = this.value.trim(); }
    });
    $('#st-pricing-toggle-options').on('click', '.st-pricing-edit-toggle', function () {
      var key = $(this).closest('.st-pricing-toggle-option').data('key');
      if (key === activeToggleKey) { return; }
      saveActiveVariant();
      if (!toggleVariants[key]) { toggleVariants[key] = toggleVariants[activeToggleKey] || editor.getHtml(); }
      if (typeof toggleVariantStyles[key] !== 'string') { toggleVariantStyles[key] = toggleVariantStyles[activeToggleKey] || getPricingCss(); }
      activeToggleKey = key;
      loadPricingComponents(toggleVariants[key]);
      editor.setStyle(normalizePricingCss(toggleVariantStyles[key] || ''));
      setTemplate(currentTemplate);
      applySectionSettings();
      renderToggleOptions();
      status.text('Editing pricing option: ' + key);
    });
    $('#st-pricing-toggle-options').on('click', '.st-pricing-remove-toggle', function () {
      if (toggleOptions.length <= 2) { status.text('Keep at least two pricing options.'); return; }
      var key = $(this).closest('.st-pricing-toggle-option').data('key');
      toggleOptions = toggleOptions.filter(function (item) { return item.key !== key; });
      delete toggleVariants[key];
      delete toggleVariantStyles[key];
      if (activeToggleKey === key) {
        activeToggleKey = toggleOptions[0].key;
        loadPricingComponents(toggleVariants[activeToggleKey] || builder.html);
        editor.setStyle(normalizePricingCss(toggleVariantStyles[activeToggleKey] || builder.css || ''));
      }
      if (builder.toggleDefault === key) { builder.toggleDefault = toggleOptions[0].key; }
      renderToggleOptions();
    });
    $('#st-pricing-add-toggle-option').on('click', function () {
      saveActiveVariant();
      var key = 'option-' + Date.now().toString(36);
      toggleOptions.push({ key: key, label: 'New option' });
      toggleVariants[key] = toggleVariants[activeToggleKey] || editor.getHtml();
      toggleVariantStyles[key] = toggleVariantStyles[activeToggleKey] || getPricingCss();
      renderToggleOptions();
    });

    $('#st-pricing-apply-html').on('click', function () {
      loadPricingComponents(sourceValue('html'));
      setTemplate(currentTemplate);
      sourceDirty.html = false;
      setSourceValue('html', formatSource('html', editor.getHtml()));
      status.text('HTML applied');
    });
    $('#st-pricing-apply-css').on('click', function () {
      editor.setStyle(normalizePricingCss(sourceValue('css')));
      sourceDirty.css = false;
      setSourceValue('css', formatSource('css', getPricingCss()));
      status.text('CSS applied');
    });
    function ensureSourceApplied() {
      if (!sourceDirty.html && !sourceDirty.css) { return true; }
      var pending = sourceDirty.html ? 'html' : 'css';
      setSourceMode(pending);
      status.text('Apply ' + pending.toUpperCase() + ' before continuing.');
      return false;
    }
    $('#st-pricing-preview').on('click', function () {
      if (editor.Commands.isActive('preview')) {
        editor.stopCommand('preview');
      } else {
        editor.runCommand('preview');
      }
      $(this).text(editor.Commands.isActive('preview') ? 'Close preview' : 'Preview');
    });
    $('#st-pricing-clone').on('click', function () {
      if (!ensureSourceApplied()) { return; }
      $('#st-pricing-clone-title-input').val($root.find('.st-pricing-toolbar h2').text().trim() + ' copy');
      $('#st-pricing-clone-error').text('');
      $('#st-pricing-clone-dialog').prop('hidden', false);
      $('#st-pricing-clone-title-input').trigger('focus').trigger('select');
    });
    function closeCloneDialog() {
      $('#st-pricing-clone-dialog').prop('hidden', true);
      $('#st-pricing-clone').trigger('focus');
    }
    $('#st-pricing-clone-cancel').on('click', closeCloneDialog);
    $('#st-pricing-clone-dialog').on('click', function (event) {
      if (event.target === this) { closeCloneDialog(); }
    });
    $(document).on('keydown.stPricingClone', function (event) {
      if (event.key === 'Escape' && !$('#st-pricing-clone-dialog').prop('hidden')) { closeCloneDialog(); }
      if (event.key === 'Escape' && !$('#st-pricing-icon-dialog').prop('hidden')) { closeIconLibrary(); }
    });
    $('#st-pricing-clone-form').on('submit', function (event) {
      event.preventDefault();
      var title = $('#st-pricing-clone-title-input').val().trim();
      if (!title) { $('#st-pricing-clone-error').text('Enter a table name.'); return; }
      var $submit = $('#st-pricing-clone-submit').prop('disabled', true);
      $('#st-pricing-clone-error').text('Creating copy…');
      window.supsystic.Tables.request(
        { module: 'tables', action: 'cloneTable', nonce: nonce },
        { id: tableId, title: title }
      ).done(function (response) {
        var url = new URL(window.location.href);
        url.searchParams.set('id', response.id);
        window.location.href = url.toString();
      }).fail(function (message) {
        $('#st-pricing-clone-error').text(message || 'Could not clone the pricing table');
      }).always(function () {
        $submit.prop('disabled', false);
      });
    });
    function collectBuilder() {
      saveActiveVariant();
      var defaultOption = $('#st-pricing-toggle-options input[name=st-pricing-toggle-default]:checked').val() || builder.toggleDefault || toggleOptions[0].key;
      return {
        template: currentTemplate,
        html: toggleVariants[defaultOption] || editor.getHtml(),
        css: normalizePricingCss(toggleVariantStyles[defaultOption] || getPricingCss()),
        fontFamily: $('#st-pricing-table-font').val().trim(),
        fonts: usedFonts,
        maxWidth: normalizeMaxWidth($('#st-pricing-max-width').val(), $('#st-pricing-max-width-unit').val()),
        maxWidthUnit: $('#st-pricing-max-width-unit').val() === 'px' ? 'px' : '%',
        toggle: $('#st-pricing-toggle').is(':checked') ? 1 : 0,
        toggleText: $('#st-pricing-toggle-text').val() || '',
        toggleStyle: $('#st-pricing-toggle-style').val() || 'rounded',
        toggleDefault: defaultOption,
        toggleOptions: toggleOptions,
        toggleVariants: toggleVariants,
        toggleVariantStyles: toggleVariantStyles,
        ajaxToggle: $('#st-pricing-ajax-toggle').is(':checked') ? 1 : 0,
        ajaxToggleText: $('#st-pricing-ajax-toggle-text').val() || 'Show pricing table',
        descriptionColumn: $('#st-pricing-description-column').is(':checked') ? 1 : 0,
        showHeader: $('#st-pricing-header').is(':checked') ? 1 : 0,
        showDescription: $('#st-pricing-description').is(':checked') ? 1 : 0,
        showFooter: $('#st-pricing-footer').is(':checked') ? 1 : 0,
        hoverAnimation: $('#st-pricing-animation').is(':checked') ? 1 : 0
      };
    }
    $('#st-pricing-export').on('click', function () {
      if (!ensureSourceApplied()) { return; }
      var file = new Blob([JSON.stringify(collectBuilder(), null, 2)], { type: 'application/json' });
      var url = URL.createObjectURL(file);
      var link = document.createElement('a');
      link.href = url;
      link.download = 'pricing-table-' + tableId + '.json';
      link.click();
      setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    });
    $('#st-pricing-import').on('click', function () {
      if (ensureSourceApplied()) { $('#st-pricing-import-file').trigger('click'); }
    });
    $('#st-pricing-import-file').on('change', function () {
      var file = this.files && this.files[0];
      if (!file || file.size > 1200000) { status.text('Choose a pricing JSON file under 1.2 MB'); return; }
      var reader = new FileReader();
      reader.onload = function () {
        try {
          var incoming = JSON.parse(reader.result);
          var html = incoming.html || incoming.html_frontend;
          if (typeof html !== 'string' || typeof incoming.css !== 'string') { throw new Error('Invalid pricing file'); }
          loadPricingComponents(html);
          incoming.css = normalizePricingCss(incoming.css);
          editor.setStyle(incoming.css);
          sourceDirty.html = false;
          sourceDirty.css = false;
          var template = incoming.template;
          if (!$('.st-pricing-template[data-template="' + template + '"]').length) { template = 'classic'; }
          setTemplate(template);
          $('#st-pricing-toggle').prop('checked', !!incoming.toggle);
          $('#st-pricing-toggle-text').val(incoming.toggleText || '');
          $('#st-pricing-toggle-style').val(incoming.toggleStyle || 'rounded');
          toggleOptions = Array.isArray(incoming.toggleOptions) && incoming.toggleOptions.length ? incoming.toggleOptions : [{ key: 'monthly', label: 'Monthly' }, { key: 'yearly', label: 'Yearly' }];
          toggleVariants = incoming.toggleVariants && typeof incoming.toggleVariants === 'object' ? incoming.toggleVariants : {};
          toggleVariantStyles = incoming.toggleVariantStyles && typeof incoming.toggleVariantStyles === 'object' ? incoming.toggleVariantStyles : {};
          Object.keys(toggleVariantStyles).forEach(function (key) {
            toggleVariantStyles[key] = normalizePricingCss(toggleVariantStyles[key]);
          });
          builder.toggleDefault = incoming.toggleDefault || toggleOptions[0].key;
          activeToggleKey = builder.toggleDefault;
          if (!toggleVariants[activeToggleKey]) { toggleVariants[activeToggleKey] = html; }
          if (typeof toggleVariantStyles[activeToggleKey] !== 'string') { toggleVariantStyles[activeToggleKey] = incoming.css || ''; }
          renderToggleOptions();
          $('#st-pricing-ajax-toggle').prop('checked', !!incoming.ajaxToggle);
          $('#st-pricing-ajax-toggle-text').val(incoming.ajaxToggleText || 'Show pricing table');
          syncAjaxToggleFields();
          syncPricingSwitcherFields();
          $('#st-pricing-description-column').prop('checked', !!incoming.descriptionColumn);
          $('#st-pricing-header').prop('checked', incoming.showHeader !== false && incoming.showHeader !== 0);
          $('#st-pricing-description').prop('checked', incoming.showDescription !== false && incoming.showDescription !== 0);
          $('#st-pricing-footer').prop('checked', incoming.showFooter !== false && incoming.showFooter !== 0);
          $('#st-pricing-animation').prop('checked', !!incoming.hoverAnimation);
          usedFonts = Array.isArray(incoming.fonts) ? incoming.fonts.slice() : [];
          $('#st-pricing-table-font').val(incoming.fontFamily || '');
          applyTableFont(incoming.fontFamily || '', false);
          applyTableMaxWidth(incoming.maxWidth || 100, incoming.maxWidthUnit || '%', false);
          applySectionSettings();
          status.text('Imported. Save to publish changes.');
        } catch (error) {
          status.text(error.message || 'Could not import pricing file');
        }
      };
      reader.readAsText(file);
      this.value = '';
    });
    $('#st-pricing-save').on('click', function () {
      if (!ensureSourceApplied()) { return; }
      var $button = $(this).prop('disabled', true);
      status.text('Saving…');
      var data = collectBuilder();
      window.supsystic.Tables.request(
        { module: 'pricing', action: 'save', nonce: nonce },
        { id: tableId, builder: data }
      ).done(function (response) {
        status.text(response && response.success === false ? response.message : 'Saved');
      }).fail(function () {
        status.text('Could not save the pricing table');
      }).always(function () {
        $button.prop('disabled', false);
      });
    });
  });
})(window.jQuery);
