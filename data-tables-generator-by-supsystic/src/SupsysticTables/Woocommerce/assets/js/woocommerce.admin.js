(function ($, app) {
  var WOO_DESIGN_PRESETS = {"warm_contrast":{"header_text":"#fff7ed","header_bg":"#7c2d12","header_font_size":"15","header_font_weight":"700","body_text":"#3f3f46","body_bg":"#fffdf8","body_font_size":"14","body_font_weight":"400","input_text":"#451a03","input_bg":"#fff7ed","select_text":"#451a03","select_bg":"#fff7ed","button_text":"#fff7ed","button_bg":"#9a3412","button_font_size":"13","button_font_weight":"700","button_hover_text":"#ffffff","button_hover_bg":"#7c2d12","stripe_text":"#3f3f46","stripe_bg":"#ffedd5","hover_text":"#27272a","hover_bg":"#fed7aa","price_text":"#9a3412","price_bg":"#fff7ed","border_color":"#fdba74"},"mono_slate":{"header_text":"#f8fafc","header_bg":"#334155","header_font_size":"14","header_font_weight":"700","body_text":"#334155","body_bg":"#ffffff","body_font_size":"14","body_font_weight":"400","input_text":"#1e293b","input_bg":"#f8fafc","select_text":"#1e293b","select_bg":"#f8fafc","button_text":"#f8fafc","button_bg":"#0f172a","button_font_size":"13","button_font_weight":"600","button_hover_text":"#ffffff","button_hover_bg":"#334155","stripe_text":"#334155","stripe_bg":"#f8fafc","hover_text":"#0f172a","hover_bg":"#e2e8f0","price_text":"#0f172a","price_bg":"#ffffff","border_color":"#cbd5e1"},"sage_market":{"header_text":"#f6fff8","header_bg":"#3b6b57","header_font_size":"14","header_font_weight":"700","body_text":"#254034","body_bg":"#fbfdf9","body_font_size":"14","body_font_weight":"400","input_text":"#254034","input_bg":"#f6fbf7","select_text":"#254034","select_bg":"#f6fbf7","button_text":"#f6fff8","button_bg":"#4f7c65","button_font_size":"13","button_font_weight":"600","button_hover_text":"#ffffff","button_hover_bg":"#365847","stripe_text":"#254034","stripe_bg":"#eef6f0","hover_text":"#1f3529","hover_bg":"#dbeadf","price_text":"#2f5d46","price_bg":"#fbfdf9","border_color":"#b9d0c0"},"midnight_glow":{"header_text":"#e0f2fe","header_bg":"#1e293b","header_font_size":"15","header_font_weight":"700","body_text":"#e2e8f0","body_bg":"#111827","body_font_size":"14","body_font_weight":"400","input_text":"#e2e8f0","input_bg":"#1f2937","select_text":"#e2e8f0","select_bg":"#1f2937","button_text":"#0f172a","button_bg":"#67e8f9","button_font_size":"13","button_font_weight":"700","button_hover_text":"#082f49","button_hover_bg":"#22d3ee","stripe_text":"#e2e8f0","stripe_bg":"#172033","hover_text":"#f8fafc","hover_bg":"#243447","price_text":"#67e8f9","price_bg":"#111827","border_color":"#334155"},"sandstone_shop":{"header_text":"#fffaf0","header_bg":"#8c5a2b","header_font_size":"14","header_font_weight":"700","body_text":"#5b4636","body_bg":"#fffcf7","body_font_size":"14","body_font_weight":"400","input_text":"#5b4636","input_bg":"#fff7eb","select_text":"#5b4636","select_bg":"#fff7eb","button_text":"#fff8ef","button_bg":"#c2783b","button_font_size":"13","button_font_weight":"700","button_hover_text":"#fff8ef","button_hover_bg":"#9f5d28","stripe_text":"#5b4636","stripe_bg":"#f6ecdf","hover_text":"#493528","hover_bg":"#f2ddc5","price_text":"#9f5d28","price_bg":"#fffcf7","border_color":"#e2c4a4"},"berry_editorial":{"header_text":"#fff1f7","header_bg":"#7a284b","header_font_size":"15","header_font_weight":"700","body_text":"#4b1f31","body_bg":"#fffafc","body_font_size":"14","body_font_weight":"400","input_text":"#4b1f31","input_bg":"#fff2f7","select_text":"#4b1f31","select_bg":"#fff2f7","button_text":"#fff5f8","button_bg":"#c44278","button_font_size":"13","button_font_weight":"700","button_hover_text":"#fff5f8","button_hover_bg":"#a92c5f","stripe_text":"#4b1f31","stripe_bg":"#fde8f0","hover_text":"#3c1827","hover_bg":"#f9d1e0","price_text":"#a92c5f","price_bg":"#fffafc","border_color":"#edb7ca"},"ocean_breeze":{"header_text":"#ecfeff","header_bg":"#0f5f78","header_font_size":"14","header_font_weight":"700","body_text":"#164e63","body_bg":"#f8fdff","body_font_size":"14","body_font_weight":"400","input_text":"#164e63","input_bg":"#f0fbff","select_text":"#164e63","select_bg":"#f0fbff","button_text":"#ecfeff","button_bg":"#0891b2","button_font_size":"13","button_font_weight":"700","button_hover_text":"#ecfeff","button_hover_bg":"#0e7490","stripe_text":"#164e63","stripe_bg":"#e0f7fa","hover_text":"#083344","hover_bg":"#bae6fd","price_text":"#0369a1","price_bg":"#f8fdff","border_color":"#a5d8e6"},"forest_merchant":{"header_text":"#f0fdf4","header_bg":"#14532d","header_font_size":"14","header_font_weight":"700","body_text":"#1f3d2a","body_bg":"#fbfefb","body_font_size":"14","body_font_weight":"400","input_text":"#1f3d2a","input_bg":"#f3fbf4","select_text":"#1f3d2a","select_bg":"#f3fbf4","button_text":"#f0fdf4","button_bg":"#15803d","button_font_size":"13","button_font_weight":"700","button_hover_text":"#f0fdf4","button_hover_bg":"#166534","stripe_text":"#1f3d2a","stripe_bg":"#e7f5ea","hover_text":"#16311f","hover_bg":"#d1fad8","price_text":"#166534","price_bg":"#fbfefb","border_color":"#b7dec0"},"rose_clay":{"header_text":"#fff8f6","header_bg":"#9a5b63","header_font_size":"14","header_font_weight":"700","body_text":"#5c3b41","body_bg":"#fffaf9","body_font_size":"14","body_font_weight":"400","input_text":"#5c3b41","input_bg":"#fff3f0","select_text":"#5c3b41","select_bg":"#fff3f0","button_text":"#fffaf7","button_bg":"#d27d6a","button_font_size":"13","button_font_weight":"700","button_hover_text":"#fffaf7","button_hover_bg":"#b86657","stripe_text":"#5c3b41","stripe_bg":"#f9ece7","hover_text":"#472e33","hover_bg":"#f3d7cf","price_text":"#b86657","price_bg":"#fffaf9","border_color":"#e6c0b8"},"lux_gold":{"header_text":"#fef3c7","header_bg":"#1f2937","header_font_size":"15","header_font_weight":"700","body_text":"#374151","body_bg":"#fffdfa","body_font_size":"14","body_font_weight":"400","input_text":"#374151","input_bg":"#fff9ed","select_text":"#374151","select_bg":"#fff9ed","button_text":"#fffaf0","button_bg":"#c08a1a","button_font_size":"13","button_font_weight":"700","button_hover_text":"#fffaf0","button_hover_bg":"#a16207","stripe_text":"#374151","stripe_bg":"#faf5e6","hover_text":"#1f2937","hover_bg":"#f6e7b7","price_text":"#a16207","price_bg":"#fffdfa","border_color":"#e6cf8b"}};
  var WOO_DESIGN_PRESET_DEFAULTS = {length_color:'#334155',length_font_size:'13',length_font_weight:'400',info_color:'#334155',info_font_size:'13',info_font_weight:'400',description_color:'#334155',description_font_size:'14',description_font_weight:'400',signature_color:'#334155',signature_font_size:'13',signature_font_weight:'400',processing_bg:'#ffffff',processing_color:'#000000',price_font_size:'16',price_font_weight:'600',price_filter_input_text:'#1f2933',price_filter_input_bg:'#ffffff',price_filter_input_font_size:'13',price_filter_input_font_weight:'400',price_filter_track:'#d7dce1',price_filter_fill:'#2271b1',price_filter_thumb:'#2271b1',price_filter_thumb_style:'circle',checkout_button_text:'#2271b1',checkout_button_bg:'#ffffff',checkout_button_font_size:'13',checkout_button_font_weight:'600',checkout_button_hover_text:'#ffffff',checkout_button_hover_bg:'#135e96',reset_button_text:'#334155',reset_button_bg:'#ffffff',reset_button_font_size:'13',reset_button_font_weight:'600',reset_button_hover_text:'#ffffff',reset_button_hover_bg:'#2271b1'};

  $.each(WOO_DESIGN_PRESETS, function (key, preset) {
    WOO_DESIGN_PRESETS[key] = $.extend({}, WOO_DESIGN_PRESET_DEFAULTS, {
      length_color: preset.body_text || WOO_DESIGN_PRESET_DEFAULTS.length_color,
      info_color: preset.body_text || WOO_DESIGN_PRESET_DEFAULTS.info_color,
      description_color: preset.body_text || WOO_DESIGN_PRESET_DEFAULTS.description_color,
      signature_color: preset.body_text || WOO_DESIGN_PRESET_DEFAULTS.signature_color,
      processing_bg: preset.body_bg || WOO_DESIGN_PRESET_DEFAULTS.processing_bg,
      processing_color: preset.button_bg || preset.price_text || preset.body_text || WOO_DESIGN_PRESET_DEFAULTS.processing_color,
      price_font_size: preset.body_font_size || WOO_DESIGN_PRESET_DEFAULTS.price_font_size,
      price_font_weight: preset.button_font_weight || preset.header_font_weight || WOO_DESIGN_PRESET_DEFAULTS.price_font_weight,
      price_filter_input_text: preset.input_text || WOO_DESIGN_PRESET_DEFAULTS.price_filter_input_text,
      price_filter_input_bg: preset.input_bg || WOO_DESIGN_PRESET_DEFAULTS.price_filter_input_bg,
      price_filter_input_font_size: preset.body_font_size || WOO_DESIGN_PRESET_DEFAULTS.price_filter_input_font_size,
      price_filter_input_font_weight: preset.body_font_weight || WOO_DESIGN_PRESET_DEFAULTS.price_filter_input_font_weight,
      price_filter_track: preset.border_color || WOO_DESIGN_PRESET_DEFAULTS.price_filter_track,
      price_filter_fill: preset.button_bg || WOO_DESIGN_PRESET_DEFAULTS.price_filter_fill,
      price_filter_thumb: preset.button_bg || preset.price_text || WOO_DESIGN_PRESET_DEFAULTS.price_filter_thumb,
      price_filter_thumb_style: WOO_DESIGN_PRESET_DEFAULTS.price_filter_thumb_style,
      checkout_button_text: preset.button_bg || WOO_DESIGN_PRESET_DEFAULTS.checkout_button_text,
      checkout_button_bg: preset.body_bg || WOO_DESIGN_PRESET_DEFAULTS.checkout_button_bg,
      checkout_button_font_size: preset.button_font_size || WOO_DESIGN_PRESET_DEFAULTS.checkout_button_font_size,
      checkout_button_font_weight: preset.button_font_weight || WOO_DESIGN_PRESET_DEFAULTS.checkout_button_font_weight,
      checkout_button_hover_text: preset.button_hover_text || WOO_DESIGN_PRESET_DEFAULTS.checkout_button_hover_text,
      checkout_button_hover_bg: preset.button_hover_bg || WOO_DESIGN_PRESET_DEFAULTS.checkout_button_hover_bg,
      reset_button_text: preset.body_text || WOO_DESIGN_PRESET_DEFAULTS.reset_button_text,
      reset_button_bg: preset.body_bg || WOO_DESIGN_PRESET_DEFAULTS.reset_button_bg,
      reset_button_font_size: preset.button_font_size || WOO_DESIGN_PRESET_DEFAULTS.reset_button_font_size,
      reset_button_font_weight: preset.button_font_weight || WOO_DESIGN_PRESET_DEFAULTS.reset_button_font_weight,
      reset_button_hover_text: preset.button_hover_text || WOO_DESIGN_PRESET_DEFAULTS.reset_button_hover_text,
      reset_button_hover_bg: preset.button_bg || WOO_DESIGN_PRESET_DEFAULTS.reset_button_hover_bg,
    }, preset);
  });

  function AdminPage() {
    this.$obj = this;
    this.tableSearchWrap = $('#stSearchContentTbl');
    this.tablePropertiesWrap = $('#stSetPropertiesContent');
    this.tableSearch = '';
    this.tableContent = '';

    return this.$obj;
  }

  AdminPage.prototype.init = function () {
    var _thisObj = this.$obj;
    _thisObj.eventsAdminPage();
    _thisObj.eventsTables();
    _thisObj.eventsProperties();
    _thisObj.loadProductsSearchTbl();
    _thisObj.loadProductsContentTbl();
  };

  AdminPage.prototype.initTable = function (tablename) {
    var _thisObj = this.$obj;

    function buildAdminProcessingLoaderHtml() {
      var iconName = $('input[name="tableLoader[iconName]"]').val() || 'default',
        iconItems = parseInt($('input[name="tableLoader[iconItems]"]').val(), 10),
        processingColor = $('input[name="woocommerce[design][processing_color]"]').val() || $('input[name="tableLoader[color]"]').val() || '#000000',
        processingBg = $('input[name="woocommerce[design][processing_bg]"]').val() || '#ffffff',
        itemsHtml = '',
        i = 0;

      iconItems = isNaN(iconItems) || iconItems < 0 ? 0 : iconItems;
      if (iconName === 'default') {
        return '<div class="stb-processing-loader-wrap" style="background-color:' + processingBg + ';"><div class="supsystic-table-loader spinner" style="background-color:' + processingColor + ';"></div></div>';
      }

      for (i = 0; i < iconItems; i++) {
        itemsHtml += '<div></div>';
      }

      return '<div class="stb-processing-loader-wrap" style="background-color:' + processingBg + ';"><div class="supsystic-table-loader la-' + iconName + ' la-2x" style="color:' + processingColor + ';">' + itemsHtml + '</div></div>';
    }

    function attachAdminProcessingLoader(tableApi) {
      var $processing = $(tableApi.table().container()).find('.dataTables_processing');

      if (!$processing.length) {
        return;
      }

      $processing.html(buildAdminProcessingLoaderHtml());
      $(tableApi.table().node()).on('processing.dt', function () {
        $processing.html(buildAdminProcessingLoaderHtml());
      });
    }

    switch (tablename) {
      case 'tableSearch':
        _thisObj.tableSearch = $('#stSearchTable')
          .css('width', '100%')
          .DataTable({
            serverSide: true,
            processing: true,
            ajax: {
              url: ajaxurl,
              type: 'POST',
              data: function (d) {
                d['route[module]'] = 'woocommerce';
                d['route[action]'] = 'getSearchProducts';
                d['route[nonce]'] = DTGS_NONCE;
                d['action'] = 'supsystic-tables';
                d['show_variations'] = jQuery('#stSearchTable_wrapper .dt-buttons input[name="show_variations"]').is(':checked') ? 1 : 0;
                d['show_private'] = jQuery('#stSearchTable_wrapper .dt-buttons input[name="show_private"]').is(':checked') ? 1 : 0;
              },
              beforeSend: function () {
                jQuery('#stSearchTable').DataTable().column(3).visible(jQuery('#stSearchTable_wrapper .dt-buttons input[name="show_variations"]').is(':checked'));
              },
            },
            dom: 'Bfrtip',
            columnDefs: [
              {
                targets: 'no-sort',
                orderable: false,
              },
            ],
            order: [],
            responsive: true,
            language: {
              processing: '',
              emptyTable: "There\'re no products in the WooCommerce store",
            },
            initComplete: function () {
              attachAdminProcessingLoader(_thisObj.tableSearch);
            },
            fnDrawCallback: function () {
              // Fix of conflict with handsontable library - it triggers error if user makes click on link without href attribute
              jQuery('#stSearchTable_wrapper .paginate_button ').each(function () {
                jQuery(this).attr('href', '#');
                jQuery(this).attr('onclick', 'return false');
              });

              if (jQuery('#stSearchTable_wrapper .dataTables_paginate  span .paginate_button').size() > 1) {
                jQuery('#stSearchTable_wrapper .dataTables_paginate ')[0].style.display = 'block';
              } else {
                jQuery('#stSearchTable_wrapper .dataTables_paginate ')[0].style.display = 'none';
              }
            },
          });
        //Added button here
        if (!jQuery('#stSearchTable_wrapper .dt-buttons').length) {
          var buttons = jQuery('.dt-buttons-add-template').removeClass('dt-buttons-add-template stHidden');
          jQuery('#stSearchTable_wrapper').prepend(buttons);
        }
        jQuery('#stSearchTable_wrapper .dt-buttons input').on('change ifChanged', function (e) {
          _thisObj.tableSearch.ajax.reload();
        });

        break;
      case 'tableContent':
        _thisObj.tableContent = $('#stContentTable').DataTable({
          serverSide: true,
          processing: true,
          ajax: {
            url: ajaxurl,
            type: 'POST',
            data: function (d) {
              d['route[module]'] = 'woocommerce';
              d['route[action]'] = 'getProductContent';
              d['route[nonce]'] = DTGS_NONCE;
              d['action'] = 'supsystic-tables';
              d['tableid'] = app.getParameterByName('id');
              d['current_productids'] = $('input[name="woocommerce[productids]"]').val() || '';
              d['current_exclude_productids'] = $('input[name="woocommerce[exclude_productids]"]').val() || '';
              d['current_auto_categories_enable'] = $('#woocommerce-auto-categories-enable').is(':checked') ? 1 : 0;
              d['current_auto_categories_list'] = $('input[name="woocommerce[auto_categories_list]"]').val() || '';
            },
            dataSrc: function (json) {
              jQuery('#stContentTable').DataTable().column(3).visible(json.variations);
              return json.data;
            },
          },
          dom: 'Bfrtip',
          columnDefs: [
            {
              targets: 'no-sort',
              orderable: false,
            },
          ],
          order: [],
          language: {
            processing: '',
            emptyTable: "There\'re no selected products",
          },
          initComplete: function () {
            attachAdminProcessingLoader(_thisObj.tableContent);
          },
          fnDrawCallback: function () {
            //Added button here
            if (!jQuery('#stContentTable_wrapper .dt-buttons').length) {
              var buttons = jQuery('.dt-buttons-remove-template').clone().removeClass('dt-buttons-remove-template stHidden');
              jQuery('#stContentTable_wrapper').prepend(buttons);
            }
            // Fix of conflict with handsontable library - it triggers error if user makes click on link without href attribute
            jQuery('#stContentTable_wrapper .paginate_button ').each(function () {
              jQuery(this).attr('href', '#');
              jQuery(this).attr('onclick', 'return false');
            });

            if (jQuery('#stContentTable_wrapper .dataTables_paginate  span .paginate_button').size() > 1) {
              jQuery('#stContentTable_wrapper .dataTables_paginate ')[0].style.display = 'block';
            } else {
              jQuery('#stContentTable_wrapper .dataTables_paginate ')[0].style.display = 'none';
            }
          },
        });
        break;
    }
    return;
  };

  AdminPage.prototype.eventsAdminPage = function () {
    var _thisObj = this.$obj,
      wooForm = $('form#woocommerce-settings'),
      advancedAllowed = typeof SDT_DATA !== 'undefined' && !!SDT_DATA.isWooAdvanced;

    function syncWooCheckbox($input, checked) {
      if (!$input.length) {
        return;
      }

      $input.prop('checked', !!checked);

      if ($.fn.iCheck) {
        $input.iCheck(checked ? 'check' : 'uncheck');
        $input.iCheck('update');
      }
    }

    function toggleWooDesignOptions() {
      var enabled = wooForm.find('input[name="woocommerce[design][enabled]"]').is(':checked'),
        rows = wooForm.find('.woo-design-options');

      if (enabled) {
        rows.removeClass('stHidden').stop(true, true).fadeIn();
      } else {
        rows.stop(true, true).fadeOut(function () {
          $(this).addClass('stHidden');
        });
      }
    }

    function toggleWooStockStatusFilterAvailability() {
      var hideOutOfStock = wooForm.find('input[name="woocommerce[hide_out_of_stock]"]').is(':checked'),
        stockFilterToggle = wooForm.find('input[name="woocommerce[filter_stock_status]"]'),
        shouldDisable = hideOutOfStock || !advancedAllowed;

      if (!stockFilterToggle.length) {
        return;
      }

      if (hideOutOfStock) {
        syncWooCheckbox(stockFilterToggle, false);
      }

      stockFilterToggle.prop('disabled', shouldDisable);

      if ($.fn.iCheck) {
        stockFilterToggle.iCheck(shouldDisable ? 'disable' : 'enable');
        stockFilterToggle.iCheck('update');
      }
    }

    function toggleWooSettingsBlock(rows, enabled) {
      rows = $(rows);

      if (enabled) {
        rows.removeClass('stHidden').stop(true, true).fadeIn();
      } else {
        rows.stop(true, true).fadeOut(function () {
          $(this).addClass('stHidden');
        });
      }
    }

    function setWooFieldsDisabled(rows, disabled) {
      $(rows)
        .find('input, select, textarea, button')
        .each(function () {
          var field = $(this);

          field.prop('disabled', !!disabled);

          if ($.fn.iCheck && field.is(':checkbox, :radio')) {
            field.iCheck(disabled ? 'disable' : 'enable');
            field.iCheck('update');
          }
        });
    }

    function toggleWooSortingOptions() {
      var enabled = wooForm.find('input[name="features[ordering]"]').is(':checked'),
        rows = wooForm.find('.woo-sorting-options');

      toggleWooSettingsBlock(rows, enabled);
    }

    function toggleWooSearchingColumnOptions() {
      var enabled = wooForm.find('input[name="features[searching]"]').is(':checked') && wooForm.find('input[name="searching[columnSearch]"]').is(':checked'),
        rows = wooForm.find('.woo-searching-column-options');

      toggleWooSettingsBlock(rows, enabled);
    }

    function toggleWooSearchingResultOptions() {
      var enabled = wooForm.find('input[name="features[searching]"]').is(':checked') && wooForm.find('input[name="searching[resultOnly]"]').is(':checked'),
        rows = wooForm.find('.woo-searching-result-options');

      toggleWooSettingsBlock(rows, enabled);
    }

    function toggleWooSearchingOptions() {
      var enabled = wooForm.find('input[name="features[searching]"]').is(':checked'),
        rows = wooForm.find('.woo-searching-options');

      toggleWooSettingsBlock(rows, enabled);
      toggleWooSearchingColumnOptions();
      toggleWooSearchingResultOptions();
    }

    function toggleWooPaginationOptions() {
      var enabled = wooForm.find('input[name="features[paging]"]').is(':checked'),
        rows = wooForm.find('.woo-pagination-options');

      toggleWooSettingsBlock(rows, enabled);
    }

    function toggleWooHeaderDependentOptions() {
      var enabled = wooForm.find('input[name="elements[head]"]').is(':checked'),
        mainRows = wooForm.find('.woo-header-dependent-main'),
        subRows = wooForm.find('.woo-sorting-options, .woo-searching-options, .woo-searching-column-options, .woo-searching-result-options');

      toggleWooSettingsBlock(mainRows, enabled);
      if (!enabled) {
        toggleWooSettingsBlock(subRows, false);
      } else {
        toggleWooSortingOptions();
        toggleWooSearchingOptions();
      }

      setWooFieldsDisabled(mainRows, !enabled);
      setWooFieldsDisabled(subRows, !enabled);
    }

    function toggleManualProductsSection(forceExpanded) {
      var toggleButton = wooForm.find('.woo-manual-products-toggle'),
        content = wooForm.find('#woo-manual-products-content'),
        expanded = typeof forceExpanded === 'boolean' ? forceExpanded : !toggleButton.hasClass('is-expanded');

      toggleButton.toggleClass('is-expanded', expanded).attr('aria-expanded', expanded ? 'true' : 'false');

      if (expanded) {
        content.stop(true, true).slideDown(180);
      } else {
        content.stop(true, true).slideUp(180);
      }
    }

    function collectWooDesignValues() {
      var designValues = {};

      wooForm.find('[data-design-key]').each(function () {
        var field = $(this),
          key = field.data('design-key');

        if (key) {
          designValues[key] = field.val();
        }
      });

      return designValues;
    }

    function applyWooDesignPreset(presetKey) {
      var preset = WOO_DESIGN_PRESETS[presetKey];

      if (!preset) {
        return;
      }

      $.each(preset, function (key, value) {
        wooForm.find('[data-design-key="' + key + '"]').val(value).trigger('change');
      });

      syncWooCheckbox(wooForm.find('input[name="woocommerce[design][enabled]"]'), true);
      toggleWooDesignOptions();
    }

    $('#chooseColumns option').each(function () {
      var options = $(this);
      if (options.css('display') === 'block') {
        $('#chooseColumns').val(options.val());
        return false;
      }
    });

    $('#stAddButton').prop('disabled', false);
    var i = 0;
    $('#chooseColumns option').each(function () {
      var options = $(this);
      if (options.css('display') === 'block') {
        $('#chooseColumns').val(options.val());
        i++;
        return false;
      }
    });
    if (i === 0) {
      $('#chooseColumns').val('');
      $('#chooseColumns').css('disabled', 'disabled');
      $('#stAddButton').prop('disabled', true);
    }

    jQuery('#woocommerce-enable').on('ifChanged', function () {
      var el = $(this),
        rows = jQuery('.woo-wrapper');

      if (el.is(':checked')) {
        wooForm.find('select[name="woocommerce[thumbnail_size]"], input[name="woocommerce[filter_attribute]"]').trigger('change');

        //if user not set products already, we make default horizontal scroll mode to display table
        if (jQuery("input[name='woocommerce[productids]']").val().length === 0) {
          jQuery('#features-responsive-mode').val('2');
        }
        rows.not('.woo-sub-setting').fadeIn().removeClass('stHidden');
      } else {
        rows.fadeOut();
      }
    });

    wooForm.find('select[name="woocommerce[thumbnail_size]"]').on('change', function () {
      if ($(this).val() == 'set_size') {
        $('.woo-thumb-size').removeClass('stHidden').fadeIn();
      } else {
        $('.woo-thumb-size').fadeOut();
      }
    });
    wooForm.find('input[name="woocommerce[filter_attribute]"]').on('change ifChanged', function () {
      if ($(this).is(':checked')) {
        $('.woo-filter-attributes').removeClass('stHidden').fadeIn();
      } else {
        $('.woo-filter-attributes').fadeOut();
      }
    });
    wooForm.find('input[name="elements[head]"]').on('change ifChanged', function () {
      toggleWooHeaderDependentOptions();
    });
    wooForm.find('input[name="woocommerce[hide_out_of_stock]"]').on('change ifChanged', function () {
      toggleWooStockStatusFilterAvailability();
    });
    wooForm.find('input[name="features[ordering]"]').on('change ifChanged', function () {
      toggleWooSortingOptions();
    });
    wooForm.find('input[name="features[searching]"]').on('change ifChanged', function () {
      toggleWooSearchingOptions();
    });
    wooForm.find('input[name="searching[columnSearch]"]').on('change ifChanged', function () {
      toggleWooSearchingColumnOptions();
    });
    wooForm.find('input[name="searching[resultOnly]"]').on('change ifChanged', function () {
      toggleWooSearchingResultOptions();
    });
    wooForm.find('input[name="features[paging]"]').on('change ifChanged', function () {
      toggleWooPaginationOptions();
    });

    wooForm.find('select[name="woocommerce[filter_attribute_selected][]"]').chosen({ width: '100%' });
    wooForm.find('select[name="features[export][]"]').chosen({ width: '100%' });
    toggleWooStockStatusFilterAvailability();
    toggleWooSortingOptions();
    toggleWooSearchingOptions();
    toggleWooPaginationOptions();
    toggleWooHeaderDependentOptions();

    wooForm.find('input[name="woocommerce[design][enabled]"]').on('change ifChanged', function () {
      toggleWooDesignOptions();
    });

    wooForm.find('.woo-manual-products-toggle').on('click', function (e) {
      e.preventDefault();
      toggleManualProductsSection();
    });

    wooForm.find('#woo-design-preset').on('change', function () {
      if (!advancedAllowed) {
        return;
      }

      applyWooDesignPreset($(this).val());
    });

    toggleWooDesignOptions();
    toggleManualProductsSection(true);

    //Auto Add Products
    var autoSelect = $('#stbAutoCategoriesList'),
      autoInput = $('#stbAutoAddProductsWrapper input[name="woocommerce[auto_categories_list]"'),
      autoEnable = wooForm.find('input[name="woocommerce[auto_categories_enable]"]');

    autoSelect.multipleSelect({
      selectAll: true,
      onClick: function (element) {
        var selected = autoSelect.multipleSelect('getSelects');
        if (element.checked) {
          selected = $.merge(selected, checkSubCategories(element.value, []));
          autoSelect.multipleSelect('setSelects', selected);
        }
        autoInput.val(selected);
      },
      onCheckAll: function () {
        autoInput.val('all');
      },
      onUncheckAll: function () {
        autoInput.val('');
      },
    });
    WOO_DESIGN_PRESETS.user_default = collectWooDesignValues();
    wooForm.find('#woo-design-preset').val('user_default');
    function checkSubCategories(parent, list) {
      autoSelect.find('option[data-parent="' + parent + '"]').each(function () {
        var value = $(this).val();
        list.push(value);
        list = checkSubCategories(value, list);
      });
      return list;
    }
    var autoValue = autoInput.val();
    if (autoValue == 'all') {
      autoSelect.multipleSelect('checkAll');
    } else {
      autoSelect.multipleSelect('setSelects', autoValue.split(','));
    }

    autoEnable
      .on('change ifChanged', function () {
        if (!advancedAllowed) {
          autoSelect.multipleSelect('disable');
          return;
        }
        if ($(this).is(':checked')) {
          autoSelect.multipleSelect('enable');
        } else {
          autoSelect.multipleSelect('disable');
        }
      })
      .trigger('change');

    var excludeSearchInput = wooForm.find('#woocommerce-exclude-product-search'),
      excludeHiddenInput = wooForm.find('#woocommerce-exclude-productids'),
      excludeResults = wooForm.find('.woo-exclude-search-results'),
      excludeList = wooForm.find('.woo-excluded-products-list'),
      excludeSearchTimer = null;

    function escapeHtml(value) {
      return $('<div/>').text(value || '').html();
    }

    function getExcludedIds() {
      var ids = [];

      excludeList.find('.woo-excluded-product-card').each(function () {
        var id = parseInt($(this).attr('data-product-id'), 10);
        if (!isNaN(id) && id > 0) {
          ids.push(id);
        }
      });

      return ids;
    }

    function syncExcludedProducts() {
      var ids = getExcludedIds();

      excludeHiddenInput.val(ids.join(',')).trigger('change');
      excludeList.toggleClass('is-empty', !ids.length);
    }

    function buildExcludedProductCard(item) {
      var variationHtml = item.variation ? '<span class="woo-excluded-product-variation">' + escapeHtml(item.variation) + '</span>' : '',
        metaHtml = item.meta ? '<div class="woo-excluded-product-meta">' + escapeHtml(item.meta) + '</div>' : '';

      return (
        '<div class="woo-excluded-product-card" data-product-id="' +
        escapeHtml(item.id) +
        '" data-title="' +
        escapeHtml(item.title) +
        '" data-variation="' +
        escapeHtml(item.variation || '') +
        '" data-meta="' +
        escapeHtml(item.meta || '') +
        '" data-thumbnail="' +
        escapeHtml(item.thumbnail || '') +
        '">' +
        '<div class="woo-excluded-product-body">' +
        '<div class="woo-excluded-product-summary">' +
        '<span class="woo-excluded-product-title">' +
        escapeHtml(item.title) +
        '</span>' +
        variationHtml +
        '</div>' +
        metaHtml +
        '</div>' +
        '<button type="button" class="button-link-delete woo-excluded-product-remove" aria-label="Remove excluded product">&times;</button>' +
        '</div>'
      );
    }

    function addExcludedProduct(item) {
      var productId = parseInt(item.attr('data-product-id'), 10);

      if (isNaN(productId) || excludeList.find('.woo-excluded-product-card[data-product-id="' + productId + '"]').length) {
        return;
      }

      excludeList.append(
        buildExcludedProductCard({
          id: productId,
          title: item.attr('data-title') || '',
          variation: item.attr('data-variation') || '',
          meta: item.attr('data-meta') || '',
          thumbnail: item.attr('data-thumbnail') || '',
        })
      );

      excludeSearchInput.val('');
      hideExcludeResults();
      syncExcludedProducts();
    }

    function renderExcludeSearchResults(items) {
      var html = '',
        existingIds = getExcludedIds();

      if (!items || !items.length) {
        excludeResults.html('<div class="woo-exclude-search-empty">No matching products found.</div>').removeClass('stHidden');
        return;
      }

      $.each(items, function (index, item) {
        var disabled = existingIds.indexOf(parseInt(item.id, 10)) !== -1,
          thumbHtml = item.thumbnail ? item.thumbnail : '<span class="woo-excluded-product-thumb-placeholder"><i class="fa fa-picture-o" aria-hidden="true"></i></span>',
          variationHtml = item.variation ? '<div class="woo-exclude-search-item-variation">' + escapeHtml(item.variation) + '</div>' : '',
          metaHtml = item.meta ? '<div class="woo-exclude-search-item-meta">' + escapeHtml(item.meta) + '</div>' : '';

        html +=
          '<div class="woo-exclude-search-item" data-product-id="' +
          escapeHtml(item.id) +
          '" data-title="' +
          escapeHtml(item.title) +
          '" data-variation="' +
          escapeHtml(item.variation || '') +
          '" data-meta="' +
          escapeHtml(item.meta || '') +
          '" data-thumbnail="' +
          escapeHtml(item.thumbnail || '') +
          '">' +
          '<div class="woo-exclude-search-item-thumb">' +
          thumbHtml +
          '</div>' +
          '<div class="woo-exclude-search-item-body">' +
          '<div class="woo-exclude-search-item-title">' +
          escapeHtml(item.title) +
          '</div>' +
          variationHtml +
          metaHtml +
          '</div>' +
          '<button type="button" class="button woo-exclude-result-add" ' +
          (disabled ? 'disabled="disabled"' : '') +
          '>' +
          (disabled ? 'Added' : 'Exclude') +
          '</button>' +
          '</div>';
      });

      excludeResults.html(html).removeClass('stHidden');
    }

    function hideExcludeResults() {
      excludeResults.addClass('stHidden').empty();
    }

    function requestExcludeProducts(term) {
      if (!advancedAllowed) {
        hideExcludeResults();
        return;
      }

      app
        .request(
          {
            module: 'woocommerce',
            action: 'searchExcludeProducts',
            nonce: DTGS_NONCE,
          },
          {
            term: term,
            limit: 5,
            exclude_ids: getExcludedIds().join(','),
            show_private: wooForm.find('input[name="woocommerce[show_private]"]').is(':checked') ? 1 : 0,
          }
        )
        .done(function (res) {
          renderExcludeSearchResults(res.items || []);
        })
        .fail(function (error) {
          console.log(error);
          hideExcludeResults();
        });
    }

    excludeSearchInput.on('input', function () {
      var term = $.trim($(this).val());

      clearTimeout(excludeSearchTimer);
      if (!term.length) {
        hideExcludeResults();
        return;
      }

      excludeSearchTimer = setTimeout(function () {
        requestExcludeProducts(term);
      }, 250);
    });

    wooForm.on('click', '.woo-exclude-result-add', function (e) {
      e.preventDefault();
      e.stopPropagation();

      if ($(this).is(':disabled')) {
        return;
      }

      addExcludedProduct($(this).closest('.woo-exclude-search-item'));
    });

    wooForm.on('click', '.woo-exclude-search-item', function (e) {
      if ($(e.target).closest('.woo-exclude-result-add').length) {
        return;
      }

      var addButton = $(this).find('.woo-exclude-result-add');
      if (addButton.length && addButton.is(':disabled')) {
        return;
      }

      addExcludedProduct($(this));
    });

    wooForm.on('click', '.woo-excluded-product-remove', function (e) {
      e.preventDefault();
      $(this).closest('.woo-excluded-product-card').remove();
      syncExcludedProducts();
    });

    $(document).on('click.wooExcludeSearch', function (e) {
      if (!$(e.target).closest('.woo-exclude-search-wrap').length) {
        hideExcludeResults();
      }
    });

    syncExcludedProducts();
    wooForm.find('input, select, textarea').on('change ifChanged', function (e) {
      g_stbIsDataEdited['woocommerce'] = true;
    });
  };

  AdminPage.prototype.eventsTables = function () {
    var _thisObj = this.$obj;
    //click to "check all rows" checkbox
    $('body').on('ifChanged', '.stCheckAll', function () {
      var el = $(this),
        wrapper = el.closest('.dataTable'),
        rows = wrapper.find('tbody tr');

      if (el.is(':checked')) {
        rows.addClass('selected');
        rows.find('input').prop('checked', true);
      } else {
        rows.removeClass('selected');
        rows.find('input').prop('checked', false);
      }
    });

    //click on "check one row" checkbox
    $('body').on('click', '#woocommerce-settings table tbody input[type="checkbox"]', function () {
      var el = $(this);
      if (el.is(':checked')) {
        el.closest('tr').addClass('selected');
      } else {
        el.closest('tr').removeClass('selected');
      }
    });

    $('#woocommerce-settings').on('click', '.stAddProducts', function (e) {
      e.preventDefault();
      g_stbIsDataEdited['woocommerce'] = true;
      var data = _thisObj.tableSearch.rows('.selected').data().toArray();
      var productIdSelected = [];

      data.forEach(function (row, i) {
        row.forEach(function (column, j) {
          //get only id value
          if (j === 0) {
            var html = $.parseHTML(column),
              id = $(html).attr('data-id');
            productIdSelected.push(id);
          }
        });
      });

      $('#stSearchTable').find('.selected').removeClass('selected').find('input[type="checkbox"]').prop('checked', false);

      $('#stSearchTable').find('th input[type="checkbox"]').prop('checked', false).iCheck('update');

      var tableId = app.getParameterByName('id');

      app
        .request(
          {
            module: 'woocommerce',
            action: 'addProducts',
            nonce: DTGS_NONCE,
          },
          {
            productIdSelected: productIdSelected,
            tableid: tableId,
          }
        )
        .done(function (res) {
          if (typeof res.productIds !== 'undefined') {
            jQuery('input[name="woocommerce[productids]"]').val(res.productIds);
          }
          _thisObj.tableContent.draw();
        })
        .fail(function (error) {
          console.log(error);
        });
    });

    jQuery('#woocommerce-settings').on('click', '.stRemoveProducts', function (e) {
      e.preventDefault();
      g_stbIsDataEdited['woocommerce'] = true;
      var data = _thisObj.tableContent.rows('.selected').data().toArray();
      var productIdSelected = [];

      data.forEach(function (row, i) {
        row.forEach(function (column, j) {
          //get only id value
          if (j === 0) {
            var html = $.parseHTML(column),
              id = $(html).attr('data-id');
            productIdSelected.push(id);
          }
        });
      });
      $('#stSearchTable').find('th input[type="checkbox"]').prop('checked', false).iCheck('update');

      var tableId = app.getParameterByName('id');

      app
        .request(
          {
            module: 'woocommerce',
            action: 'removeProducts',
            nonce: DTGS_NONCE,
          },
          {
            productIdSelected: productIdSelected,
            tableid: tableId,
          }
        )
        .done(function (res) {
          if (typeof res.productIds !== 'undefined') {
            jQuery('input[name="woocommerce[productids]"]').val(res.productIds);
          }
          _thisObj.tableContent.draw();
        })
        .fail(function (error) {
          console.log(error);
        });
    });
  };

  AdminPage.prototype.eventsProperties = function () {
    var _thisObj = this.$obj,
      advancedAllowed = typeof SDT_DATA !== 'undefined' && !!SDT_DATA.isWooAdvanced;

    function syncDialogCheckbox($input, checked, disabled) {
      if (!$input || !$input.length) {
        return;
      }

      if (typeof disabled !== 'undefined') {
        $input.prop('disabled', !!disabled);
      }

      $input.prop('checked', !!checked);

      if ($.fn.iCheck && $input.parent().hasClass('icheckbox_minimal')) {
        $input.iCheck(checked ? 'check' : 'uncheck');
        $input.iCheck('update');
      }
    }

    function toggleDialogOption($label, $input, visible) {
      $label.toggleClass('stHidden', !visible);
      $input.toggleClass('stHidden', !visible);

      if ($.fn.iCheck && $input.parent().hasClass('icheckbox_minimal')) {
        $input.parent().toggleClass('stHidden', !visible);
      }
    }

    //make properties sortable
    $('.stPropertiesWrapp').sortable({
      containment: 'parent',
      cursor: 'move',
      stop: _thisObj.saveProperties,
      handle: '.stOptionDragHandler',
    });

    $('body').on('click', '.stPropertiesHideResponsiveLabel', function (e) {
      e.preventDefault();

      var dialogHtml = $(this).closest('.stPropertiesChangeNameWrapp'),
        input = dialogHtml.find('.stColumnHideResponsiveInput').first();

      if (!input.length || input.prop('disabled')) {
        return;
      }

      if ($.fn.iCheck && input.parent().hasClass('icheckbox_minimal')) {
        input.iCheck(input.is(':checked') ? 'uncheck' : 'check');
        input.iCheck('update');
      } else {
        input.prop('checked', !input.is(':checked'));
      }
    });

    $('body').on('click', '.stPropertiesPriceSearchInputsLabel', function (e) {
      e.preventDefault();

      var dialogHtml = $(this).closest('.stPropertiesChangeNameWrapp'),
        input = dialogHtml.find('.stColumnShowPriceSearchInputsInput').first();

      if (!input.length || input.prop('disabled')) {
        return;
      }

      if ($.fn.iCheck && input.parent().hasClass('icheckbox_minimal')) {
        input.iCheck(input.is(':checked') ? 'uncheck' : 'check');
        input.iCheck('update');
      } else {
        input.prop('checked', !input.is(':checked'));
      }
    });

    $('body').on('click', '.stPropertiesHideSearchInputLabel', function (e) {
      e.preventDefault();

      var dialogHtml = $(this).closest('.stPropertiesChangeNameWrapp'),
        input = dialogHtml.find('.stColumnHideSearchInputInput').first();

      if (!input.length || input.prop('disabled')) {
        return;
      }

      if ($.fn.iCheck && input.parent().hasClass('icheckbox_minimal')) {
        input.iCheck(input.is(':checked') ? 'uncheck' : 'check');
        input.iCheck('update');
      } else {
        input.prop('checked', !input.is(':checked'));
      }
    });

    $('body').on('click', '.stPropertiesDisableSortingLabel', function (e) {
      e.preventDefault();

      var dialogHtml = $(this).closest('.stPropertiesChangeNameWrapp'),
        input = dialogHtml.find('.stColumnDisableSortingInput').first();

      if (!input.length || input.prop('disabled')) {
        return;
      }

      if ($.fn.iCheck && input.parent().hasClass('icheckbox_minimal')) {
        input.iCheck(input.is(':checked') ? 'uncheck' : 'check');
        input.iCheck('update');
      } else {
        input.prop('checked', !input.is(':checked'));
      }
    });

      $('body').on('click', '.stOptionEditHandler', function (e) {
      e.preventDefault();
      var el = $(this),
        wrapper = el.closest('.stOptions'),
        columnNameHtml = wrapper.find('.content'),
        columnSlug = wrapper.attr('data-slug') || '',
        isPriceColumn = columnSlug === 'price',
        showColumn = wrapper.attr('data-nice-name-display') === '1' ? true : false,
        originalName = wrapper.attr('data-name'),
        currentDisplayName = $.trim(wrapper.attr('data-nice-name') || ''),
        currentMaxWidth = wrapper.attr('data-max-width') || '',
        currentTextAlign = $.trim(wrapper.attr('data-text-align') || ''),
        currentVerticalAlign = $.trim(wrapper.attr('data-vertical-align') || ''),
        hideColumn = wrapper.attr('data-hide-column') === '1' ? true : false,
        hideResponsiveColumn = wrapper.attr('data-hide-responsive-column') === '1' ? true : false,
        hideSearchInput = wrapper.attr('data-hide-search-input') === '1' ? true : false,
        disableSorting = wrapper.attr('data-disable-sorting') === '1' ? true : false,
        showPriceSearchInputs = isPriceColumn && wrapper.attr('data-show-price-search-inputs') === '1',
        dialogHtml = $('.stPropertiesChangeNameWrapp').removeClass('stHidden'),
        dialogInput = dialogHtml.find('.stColumnTitleInput'),
        dialogMaxWidth = dialogHtml.find('.stColumnMaxWidthInput'),
        dialogTextAlign = dialogHtml.find('.stColumnTextAlignInput'),
        dialogVerticalAlign = dialogHtml.find('.stColumnVerticalAlignInput'),
        dialogCheckbox = dialogHtml.find('.stPropertiesToggleLabel input[type="checkbox"]'),
        dialogHideColumn = dialogHtml.find('.stColumnHideColumnInput'),
        dialogHideResponsiveColumn = dialogHtml.find('.stColumnHideResponsiveInput'),
        dialogHideSearchInput = dialogHtml.find('.stColumnHideSearchInputInput'),
        dialogDisableSorting = dialogHtml.find('.stColumnDisableSortingInput'),
        dialogHideSearchInputLabel = dialogHtml.find('.stPropertiesHideSearchInputLabel'),
        dialogShowPriceSearchInputs = dialogHtml.find('.stColumnShowPriceSearchInputsInput'),
        dialogShowPriceSearchInputsLabel = dialogHtml.find('.stPropertiesPriceSearchInputsLabel');

      dialogInput.attr('placeholder', originalName);
      dialogInput.val(currentDisplayName !== '' ? currentDisplayName : showColumn ? $.trim(columnNameHtml.text()) : '');
      dialogMaxWidth.val(currentMaxWidth);
      dialogMaxWidth.prop('disabled', !advancedAllowed);
      dialogTextAlign.val(currentTextAlign);
      dialogTextAlign.prop('disabled', !advancedAllowed);
      dialogVerticalAlign.val(currentVerticalAlign);
      dialogVerticalAlign.prop('disabled', !advancedAllowed);
      syncDialogCheckbox(dialogCheckbox, showColumn, false);
      syncDialogCheckbox(dialogHideColumn, hideColumn, !advancedAllowed);
      syncDialogCheckbox(dialogHideResponsiveColumn, hideResponsiveColumn, !advancedAllowed);
      syncDialogCheckbox(dialogHideSearchInput, hideSearchInput, !advancedAllowed);
      syncDialogCheckbox(dialogDisableSorting, disableSorting, !advancedAllowed);
      syncDialogCheckbox(dialogShowPriceSearchInputs, showPriceSearchInputs, !advancedAllowed || !isPriceColumn);
      toggleDialogOption(dialogHideSearchInputLabel, dialogHideSearchInput, !isPriceColumn);
      toggleDialogOption(dialogShowPriceSearchInputsLabel, dialogShowPriceSearchInputs, isPriceColumn);

      var $dialog = dialogHtml.dialog({
        width: 360,
        modal: true,
        autoOpen: false,
        title: 'Change column title',
        close: function () {
          $dialog.dialog('close');
        },
        buttons: {
          Change: function (event) {
            var input = $.trim($dialog.find('input[type="text"]').val()),
              columnTitle = input !== '' ? input : originalName,
              checkbox = dialogCheckbox.is(':checked'),
              hideColumnChecked = dialogHideColumn.is(':checked'),
              hideResponsiveColumnChecked = dialogHideResponsiveColumn.is(':checked'),
              hideSearchInputChecked = dialogHideSearchInput.is(':checked'),
              disableSortingChecked = dialogDisableSorting.is(':checked'),
              showPriceSearchInputsChecked = dialogShowPriceSearchInputs.is(':checked'),
              textAlign = advancedAllowed ? $.trim(dialogTextAlign.val()) : '',
              verticalAlign = advancedAllowed ? $.trim(dialogVerticalAlign.val()) : '',
              maxWidthInput = $.trim(dialogMaxWidth.val()),
              parsedMaxWidth = parseInt(maxWidthInput, 10),
              maxWidth = advancedAllowed && !isNaN(parsedMaxWidth) && parsedMaxWidth > 0 ? parsedMaxWidth : '';

            if (checkbox) {
              columnNameHtml.text(columnTitle);
              wrapper.attr('data-nice-name', columnTitle);
              wrapper.attr('data-nice-name-display', '1');
            } else {
              columnNameHtml.text(originalName);
              wrapper.attr('data-nice-name', input);
              wrapper.attr('data-nice-name-display', '0');
            }
            wrapper.attr('data-max-width', maxWidth);
            wrapper.attr('data-hide-column', advancedAllowed && hideColumnChecked ? '1' : '0');
            wrapper.attr('data-hide-responsive-column', advancedAllowed && hideResponsiveColumnChecked ? '1' : '0');
            wrapper.attr('data-hide-search-input', advancedAllowed && !isPriceColumn && hideSearchInputChecked ? '1' : '0');
            wrapper.attr('data-disable-sorting', advancedAllowed && disableSortingChecked ? '1' : '0');
            wrapper.attr('data-text-align', advancedAllowed && $.inArray(textAlign, ['left', 'center', 'right']) !== -1 ? textAlign : '');
            wrapper.attr('data-vertical-align', advancedAllowed && $.inArray(verticalAlign, ['top', 'middle', 'bottom']) !== -1 ? verticalAlign : '');
            wrapper.attr('data-show-price-search-inputs', advancedAllowed && isPriceColumn && showPriceSearchInputsChecked ? '1' : '0');

            _thisObj.saveProperties();

            $dialog.dialog('close');
          },
          Cancel: function () {
            $dialog.dialog('close');
          },
        },
      });

      $dialog.dialog('open');
    });

    $('body').on('click', '.stOptionRemoveHandler', function (e) {
      e.preventDefault();
      $(this).closest('.stOptions').remove();
      _thisObj.saveProperties();
    });

    $('#stAddButton').on('click', function (e) {
      e.preventDefault();
      var selected = $('#chooseColumns').find(':selected'),
        id = selected.attr('value'),
        name = selected.attr('data-name'),
        slug = selected.attr('data-slug'),
        template = $('.stOptionsEmpty').clone(),
        propertiesWrapp = $('.stPropertiesWrapp');

      if (!$('.stPropertiesWrapp .stOptions[data-id="' + id + '"]').length && id) {
        template.removeClass('stOptionsEmpty stHidden');
        template.attr('data-id', id);
        template.attr('data-name', name);
        template.attr('data-max-width', '');
        template.attr('data-hide-column', '0');
        template.attr('data-hide-responsive-column', '0');
        template.attr('data-hide-search-input', '0');
        template.attr('data-disable-sorting', '0');
        template.attr('data-text-align', '');
        template.attr('data-vertical-align', '');
        template.attr('data-show-price-search-inputs', '0');
        template.attr('data-slug', slug);
        template.find('.content').text(name);
        propertiesWrapp.append(template);
        _thisObj.saveProperties();
      }
    });
  };

  AdminPage.prototype.saveProperties = function () {
    var optArr = new Array();
    var optionsWrapper = $('.stPropertiesWrapp .stOptions').not('.stEmptyOptions');
    var advancedAllowed = typeof SDT_DATA !== 'undefined' && !!SDT_DATA.isWooAdvanced;
    //make visible all properties in select "Select and add columns"
    $('#chooseColumns option').css('display', 'block');
    g_stbIsDataEdited['woocommerce'] = true;

    optionsWrapper.each(function (index) {
      var el = $(this);
      var slug = el.attr('data-slug');
      var hideSearchInputAttr = el.attr('data-hide-search-input');
      var disableSortingAttr = el.attr('data-disable-sorting');
      var textAlignAttr = $.trim(el.attr('data-text-align') || '');
      var verticalAlignAttr = $.trim(el.attr('data-vertical-align') || '');
      var showPriceSearchInputsAttr = el.attr('data-show-price-search-inputs');
      var valueToPush = {};
      valueToPush['id'] = el.attr('data-id');
      valueToPush['display_name'] = el.attr('data-nice-name');
      valueToPush['original_name'] = el.attr('data-name');
      valueToPush['show_display_name'] = el.attr('data-nice-name-display');
      valueToPush['max_width'] = advancedAllowed ? el.attr('data-max-width') : '';
      valueToPush['hide_column'] = advancedAllowed && el.attr('data-hide-column') === '1' ? 1 : 0;
      valueToPush['hide_responsive'] = advancedAllowed && el.attr('data-hide-responsive-column') === '1' ? 1 : 0;
      valueToPush['hide_search_input'] = advancedAllowed && slug !== 'price' && hideSearchInputAttr === '1' ? 1 : 0;
      valueToPush['disable_sorting'] = advancedAllowed && disableSortingAttr === '1' ? 1 : 0;
      valueToPush['text_align'] = advancedAllowed && $.inArray(textAlignAttr, ['left', 'center', 'right']) !== -1 ? textAlignAttr : '';
      valueToPush['vertical_align'] = advancedAllowed && $.inArray(verticalAlignAttr, ['top', 'middle', 'bottom']) !== -1 ? verticalAlignAttr : '';
      valueToPush['show_price_search_inputs'] = advancedAllowed && slug === 'price' && showPriceSearchInputsAttr === '1' ? 1 : 0;
      valueToPush['slug'] = slug;
      optArr.push(valueToPush);

      //make selected options hidden in select "Select and add columns"
      $('#chooseColumns option').filter(function () {
        return $(this).attr('data-slug') === el.attr('data-slug');
      }).css('display', 'none');
    });

    $('#stAddButton').prop('disabled', false);
    var i = 0;
    $('#chooseColumns option').each(function () {
      var options = $(this);
      if (options.css('display') === 'block') {
        $('#chooseColumns').val(options.val());
        i++;
        return false;
      }
    });
    if (i === 0) {
      $('#chooseColumns').val('');
      $('#chooseColumns').css('disabled', 'disabled');
      $('#stAddButton').prop('disabled', true);
    }

    var propertiesJson = JSON.stringify(optArr);
    $('input[name="woocommerce[order]"]').val(propertiesJson);

    $(document.body).trigger('changeOrderPosition');
  };

  AdminPage.prototype.loadProductsSearchTbl = function () {
    var _thisObj = this.$obj;
    _thisObj.initTable('tableSearch');
  };

  AdminPage.prototype.loadProductsContentTbl = function () {
    var _thisObj = this.$obj;
    _thisObj.initTable('tableContent');
  };

  $(document).ready(function () {
    var adminPage = new AdminPage();
    adminPage.init();
  });
})(window.jQuery, window.supsystic.Tables);
