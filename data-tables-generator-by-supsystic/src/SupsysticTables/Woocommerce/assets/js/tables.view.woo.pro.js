(function ($, app) {
  $(document).ready(function () {
    app.WooPro = function () {
      app.WooProFrontendEvents();
    };

    app.WooProFrontendEvents = function () {
      //Click on multy add products button
      $('.stAddMultyButton').on('click', function (e) {
        e.preventDefault();

        var _this = $(this),
          wrapper = _this.closest('.supsystic-tables-wrap'),
          rows = wrapper.find('.stMultiAddCheck'),
          selectedProduct = [],
          addonData = wrapper.find('.supsystic-tables-addon'),
          hideViewCart = addonData.length && addonData.attr('data-view-cart-hide') == 'on';

        rows.each(function () {
          var checkbox = $(this).find('.stAddMulty'),
            pushObj = {},
            variation = {};
          checkbox
            .closest('td')
            .find('.stVarAttribute')
            .each(function () {
              variation[$(this).attr('data-attribute')] = $(this).val();
            });

          pushObj.id = checkbox.val();
          pushObj.varId = checkbox.attr('data-variation_id');
          pushObj.quantity = checkbox.attr('data-quantity');
          pushObj.variation = variation;
          selectedProduct.push(pushObj);
        });

        app
          .request(
            {
              module: 'woocommerce',
              action: 'productsAddToCart',
              nonce: typeof DTGS_NONCE !== 'undefined' ? DTGS_NONCE : DTGS_NONCE_FRONTEND,
            },
            {
              selectedProducts: selectedProduct,
            }
          )
          .done(function (res) {
            console.log(res);
            $(document.body).trigger('wc_fragment_refresh');

            rows.each(function () {
              var _this = $(this);
              _this.removeClass('.stMultiAddCheck');
              _this.find('.quantity .qty').val('1').trigger('change');
              _this.find('.stAddMulty').prop('checked', false);
              if (!hideViewCart) {
                _this.find('.added_to_cart').removeClass('stHidden');
              }
            });
            var message = res.message,
              errors = res.errors;
            $.sNotify({
              icon: errors ? false : 'fa fa-check',
              content: '<span>' + message + '</span>',
              delay: errors ? 5000 : 1500,
            });
          })
          .fail(function (error) {
            console.log(error);
          });
        return false;
      });

      //Click on add product button
      $('body').on('click', '.stAddToCart', function (e) {
        e.preventDefault();
        var _this = $(this),
          wrapper = _this.closest('.stAddToCartWrapper'),
          addonData = _this.closest('.supsystic-tables-wrap').find('.supsystic-tables-addon'),
          hideViewCart = addonData.length && addonData.attr('data-view-cart-hide') == 'on',
          selectedProduct = [],
          pushObj = {},
          variation = {};
        _this
          .closest('td')
          .find('.stVarAttribute')
          .each(function () {
            variation[$(this).attr('data-attribute')] = $(this).val();
          });

        pushObj.id = _this.attr('data-product_id');
        pushObj.varId = _this.attr('data-variation_id');
        pushObj.quantity = _this.attr('data-quantity');
        pushObj.variation = variation;
        selectedProduct.push(pushObj);

        app
          .request(
            {
              module: 'woocommerce',
              action: 'productsAddToCart',
              nonce: typeof DTGS_NONCE !== 'undefined' ? DTGS_NONCE : DTGS_NONCE_FRONTEND,
            },
            {
              selectedProducts: selectedProduct,
            }
          )
          .done(function (res) {
            $(document.body).trigger('wc_fragment_refresh');
            if (!hideViewCart) {
              wrapper.find('.added_to_cart').removeClass('stHidden');
            }

            wrapper.find('.quantity .qty').val('1').trigger('change');
            wrapper.find('.stAddMulty').prop('checked', false);

            var message = res.message,
              errors = res.errors;
            $.sNotify({
              icon: errors ? false : 'fa fa-check',
              content: '<span>' + message + '</span>',
              delay: errors ? 5000 : 1500,
            });
          })
          .fail(function (error) {
            console.log(error);
          });
        return false;
      });

      //Change multy checkbox event
      $('.supsystic-tables-wrap').on('change', '.stAddMulty', function () {
        var checkbox = $(this),
          row = checkbox.closest('tr');
        if (row.hasClass('child')) {
          row = row.prev();
        }
        if (checkbox.is(':checked')) {
          row.addClass('stMultiAddCheck');
        } else {
          row.removeClass('stMultiAddCheck');
        }
      });

      //change quantity input event
      $('.supsystic-tables-wrap').on('change', '.quantity .qty', function () {
        var qtyInput = $(this),
          wrapper = qtyInput.closest('.stAddToCartWrapper'),
          row = qtyInput.closest('tr');
        if (row.hasClass('child')) {
          row = row.prev();
          var wrapperMain = row.find('td.add_to_cart');
          wrapperMain.find('.stAddToCartButWrapp .stAddToCart').attr('data-quantity', qtyInput.val());
          wrapperMain.find('.qty').val(qtyInput.val());
          wrapperMain.find('.stAddMulty').attr('data-quantity', qtyInput.val());
        }
        wrapper.find('.stAddToCartButWrapp .stAddToCart').attr('data-quantity', qtyInput.val());
        wrapper.find('.stAddMulty').attr('data-quantity', qtyInput.val());
      });

      $('.supsystic-tables-wrap').on('change', '.stVarAttribute', function () {
        var select = $(this),
          wrapper = select.closest('.stVarAttributes'),
          variations = JSON.parse(wrapper.attr('data-variations')),
          td = select.closest('td'),
          curAttr = select.attr('data-attribute'),
          curValue = select.val(),
          otherAttrs = wrapper.find('select:not([data-attribute="' + curAttr + '"])'),
          multy = td.closest('tr').find('.stAddMulty'),
          attributes = [];

        otherAttrs.each(function () {
          var current = $(this);
          attributes.push({ name: current.attr('data-attribute'), value: current.val() });
          current.find('option:not([value=""])').css('display', 'none');
        });

        td.find('.stVarPrice').addClass('stHidden');
        td.find('.stAddToCart').prop('disabled', true).attr('data-variation_id', 0);
        multy.prop('disabled', true).attr('data-variation_id', 0);
        var attrLen = attributes.length,
          found = false;
        for (id in variations) {
          var attrs = variations[id],
            match = !(curAttr in attrs) || attrs[curAttr] == '' || attrs[curAttr] == curValue;
          if (match || curValue == '') {
            for (var i = 0; i < attrLen; i++) {
              var name = attributes[i]['name'],
                value = attributes[i]['value'];
              if (name in attrs) {
                wrapper.find('select[data-attribute="' + name + '"] option' + (attrs[name] == '' ? '' : '[value="' + attrs[name] + '"]')).css('display', 'block');
                if (attrs[name] != '' && attrs[name] != value) {
                  match = false;
                }
              }
            }
            if (!found && match) {
              td.find('.stVarPrice[data-variation_id="' + id + '"]').removeClass('stHidden');
              td.find('.stAddToCart').prop('disabled', false).attr('data-variation_id', id);
              multy.prop('disabled', false).attr('data-variation_id', id);
              found = true;
            }
          }
        }
        if (multy.length > 0 && multy.prop('disabled')) {
          multy.prop('checked', false);
        }
        otherAttrs.each(function () {
          var current = $(this);
          if (current.find('option:selected').css('display') == 'none') {
            current.val('');
          }
        });
      });
    };

    app.WooProExportPrepare = function ($table, exportTable, params) {
      var addonData = $table.closest('.supsystic-tables-wrap').find('.supsystic-tables-addon');
      if (addonData.length && addonData.attr('data-woocommerce-table') === 'on') {
        exportTable.find('.stbWooExportToggle').remove();
        exportTable.find('.stbWooColumnsToggle').remove();
        exportTable.find('.stbWooCategoryFilter').remove();
        exportTable.find('.stbWooFilterToggle').remove();
        exportTable.find('.stbWooFilterWrapper').remove();
        exportTable.find('.stbResetWooFilters').remove();
        exportTable.find('.stAddMultyButton').remove();
        exportTable.find('.stViewCheckoutButton').remove();

        var buyTds = exportTable.find('.stAddToCartWrapper').closest('td');
        if (buyTds.length) {
          var numColumn = buyTds.eq(0).attr('data-x');
          buyTds.remove();
          exportTable.find('th[data-x="' + numColumn + '"]').remove();
        }
        exportTable.css('font-size', '80%');
        exportTable.find('a').each(function () {
          $(this).replaceWith($(this).text());
        });
        exportTable.find('.screen-reader-text').remove();
        exportTable.find('.woocommerce-Price-currencySymbol[aria-hidden="true"]').removeAttr('aria-hidden');
        exportTable.find('del').css({
          'text-decoration': 'line-through',
          opacity: '1',
        });
        exportTable.find('ins').css({
          'text-decoration': 'none',
          'font-weight': '700',
          background: 'transparent',
        });
        exportTable.find('.woocommerce-Price-amount').css('white-space', 'nowrap').closest('td').css('white-space', 'nowrap');
        exportTable.find('td, th').css('word-break', 'normal');
        if (params['type'] == 'pdf') {
          exportTable.find('.stDateWrapp').css('white-space', 'nowrap');
          exportTable
            .find('.woocommerce-Price-amount')
            .closest('td')
            .each(function () {
              var td = $(this),
                ins = td.find('ins');
              if (ins.length) {
                ins.replaceWith(ins.html());
              }
              if (td.find('.woocommerce-Price-amount').length > 1) {
                td.html('<span class="woocommerce-Price-amount">' + td.html() + '<span>');
              }
            });
        }
      }
      return exportTable;
    };

  app.WooPro();
  });

  app.setTableAddSearching = function (table) {
    return table.closest('.supsystic-tables-wrap').find('.stbWooCategoryFilter, .stbWooFilterWrapper select, .stbWooFilterWrapper .stWooPriceInput, .stbWooFilterWrapper .stWooPriceRange').length > 0;
  };

  function isWooServerSideTable(table) {
    var apiSettings = table.api().settings()[0];

    return table.attr('data-server-side-processing') === 'on' || (apiSettings && apiSettings.oFeatures && apiSettings.oFeatures.bServerSide);
  }

  function getWooServerSideFilters(table) {
    var filters = [],
      tableWrapper = table.closest('.supsystic-tables-wrap');

    var categorySelection = getWooCategoryFilterSelection(tableWrapper);
    if (categorySelection.total > 0 && categorySelection.selected.length !== categorySelection.total) {
      filters.push({
        type: 'category_list',
        values: categorySelection.selected,
      });
    }

    tableWrapper.find('.stbWooFilterWrapper select').each(function () {
        var select = $(this),
          filterType = select.attr('data-filter-type') || '',
          taxonomy = select.attr('data-taxonomy') || '',
          value = select.val(),
          keys = select.attr('data-column-keys'),
          columns = typeof keys !== 'undefined' ? keys.split(',') : [];

        if (value && filterType === 'stock_status') {
          filters.push({
            type: 'stock_status',
            value: value,
          });
          return;
        }

        if (value && filterType === 'attribute_term') {
          filters.push({
            type: 'attribute_term',
            taxonomy: taxonomy,
            value: value,
          });
          return;
        }

        columns = $.map(columns, function (columnId) {
          columnId = parseInt($.trim(columnId), 10);

          return isNaN(columnId) ? null : columnId;
        });

        if (value && columns.length) {
          filters.push({
            columns: columns,
            value: value,
          });
        }
      });

    var priceBounds = getWooSharedPriceState(tableWrapper);
    if (priceBounds.min !== null || priceBounds.max !== null) {
      filters.push({
        type: 'price_range',
        min: priceBounds.min !== null ? priceBounds.min : '',
        max: priceBounds.max !== null ? priceBounds.max : '',
      });
    }

    return filters;
  }

  function getWooCategoryFilterSelection(tableWrapper) {
    var filterWrap = tableWrapper.find('.stbWooCategoryFilter').first(),
      items = filterWrap.find('.stbWooCategoryFilterItem'),
      selected = [];

    items.each(function () {
      var item = $(this),
        checkbox = item.find('.stbWooCategoryFilterCheckbox'),
        categoryId = parseInt(item.attr('data-category-id'), 10);

      if (!isNaN(categoryId) && checkbox.is(':checked')) {
        selected.push(categoryId);
      }
    });

    return {
      total: items.length,
      selected: selected,
    };
  }

  function syncWooCategoryFilterItemState(item, checked) {
    item.toggleClass('is-active', !!checked);
    item.attr('aria-pressed', checked ? 'true' : 'false');
    item.find('.stbWooCategoryFilterCheckbox').prop('checked', !!checked);
  }

  function parseWooPriceValue(value) {
    if (value === null || typeof value === 'undefined') {
      return null;
    }

    value = $.trim(String(value)).replace(',', '.');
    if (value === '') {
      return null;
    }

    value = parseFloat(value);
    return isNaN(value) ? null : value;
  }

  function roundWooPriceValue(value, step) {
    if (value === null) {
      return null;
    }

    var precision = step && String(step).indexOf('.') !== -1 ? String(step).split('.')[1].length : 0;
    return parseFloat(Number(value).toFixed(precision));
  }

  function normalizeWooPriceBounds(minValue, maxValue, absoluteMin, absoluteMax) {
    if (minValue !== null && absoluteMin !== null) {
      minValue = Math.max(minValue, absoluteMin);
    }
    if (maxValue !== null && absoluteMax !== null) {
      maxValue = Math.min(maxValue, absoluteMax);
    }

    if (minValue !== null && maxValue !== null && minValue > maxValue) {
      var swap = minValue;
      minValue = maxValue;
      maxValue = swap;
    }

    return {
      min: minValue,
      max: maxValue,
    };
  }

  function compactWooPriceBounds(state, absoluteMin, absoluteMax) {
    state = state || {
      min: null,
      max: null,
    };

    if (absoluteMin !== null && state.min !== null && state.min === absoluteMin) {
      state.min = null;
    }
    if (absoluteMax !== null && state.max !== null && state.max === absoluteMax) {
      state.max = null;
    }

    return state;
  }

  function getWooPriceFilterState(priceFilter) {
    var absoluteMin = parseWooPriceValue(priceFilter.attr('data-min')),
      absoluteMax = parseWooPriceValue(priceFilter.attr('data-max')),
      inputMin = priceFilter.find('.stWooPriceInputMin'),
      inputMax = priceFilter.find('.stWooPriceInputMax'),
      rangeMin = priceFilter.find('.stWooPriceRangeMin'),
      rangeMax = priceFilter.find('.stWooPriceRangeMax'),
      minValue = inputMin.length ? parseWooPriceValue(inputMin.val()) : null,
      maxValue = inputMax.length ? parseWooPriceValue(inputMax.val()) : null;

    if (minValue === null && rangeMin.length) {
      minValue = parseWooPriceValue(rangeMin.val());
    }
    if (maxValue === null && rangeMax.length) {
      maxValue = parseWooPriceValue(rangeMax.val());
    }

    return compactWooPriceBounds(
      normalizeWooPriceBounds(minValue, maxValue, absoluteMin, absoluteMax),
      absoluteMin,
      absoluteMax
    );
  }

  function updateWooPriceSliderVisual(priceFilter, minValue, maxValue) {
    var absoluteMin = parseWooPriceValue(priceFilter.attr('data-min')),
      absoluteMax = parseWooPriceValue(priceFilter.attr('data-max')),
      range = absoluteMax !== null && absoluteMin !== null ? absoluteMax - absoluteMin : 0,
      startPercent = 0,
      endPercent = 100;

    if (range > 0 && minValue !== null && maxValue !== null) {
      startPercent = ((minValue - absoluteMin) / range) * 100;
      endPercent = ((maxValue - absoluteMin) / range) * 100;
    }

    priceFilter.css({
      '--stb-price-start': startPercent + '%',
      '--stb-price-end': endPercent + '%',
    });
  }

  function syncWooPriceFilterUi(priceFilter, state) {
    var step = priceFilter.attr('data-step') || '1',
      rangeMin = priceFilter.find('.stWooPriceRangeMin'),
      rangeMax = priceFilter.find('.stWooPriceRangeMax'),
      inputMin = priceFilter.find('.stWooPriceInputMin'),
      inputMax = priceFilter.find('.stWooPriceInputMax'),
      absoluteMin = parseWooPriceValue(priceFilter.attr('data-min')),
      absoluteMax = parseWooPriceValue(priceFilter.attr('data-max')),
      minValue = state.min !== null ? state.min : absoluteMin,
      maxValue = state.max !== null ? state.max : absoluteMax;

    minValue = roundWooPriceValue(minValue, step);
    maxValue = roundWooPriceValue(maxValue, step);

    if (rangeMin.length) {
      rangeMin.val(minValue);
    }
    if (rangeMax.length) {
      rangeMax.val(maxValue);
    }
    if (inputMin.length) {
      inputMin.val(state.min === null ? '' : minValue);
    }
    if (inputMax.length) {
      inputMax.val(state.max === null ? '' : maxValue);
    }

    updateWooPriceSliderVisual(priceFilter, minValue, maxValue);
  }

  function getWooPriceColumnSearchState(priceSearch, absoluteMin, absoluteMax) {
    if (!priceSearch || !priceSearch.length) {
      return {
        min: null,
        max: null,
      };
    }

    absoluteMin = absoluteMin !== null && typeof absoluteMin !== 'undefined' ? absoluteMin : null;
    absoluteMax = absoluteMax !== null && typeof absoluteMax !== 'undefined' ? absoluteMax : null;

    return compactWooPriceBounds(
      normalizeWooPriceBounds(
        parseWooPriceValue(priceSearch.find('.stWooColumnPriceSearchMin').val()),
        parseWooPriceValue(priceSearch.find('.stWooColumnPriceSearchMax').val()),
        absoluteMin,
        absoluteMax
      ),
      absoluteMin,
      absoluteMax
    );
  }

  function syncWooPriceColumnSearchUi(priceSearch, state) {
    if (!priceSearch || !priceSearch.length) {
      return;
    }

    priceSearch.find('.stWooColumnPriceSearchMin').val(state.min === null ? '' : state.min);
    priceSearch.find('.stWooColumnPriceSearchMax').val(state.max === null ? '' : state.max);
  }

  function storeWooPriceFilterState(tableWrapper, state) {
    tableWrapper.data('dtgWooPriceFilterState', {
      min: state && state.min !== null ? state.min : null,
      max: state && state.max !== null ? state.max : null,
    });
  }

  function getWooSharedPriceState(tableWrapper) {
    var priceFilter = tableWrapper.find('.stWooFilterPrice[data-filter-type="price_range"]').first(),
      priceSearch = tableWrapper.find('.stWooColumnPriceSearch[data-filter-type="price_range"]').first(),
      absoluteMin = priceFilter.length ? parseWooPriceValue(priceFilter.attr('data-min')) : null,
      absoluteMax = priceFilter.length ? parseWooPriceValue(priceFilter.attr('data-max')) : null,
      filterState = priceFilter.length ? getWooPriceFilterState(priceFilter) : null,
      searchState = priceSearch.length ? getWooPriceColumnSearchState(priceSearch, absoluteMin, absoluteMax) : null,
      storedFilterState = tableWrapper.data('dtgWooPriceFilterState') || null,
      storedSearchState = tableWrapper.data('dtgWooPriceColumnSearchState') || null,
      state = null;

    if (filterState && (filterState.min !== null || filterState.max !== null)) {
      state = filterState;
    } else if (searchState && (searchState.min !== null || searchState.max !== null)) {
      state = searchState;
    } else if (storedFilterState && (storedFilterState.min !== null || storedFilterState.max !== null)) {
      state = compactWooPriceBounds(normalizeWooPriceBounds(storedFilterState.min, storedFilterState.max, absoluteMin, absoluteMax), absoluteMin, absoluteMax);
    } else if (storedSearchState && (storedSearchState.min !== null || storedSearchState.max !== null)) {
      state = compactWooPriceBounds(normalizeWooPriceBounds(storedSearchState.min, storedSearchState.max, absoluteMin, absoluteMax), absoluteMin, absoluteMax);
    }

    return state || {
      min: null,
      max: null,
    };
  }

  function syncWooSharedPriceState(tableWrapper, state) {
    var priceFilter = tableWrapper.find('.stWooFilterPrice[data-filter-type="price_range"]').first(),
      priceSearch = tableWrapper.find('.stWooColumnPriceSearch[data-filter-type="price_range"]').first(),
      absoluteMin = priceFilter.length ? parseWooPriceValue(priceFilter.attr('data-min')) : null,
      absoluteMax = priceFilter.length ? parseWooPriceValue(priceFilter.attr('data-max')) : null,
      normalizedState = compactWooPriceBounds(
        normalizeWooPriceBounds(
          state && typeof state.min !== 'undefined' ? state.min : null,
          state && typeof state.max !== 'undefined' ? state.max : null,
          absoluteMin,
          absoluteMax
        ),
        absoluteMin,
        absoluteMax
      );

    if (priceFilter.length) {
      syncWooPriceFilterUi(priceFilter, normalizedState);
    }
    if (priceSearch.length) {
      syncWooPriceColumnSearchUi(priceSearch, normalizedState);
    }

    storeWooPriceFilterState(tableWrapper, normalizedState);
    storeWooPriceColumnSearchState(tableWrapper, normalizedState);

    return normalizedState;
  }

  function storeWooPriceColumnSearchState(tableWrapper, state) {
    tableWrapper.data('dtgWooPriceColumnSearchState', {
      min: state && state.min !== null ? state.min : null,
      max: state && state.max !== null ? state.max : null,
    });
  }

  function getWooAddon(tableWrapper) {
    return tableWrapper.find('.supsystic-tables-addon[data-woocommerce-table="on"]').first();
  }

  function getWooAddonColumnsConfig(tableWrapper) {
    var addon = getWooAddon(tableWrapper),
      config = addon.attr('data-woo-columns-config');

    if (!config) {
      return [];
    }

    try {
      config = JSON.parse(config);
    } catch (e) {
      config = [];
    }

    return $.isArray(config) ? config : [];
  }

  function getWooColumnSearchInputs(tableWrapper, columnIndex) {
    var selector = '.search-column';

    if (typeof columnIndex !== 'undefined' && columnIndex !== null) {
      selector += '[data-column-num="' + columnIndex + '"]';
    }

    return tableWrapper.find(selector);
  }

  function getWooColumnSearchField(tableWrapper, columnIndex) {
    return getWooColumnSearchInputs(tableWrapper, columnIndex).first().closest('.stbColumnSearchField');
  }

  function initWooColumnSearchVisibility(table) {
    var tableWrapper = table.closest('.supsystic-tables-wrap'),
      columnsConfig = getWooAddonColumnsConfig(tableWrapper);

    if (!columnsConfig.length) {
      return;
    }

    $.each(columnsConfig, function (index, columnConfig) {
      var searchField = getWooColumnSearchField(tableWrapper, index),
        hideSearchInput = columnConfig && parseInt(columnConfig.hide_search_input, 10) === 1;

      if (!searchField.length || !columnConfig) {
        return;
      }

      if (columnConfig.slug === 'price') {
        hideSearchInput = parseInt(columnConfig.show_price_search_inputs, 10) !== 1;
      }

      searchField.toggle(!hideSearchInput);
    });
  }

  function initWooAttributeFilters(table) {
    var tableWrapper = table.closest('.supsystic-tables-wrap'),
      attributeSelects = tableWrapper.find('.stWooFilterAttributes select[data-filter-type="attribute_term"]'),
      storedFilterFn = tableWrapper.data('dtgWooAttributeFilterFn'),
      extSearch = $.fn.dataTable.ext.search;

    if (storedFilterFn) {
      var existingIndex = $.inArray(storedFilterFn, extSearch);
      if (existingIndex !== -1) {
        extSearch.splice(existingIndex, 1);
      }
      tableWrapper.removeData('dtgWooAttributeFilterFn');
    }

    if (!attributeSelects.length) {
      return;
    }

    var tableNode = table.get(0),
      getRowMetaAttributes = function (rowNode) {
        var row = rowNode ? $(rowNode) : $(),
          rowMeta = row.find('.stbWooRowMeta').first();

        return {
          stock: row.attr('data-stock-status') || (rowMeta.length ? rowMeta.attr('data-stock-status') : '') || '',
          attrs: row.attr('data-woo-attributes') || (rowMeta.length ? rowMeta.attr('data-woo-attributes') : '') || '',
        };
      },
      filterFn = function (settings, data, dataIndex) {
        if (settings.nTable !== tableNode || isWooServerSideTable(table)) {
          return true;
        }

        var hasActiveFilters = false,
          aoData = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex] : null,
          rowNode = aoData && aoData.nTr ? aoData.nTr : null,
          rowMeta = getRowMetaAttributes(rowNode),
          rawAttributes = rowMeta.attrs,
          rowAttributes = {};

        if (rawAttributes) {
          try {
            rowAttributes = JSON.parse(rawAttributes) || {};
          } catch (e) {
            rowAttributes = {};
          }
        }

        var matchesAll = true;
        attributeSelects.each(function () {
          var select = $(this),
            selectedValue = select.val(),
            taxonomy = select.attr('data-taxonomy') || '',
            taxonomyValues = $.isArray(rowAttributes[taxonomy]) ? rowAttributes[taxonomy] : [];

          if (!selectedValue) {
            return;
          }

          hasActiveFilters = true;
          if ($.inArray(selectedValue, taxonomyValues) === -1) {
            matchesAll = false;
            return false;
          }
        });

        return !hasActiveFilters || matchesAll;
      };

    extSearch.push(filterFn);
    tableWrapper.data('dtgWooAttributeFilterFn', filterFn);
  }

  function initWooStockStatusFilter(table) {
    var tableWrapper = table.closest('.supsystic-tables-wrap'),
      stockSelect = tableWrapper.find('.stWooFilterStock select'),
      storedFilterFn = tableWrapper.data('dtgWooStockStatusFilterFn'),
      extSearch = $.fn.dataTable.ext.search;

    if (storedFilterFn) {
      var existingIndex = $.inArray(storedFilterFn, extSearch);
      if (existingIndex !== -1) {
        extSearch.splice(existingIndex, 1);
      }
      tableWrapper.removeData('dtgWooStockStatusFilterFn');
    }

    if (!stockSelect.length) {
      return;
    }

    var tableNode = table.get(0),
      getRowStockStatus = function (rowNode) {
        var row = rowNode ? $(rowNode) : $(),
          rowMeta = row.find('.stbWooRowMeta').first();

        return row.attr('data-stock-status') || (rowMeta.length ? rowMeta.attr('data-stock-status') : '') || '';
      },
      filterFn = function (settings, data, dataIndex) {
        if (settings.nTable !== tableNode || isWooServerSideTable(table)) {
          return true;
        }

        var selectedStatus = stockSelect.val();
        if (!selectedStatus) {
          return true;
        }

        var aoData = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex] : null,
          rowNode = aoData && aoData.nTr ? aoData.nTr : null,
          rowStatus = getRowStockStatus(rowNode);

        return rowStatus === selectedStatus;
      };

    extSearch.push(filterFn);
    tableWrapper.data('dtgWooStockStatusFilterFn', filterFn);
  }

  function initWooPriceFilter(table) {
    var tableWrapper = table.closest('.supsystic-tables-wrap'),
      priceFilter = tableWrapper.find('.stWooFilterPrice[data-filter-type="price_range"]'),
      storedFilterFn = tableWrapper.data('dtgWooPriceFilterFn'),
      storedState = tableWrapper.data('dtgWooPriceFilterState') || null,
      extSearch = $.fn.dataTable.ext.search;

    if (storedFilterFn) {
      var existingIndex = $.inArray(storedFilterFn, extSearch);
      if (existingIndex !== -1) {
        extSearch.splice(existingIndex, 1);
      }
      tableWrapper.removeData('dtgWooPriceFilterFn');
    }

    if (!priceFilter.length) {
      return;
    }

    var tableNode = table.get(0),
      getRowPriceRange = function (rowNode) {
        var row = rowNode ? $(rowNode) : $(),
          rowMeta = row.find('.stbWooRowMeta').first(),
          minValue = row.attr('data-price-min') || (rowMeta.length ? rowMeta.attr('data-price-min') : '') || '',
          maxValue = row.attr('data-price-max') || (rowMeta.length ? rowMeta.attr('data-price-max') : '') || '';

        return {
          min: parseWooPriceValue(minValue),
          max: parseWooPriceValue(maxValue),
        };
      },
      filterFn = function (settings, data, dataIndex) {
        if (settings.nTable !== tableNode || isWooServerSideTable(table)) {
          return true;
        }

        var state = getWooPriceFilterState(priceFilter),
          absoluteMin = parseWooPriceValue(priceFilter.attr('data-min')),
          absoluteMax = parseWooPriceValue(priceFilter.attr('data-max'));

        state = normalizeWooPriceBounds(state.min, state.max, absoluteMin, absoluteMax);
        if (state.min === null && state.max === null) {
          return true;
        }

        var aoData = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex] : null,
          rowNode = aoData && aoData.nTr ? aoData.nTr : null,
          rowRange = getRowPriceRange(rowNode),
          rowMin = rowRange.min,
          rowMax = rowRange.max;

        if (rowMin === null && rowMax === null) {
          return false;
        }
        if (rowMin === null) {
          rowMin = rowMax;
        }
        if (rowMax === null) {
          rowMax = rowMin;
        }

        if (state.min !== null && rowMax < state.min) {
          return false;
        }
        if (state.max !== null && rowMin > state.max) {
          return false;
        }

        return true;
      };

    extSearch.push(filterFn);
    tableWrapper.data('dtgWooPriceFilterFn', filterFn);

    if (storedState && (storedState.min !== null || storedState.max !== null)) {
      syncWooSharedPriceState(tableWrapper, storedState);
    } else {
      syncWooSharedPriceState(tableWrapper, getWooSharedPriceState(tableWrapper));
    }

    priceFilter.off('.dtgPrice');

    priceFilter.on('input.dtgPrice change.dtgPrice', '.stWooPriceRange', function () {
      var absoluteMin = parseWooPriceValue(priceFilter.attr('data-min')),
        absoluteMax = parseWooPriceValue(priceFilter.attr('data-max')),
        rangeMin = parseWooPriceValue(priceFilter.find('.stWooPriceRangeMin').val()),
        rangeMax = parseWooPriceValue(priceFilter.find('.stWooPriceRangeMax').val()),
        state = normalizeWooPriceBounds(rangeMin, rangeMax, absoluteMin, absoluteMax);

      syncWooSharedPriceState(tableWrapper, state);
    });

    priceFilter.on('change.dtgPrice', '.stWooPriceInput', function () {
      var state = getWooPriceFilterState(priceFilter),
        absoluteMin = parseWooPriceValue(priceFilter.attr('data-min')),
        absoluteMax = parseWooPriceValue(priceFilter.attr('data-max'));

      state = normalizeWooPriceBounds(state.min, state.max, absoluteMin, absoluteMax);
      syncWooSharedPriceState(tableWrapper, state);
      table.api().draw();
    });

    priceFilter.on('input.dtgPrice', '.stWooPriceRange', function () {
      if (!isWooServerSideTable(table)) {
        table.api().draw();
      }
    });

    priceFilter.on('change.dtgPrice', '.stWooPriceRange', function () {
      table.api().draw();
    });
  }

  function initWooPriceColumnSearch(table) {
    var tableWrapper = table.closest('.supsystic-tables-wrap'),
      columnsConfig = getWooAddonColumnsConfig(tableWrapper),
      storedFilterFn = tableWrapper.data('dtgWooPriceColumnSearchFilterFn'),
      storedState = tableWrapper.data('dtgWooPriceColumnSearchState') || null,
      extSearch = $.fn.dataTable.ext.search,
      addon = getWooAddon(tableWrapper),
      priceColumnIndex = -1,
      priceColumnConfig = null,
      fromPlaceholder = addon.attr('data-price-from-placeholder') || 'From',
      toPlaceholder = addon.attr('data-price-to-placeholder') || 'To';

    if (storedFilterFn) {
      var existingIndex = $.inArray(storedFilterFn, extSearch);
      if (existingIndex !== -1) {
        extSearch.splice(existingIndex, 1);
      }
      tableWrapper.removeData('dtgWooPriceColumnSearchFilterFn');
    }

    if (!columnsConfig.length) {
      return;
    }

    $.each(columnsConfig, function (index, columnConfig) {
      if (columnConfig && columnConfig.slug === 'price') {
        priceColumnIndex = index;
        priceColumnConfig = columnConfig;
        return false;
      }
    });

    if (priceColumnIndex < 0 || !priceColumnConfig) {
      return;
    }

    var originalInput = getWooColumnSearchInputs(tableWrapper, priceColumnIndex).first(),
      searchField = getWooColumnSearchField(tableWrapper, priceColumnIndex),
      shouldShow = parseInt(priceColumnConfig.show_price_search_inputs, 10) === 1,
      customWrap = searchField.find('.stWooColumnPriceSearch');

    if (!searchField.length || !originalInput.length) {
      return;
    }

    if (!shouldShow) {
      if (customWrap.length) {
        customWrap.remove();
      }
      originalInput.removeClass('stHidden').show();
      return;
    }

    originalInput.val('').addClass('stHidden').hide();

    if (!customWrap.length) {
      customWrap = $('<div class="stWooColumnPriceSearch" data-filter-type="price_range"></div>');
      customWrap.append('<input type="number" class="stWooColumnPriceSearchInput stWooColumnPriceSearchMin" placeholder="' + fromPlaceholder + '" />');
      customWrap.append('<span class="stWooPriceDash">&ndash;</span>');
      customWrap.append('<input type="number" class="stWooColumnPriceSearchInput stWooColumnPriceSearchMax" placeholder="' + toPlaceholder + '" />');
      searchField.append(customWrap);
    }

    syncWooSharedPriceState(tableWrapper, storedState || getWooSharedPriceState(tableWrapper));

    var applyPriceColumnSearchState = function () {
      var priceFilter = tableWrapper.find('.stWooFilterPrice[data-filter-type="price_range"]').first(),
        absoluteMin = priceFilter.length ? parseWooPriceValue(priceFilter.attr('data-min')) : null,
        absoluteMax = priceFilter.length ? parseWooPriceValue(priceFilter.attr('data-max')) : null,
        state = getWooPriceColumnSearchState(customWrap, absoluteMin, absoluteMax),
        column = table.api().column(priceColumnIndex);

      syncWooSharedPriceState(tableWrapper, state);
      originalInput.val('');
      if (column.search() !== '') {
        column.search('');
      }
      table.api().draw();
    };

    customWrap
      .off('.dtgWooPriceColumn')
      .on('mousedown.dtgWooPriceColumn click.dtgWooPriceColumn touchstart.dtgWooPriceColumn', 'input', function (e) {
        e.stopPropagation();
      })
      .on('keydown.dtgWooPriceColumn', 'input', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
          var debounceTimer = customWrap.data('dtgWooPriceColumnTimer');

          e.preventDefault();
          e.stopPropagation();

          if (debounceTimer) {
            clearTimeout(debounceTimer);
            customWrap.removeData('dtgWooPriceColumnTimer');
          }

          applyPriceColumnSearchState();
          return false;
        }

        e.stopPropagation();
      })
      .on('input.dtgWooPriceColumn', 'input', function () {
        var debounceTimer = customWrap.data('dtgWooPriceColumnTimer');

        if (debounceTimer) {
          clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(function () {
          applyPriceColumnSearchState();
          customWrap.removeData('dtgWooPriceColumnTimer');
        }, 2000);

        customWrap.data('dtgWooPriceColumnTimer', debounceTimer);
      })
      .on('change.dtgWooPriceColumn', 'input', function () {
        var debounceTimer = customWrap.data('dtgWooPriceColumnTimer');

        if (debounceTimer) {
          clearTimeout(debounceTimer);
          customWrap.removeData('dtgWooPriceColumnTimer');
        }

        applyPriceColumnSearchState();
      });

    var tableNode = table.get(0),
      getRowPriceRange = function (rowNode) {
        var row = rowNode ? $(rowNode) : $(),
          rowMeta = row.find('.stbWooRowMeta').first(),
          minValue = row.attr('data-price-min') || (rowMeta.length ? rowMeta.attr('data-price-min') : '') || '',
          maxValue = row.attr('data-price-max') || (rowMeta.length ? rowMeta.attr('data-price-max') : '') || '';

        return {
          min: parseWooPriceValue(minValue),
          max: parseWooPriceValue(maxValue),
        };
      },
      filterFn = function (settings, data, dataIndex) {
        if (settings.nTable !== tableNode || isWooServerSideTable(table)) {
          return true;
        }

        var state = tableWrapper.data('dtgWooPriceColumnSearchState') || {},
          stateMin = state.min !== null && typeof state.min !== 'undefined' ? state.min : null,
          stateMax = state.max !== null && typeof state.max !== 'undefined' ? state.max : null;

        if (stateMin === null && stateMax === null) {
          return true;
        }

        var aoData = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex] : null,
          rowNode = aoData && aoData.nTr ? aoData.nTr : null,
          rowRange = getRowPriceRange(rowNode),
          rowMin = rowRange.min,
          rowMax = rowRange.max;

        if (rowMin === null && rowMax === null) {
          return false;
        }
        if (rowMin === null) {
          rowMin = rowMax;
        }
        if (rowMax === null) {
          rowMax = rowMin;
        }

        if (stateMin !== null && rowMax < stateMin) {
          return false;
        }
        if (stateMax !== null && rowMin > stateMax) {
          return false;
        }

        return true;
      };

    extSearch.push(filterFn);
    tableWrapper.data('dtgWooPriceColumnSearchFilterFn', filterFn);
  }

  function getWooColumnToggleKey(table) {
    return (table.attr('id') || 'woo-table').replace(/[^A-Za-z0-9_-]/g, '');
  }

  function getWooColumnLabel(column) {
    var header = $(column.header());
    if (!header.length) {
      return '';
    }

    var clone = header.clone();
    clone.find('.stbColumnSearchField, input, select, textarea, script, style').remove();

    return $.trim(clone.text()).replace(/\s+/g, ' ');
  }

  function getWooColumnsConfig(toggleWrap) {
    var config = toggleWrap.attr('data-columns-config');

    if (!config) {
      return [];
    }

    try {
      config = JSON.parse(config);
    } catch (e) {
      config = [];
    }

    return $.isArray(config) ? config : [];
  }

  function getWooConfiguredColumnLabel(columnConfig, column) {
    var configuredLabel = '';

    if (columnConfig) {
      if (parseInt(columnConfig.show_display_name, 10) && columnConfig.display_name) {
        configuredLabel = $.trim(columnConfig.display_name);
      } else if (columnConfig.original_name) {
        configuredLabel = $.trim(columnConfig.original_name);
      } else if (columnConfig.display_name) {
        configuredLabel = $.trim(columnConfig.display_name);
      }
    }

    return configuredLabel || getWooColumnLabel(column);
  }

  function isWooToggleEligibleColumn(column, columnConfig) {
    var header = $(column.header()),
      label = getWooConfiguredColumnLabel(columnConfig, column);

    if (!header.length || !label) {
      return false;
    }

    if (header.hasClass('control') || header.hasClass('dtr-control')) {
      return false;
    }

    if (columnConfig && parseInt(columnConfig.hide_column, 10)) {
      return false;
    }

    return true;
  }

  function syncWooColumnToggleItemState(item, column) {
    var isVisible = column.visible();

    item.toggleClass('is-active', isVisible);
    item.find('.stbWooColumnsToggleCheckbox').prop('checked', isVisible);
    item.attr('aria-pressed', isVisible ? 'true' : 'false');
  }

  function updateWooMobileDropdownViewportOffset(toggleWrap, toggleButton) {
    if (!toggleWrap || !toggleWrap.length || !toggleButton || !toggleButton.length) {
      return;
    }

    if (window.matchMedia && !window.matchMedia('(max-width: 768px)').matches) {
      toggleWrap[0].style.removeProperty('--stb-woo-toggle-left');
      return;
    }

    toggleWrap.css('--stb-woo-toggle-left', toggleButton.get(0).getBoundingClientRect().left + 'px');
  }

  function bindWooMobileDropdownViewportOffset(toggleWrap, toggleButton, namespace) {
    var updatePosition = function () {
      updateWooMobileDropdownViewportOffset(toggleWrap, toggleButton);
    };

    updatePosition();
    $(window).off('resize' + namespace).on('resize' + namespace, updatePosition);
    $(window).off('scroll' + namespace).on('scroll' + namespace, updatePosition);
    $(document).off('scroll' + namespace).on('scroll' + namespace, updatePosition);
  }

  function closeWooToggleDropdowns(tableWrapper, exceptWrap) {
    if (!tableWrapper || !tableWrapper.length) {
      return;
    }

    tableWrapper.find('.stbWooColumnsToggle').each(function () {
      var wrap = $(this);
      if (exceptWrap && exceptWrap.length && wrap.is(exceptWrap)) {
        return;
      }

      wrap.find('.stbWooColumnsToggleButton').attr('aria-expanded', 'false');
      wrap.find('.stbWooColumnsToggleDropdown').addClass('stHidden');
    });

    tableWrapper.find('.stbWooExportToggle').each(function () {
      var wrap = $(this);
      if (exceptWrap && exceptWrap.length && wrap.is(exceptWrap)) {
        return;
      }

      wrap.find('.stbWooExportToggleButton').attr('aria-expanded', 'false');
      wrap.find('.stbWooExportToggleDropdown').addClass('stHidden');
    });

    tableWrapper.find('.stbWooCategoryFilter').each(function () {
      var wrap = $(this);
      if (exceptWrap && exceptWrap.length && wrap.is(exceptWrap)) {
        return;
      }

      wrap.find('.stbWooCategoryFilterButton').attr('aria-expanded', 'false');
      wrap.find('.stbWooCategoryFilterDropdown').addClass('stHidden');
    });
  }

  function syncWooTableLayoutAfterColumnToggle(table, api) {
    var tableWrapper = table.closest('.supsystic-tables-wrap'),
      tableId = table.data('id'),
      syncLayout = function () {
        var scrollBody = tableWrapper.find('.dataTables_scrollBody'),
          scrollBodyTable = scrollBody.find('.supsystic-table');

        if (scrollBody.length && scrollBodyTable.length && scrollBodyTable.is(':visible')) {
          if (scrollBody.width() > scrollBodyTable.width() || tableWrapper.width() > scrollBodyTable.width()) {
            scrollBody.width(scrollBodyTable.width());
            tableWrapper.find('.dataTables_scrollHead, .dataTables_scrollFoot, .dataTables_scrollBody').width(scrollBodyTable.width() + 1);
          }
        }

        if (api.responsive && typeof api.responsive.recalc === 'function') {
          api.responsive.recalc();
        }
        api.columns.adjust();

        if (typeof app.getTableInstanceById === 'function') {
          var instance = app.getTableInstanceById(tableId);
          if (instance && typeof instance.fnAdjustColumnSizing === 'function') {
            instance.fnAdjustColumnSizing(false);
          }
        }
      };

    window.setTimeout(syncLayout, 0);
    window.setTimeout(syncLayout, 120);
    window.setTimeout(syncLayout, 320);
  }

  function sanitizeWooExportFileName(value) {
    value = $.trim(value || '');
    if (!value) {
      value = 'table';
    }

    value = value.replace(/[\\\/:*?"<>|]+/g, '-').replace(/\s+/g, ' ');

    return $.trim(value) || 'table';
  }

  function removeWooExportColumnByIndex(table, columnIndex) {
    if (!table || !table.length || columnIndex < 0) {
      return;
    }

    table.find('tr').each(function () {
      $(this).children('th, td').eq(columnIndex).remove();
    });
  }

  function getWooVisibleExportTable(wrapper, exportType) {
    if (!wrapper || !wrapper.length) {
      return $();
    }

    var table = wrapper.find('.supsystic-table:first');
    if (!table.length) {
      return $();
    }

    var tableId = table.data('id'),
      tableHtmlId = '#supsystic-table-' + tableId,
      clonedWrapper = wrapper.clone(),
      clonedTable = clonedWrapper.find(tableHtmlId).first(),
      caption = clonedWrapper.find(tableHtmlId + ' caption:first').clone(),
      thead = clonedWrapper.find('.dataTables_scrollHead thead:first').clone(),
      tbody = clonedWrapper.find(tableHtmlId + ' tbody:first').clone(),
      tfoot = clonedWrapper.find('.dataTables_scrollFoot tfoot:first').clone();

    if (!thead.length) {
      thead = clonedWrapper.find(tableHtmlId + ' thead:first').clone();
    }
    if (!tfoot.length) {
      tfoot = clonedWrapper.find(tableHtmlId + ' tfoot:first').clone();
    }

    if (typeof _stbPrepareTableToExport === 'function') {
      clonedWrapper = _stbPrepareTableToExport(clonedWrapper, table, {
        type: exportType,
        useClonedId: false,
      });
      clonedTable = clonedWrapper.find(tableHtmlId).first();
    }

    if (typeof app.WooProExportPrepare === 'function') {
      clonedWrapper = app.WooProExportPrepare(table, clonedWrapper, {
        type: exportType,
      });
      clonedTable = clonedWrapper.find(tableHtmlId).first();
    }

    if (!clonedTable.length) {
      return $();
    }

    clonedWrapper.find('.dataTables_wrapper').each(function () {
      var currentWrapper = $(this),
        currentTable = currentWrapper.find(tableHtmlId).first();

      if (currentTable.length) {
        currentWrapper.empty().append(currentTable);
      }
    });

    clonedTable.empty();
    if (caption.length) {
      clonedTable.append(caption);
    }
    if (thead.length) {
      clonedTable.append(thead);
    }
    if (tbody.length) {
      clonedTable.append(tbody);
    }
    if (tfoot.length) {
      clonedTable.append(tfoot);
    }

    clonedTable.find('.stbColumnsSearchWrapper, .dataTables_empty, .tblEditLink').remove();
    clonedTable.find('input, textarea, select').each(function () {
      var field = $(this),
        replacement = '';

      if (field.is('select')) {
        replacement = field.find('option:selected').map(function () {
          return $.trim($(this).text());
        }).get().join(', ');
      } else if (field.is(':checkbox')) {
        replacement = field.is(':checked') ? 'Yes' : '';
      } else {
        replacement = field.val();
      }

      field.replaceWith(document.createTextNode(replacement));
    });

    clonedTable.find('a').each(function () {
      $(this).replaceWith(document.createTextNode($.trim($(this).text())));
    });

    if (exportType === 'csv' || exportType === 'xls' || exportType === 'xlsx') {
      var addToCartColumnIndex = -1,
        addToCartCell = clonedTable
          .find('tbody td')
          .filter(function () {
            return $(this).find('.stAddToCartWrapper, .stAddToCartButWrapp, .stAddToCart, .stAddMulty').length > 0;
          })
          .first();

      if (addToCartCell.length) {
        addToCartColumnIndex = addToCartCell.get(0).cellIndex;
      }

      if (addToCartColumnIndex >= 0) {
        removeWooExportColumnByIndex(clonedTable, addToCartColumnIndex);
      }

      clonedTable.find('img').each(function () {
        var image = $(this),
          imageSrc = $.trim(image.attr('src') || '');

        image.replaceWith(document.createTextNode(imageSrc));
      });
    }

    clonedTable.find('.screen-reader-text, .woocommerce-Price-currencySymbol[aria-hidden="true"]').each(function () {
      var current = $(this);

      if (current.hasClass('woocommerce-Price-currencySymbol')) {
        current.removeAttr('aria-hidden');
        return;
      }

      current.remove();
    });

    clonedTable.find('del').css({
      'text-decoration': 'line-through',
      opacity: '1',
    });
    clonedTable.find('ins').css({
      'text-decoration': 'none',
      'font-weight': '700',
      background: 'transparent',
    });

    clonedTable.find('.stbWooRowMeta').remove();
    clonedTable.find('th, td').filter(function () {
      return $(this).css('display') === 'none' || $(this).hasClass('stHidden');
    }).remove();

    return clonedTable;
  }

  function getWooExportRows(table) {
    var rows = [];

    table.find('tr').each(function () {
      var currentRow = [],
        hasCells = false;

      $(this)
        .children('th, td')
        .each(function () {
          var cell = $(this),
            cellClone = cell.clone();

          cellClone.find('.screen-reader-text').remove();
          cellClone.find('.woocommerce-Price-currencySymbol[aria-hidden="true"]').removeAttr('aria-hidden');

          var text = $.trim(cellClone.text().replace(/\s+/g, ' '));

          currentRow.push(text);
          hasCells = true;
        });

      if (hasCells) {
        rows.push(currentRow);
      }
    });

    return rows;
  }

  function buildWooCsvContent(rows) {
    return $.map(rows, function (row) {
      return $.map(row, function (cell) {
        cell = cell == null ? '' : String(cell);
        return '"' + cell.replace(/"/g, '""') + '"';
      }).join(',');
    }).join('\r\n');
  }

  function buildWooExcelHtml(table, title) {
    var html = [
      '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">',
      '<head>',
      '<meta charset="utf-8">',
      '<meta name="ProgId" content="Excel.Sheet">',
      '<meta name="Generator" content="Supsystic Tables">',
      '<title>' + $('<div/>').text(title).html() + '</title>',
      '<style>',
      'table{border-collapse:collapse;width:100%;}',
      'th,td{border:1px solid #d7dce1;padding:6px 8px;text-align:left;vertical-align:top;}',
      'th{font-weight:700;}',
      'del{text-decoration:line-through;opacity:1;}',
      'ins{text-decoration:none;font-weight:700;background:transparent;}',
      '</style>',
      '</head>',
      '<body>',
      $('<div/>').append(table.clone()).html(),
      '</body>',
      '</html>',
    ];

    return html.join('');
  }

  function downloadWooExportBlob(content, fileName, mimeType) {
    var blob = new Blob([content], { type: mimeType }),
      url = window.URL.createObjectURL(blob),
      link = document.createElement('a');

    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    window.setTimeout(function () {
      window.URL.revokeObjectURL(url);
    }, 1000);
  }

  function exportWooVisibleTable(tableWrapper, exportType) {
    if (!tableWrapper || !tableWrapper.length) {
      return false;
    }

    var exportTable = getWooVisibleExportTable(tableWrapper, exportType);
    if (!exportTable.length) {
      return false;
    }

    var sourceTable = tableWrapper.find('.supsystic-table:first'),
      fileBaseName = sanitizeWooExportFileName(sourceTable.attr('data-title') || sourceTable.data('title') || document.title || 'table');

    if (exportType === 'csv') {
      var csvRows = getWooExportRows(exportTable),
        csvContent = '\uFEFF' + buildWooCsvContent(csvRows);

      downloadWooExportBlob(csvContent, fileBaseName + '.csv', 'text/csv;charset=utf-8;');
      return true;
    }

    if (exportType === 'xls' || exportType === 'xlsx') {
      var excelHtml = buildWooExcelHtml(exportTable, fileBaseName),
        excelMime = exportType === 'xlsx'
          ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;charset=utf-8;'
          : 'application/vnd.ms-excel;charset=utf-8;';

      downloadWooExportBlob('\uFEFF' + excelHtml, fileBaseName + '.' + exportType, excelMime);
      return true;
    }

    return false;
  }

  function initWooColumnVisibilityToggle(table) {
    var tableWrapper = table.closest('.supsystic-tables-wrap'),
      toggleWrap = tableWrapper.find('.stbWooColumnsToggle');

    if (!toggleWrap.length) {
      return;
    }

    var api = table.api(),
      toggleButton = toggleWrap.find('.stbWooColumnsToggleButton'),
      toggleDropdown = toggleWrap.find('.stbWooColumnsToggleDropdown'),
      toggleList = toggleWrap.find('.stbWooColumnsToggleList'),
      columnsConfig = getWooColumnsConfig(toggleWrap),
      namespace = '.dtgWooColumns' + getWooColumnToggleKey(table),
      eligibleColumns = [];

    bindWooMobileDropdownViewportOffset(toggleWrap, toggleButton, namespace);

    api.columns().every(function (index) {
      var column = api.column(index),
        columnConfig = columnsConfig[index] || null,
        label = getWooConfiguredColumnLabel(columnConfig, column);

      if (!isWooToggleEligibleColumn(column, columnConfig)) {
        return;
      }

      eligibleColumns.push({
        index: index,
        label: label,
      });
    });

    toggleList.empty();

    if (!eligibleColumns.length) {
      toggleWrap.addClass('stHidden');
      return;
    }

    toggleWrap.removeClass('stHidden');

    $.each(eligibleColumns, function (_, itemData) {
      var item = $('<button type="button" class="stbWooColumnsToggleItem"></button>');
      item.attr({
        'data-column-index': itemData.index,
        'data-column-label': itemData.label,
      });
      item.html(
        '<span class="stbWooColumnsToggleItemInner">' +
          '<input type="checkbox" class="stbWooColumnsToggleCheckbox" />' +
          '<span class="stbWooColumnsToggleItemLabel"></span>' +
          '</span>'
      );
      item.find('.stbWooColumnsToggleItemLabel').text(itemData.label);
      syncWooColumnToggleItemState(item, api.column(itemData.index));
      toggleList.append(item);
    });

    toggleButton.off('click' + namespace).on('click' + namespace, function (e) {
      e.preventDefault();
      e.stopPropagation();

      var expanded = toggleButton.attr('aria-expanded') === 'true';
      if (!expanded) {
        closeWooToggleDropdowns(tableWrapper, toggleWrap);
      }
      updateWooMobileDropdownViewportOffset(toggleWrap, toggleButton);
      toggleButton.attr('aria-expanded', expanded ? 'false' : 'true');
      toggleDropdown.toggleClass('stHidden', expanded);
    });

    toggleWrap.off('click' + namespace, '.stbWooColumnsToggleItem').on('click' + namespace, '.stbWooColumnsToggleItem', function (e) {
      e.preventDefault();

      var item = $(this),
        columnIndex = parseInt(item.attr('data-column-index'), 10);

      if (isNaN(columnIndex)) {
        return;
      }

      var column = api.column(columnIndex);
      column.visible(!column.visible(), false);

      if (api.responsive && typeof api.responsive.recalc === 'function') {
        api.responsive.recalc();
      }

      api.columns.adjust().draw(false);
      syncWooTableLayoutAfterColumnToggle(table, api);
      syncWooColumnToggleItemState(item, column);
    });

    api.off('column-visibility' + namespace).on('column-visibility' + namespace, function (e, settings, columnIndex) {
      var item = toggleList.find('.stbWooColumnsToggleItem[data-column-index="' + columnIndex + '"]');

      if (item.length) {
        syncWooColumnToggleItemState(item, api.column(columnIndex));
      }
    });

    $(document).off('click' + namespace).on('click' + namespace, function (e) {
      if ($(e.target).closest('.stbWooColumnsToggle').length) {
        return;
      }

      toggleButton.attr('aria-expanded', 'false');
      toggleDropdown.addClass('stHidden');
    });
  }

  function initWooFiltersToggle(table) {
    var tableWrapper = table.closest('.supsystic-tables-wrap'),
      toggleWrap = tableWrapper.find('.stbWooFilterToggle'),
      filtersWrap = tableWrapper.find('.stbWooFilterWrapper').first();

    if (!toggleWrap.length || !filtersWrap.length) {
      return;
    }

    var toggleButton = toggleWrap.find('.stbWooFiltersToggleButton'),
      resetButton = toggleWrap.find('.stbResetWooFilters'),
      namespace = '.dtgWooFiltersToggle' + getWooColumnToggleKey(table),
      setExpandedState = function (expanded) {
        toggleButton.attr('aria-expanded', expanded ? 'true' : 'false');
        filtersWrap.toggleClass('stHidden', !expanded);
        if (resetButton.length) {
          resetButton.toggleClass('stHidden', !expanded);
        }
      };

    setExpandedState(toggleButton.attr('aria-expanded') === 'true');

    toggleButton.off('click' + namespace).on('click' + namespace, function (e) {
      e.preventDefault();
      e.stopPropagation();

      setExpandedState(toggleButton.attr('aria-expanded') !== 'true');
    });
  }

  function initWooExportToggle(table) {
    var tableWrapper = table.closest('.supsystic-tables-wrap'),
      toggleWrap = tableWrapper.find('.stbWooExportToggle');

    if (!toggleWrap.length) {
      return;
    }

    var toggleButton = toggleWrap.find('.stbWooExportToggleButton'),
      toggleDropdown = toggleWrap.find('.stbWooExportToggleDropdown'),
      namespace = '.dtgWooExportToggle' + getWooColumnToggleKey(table);

    bindWooMobileDropdownViewportOffset(toggleWrap, toggleButton, namespace);

    if (!toggleWrap.find('.stbWooExportToggleItem').length) {
      toggleWrap.addClass('stHidden');
      return;
    }

    toggleButton.off('click' + namespace).on('click' + namespace, function (e) {
      e.preventDefault();
      e.stopPropagation();

      var expanded = toggleButton.attr('aria-expanded') === 'true';
      if (!expanded) {
        closeWooToggleDropdowns(tableWrapper, toggleWrap);
      }
      updateWooMobileDropdownViewportOffset(toggleWrap, toggleButton);
      toggleButton.attr('aria-expanded', expanded ? 'false' : 'true');
      toggleDropdown.toggleClass('stHidden', expanded);
    });

    toggleWrap.off('click' + namespace, '.stbWooExportToggleItem').on('click' + namespace, '.stbWooExportToggleItem', function () {
      toggleButton.attr('aria-expanded', 'false');
      toggleDropdown.addClass('stHidden');
    });

    toggleWrap
      .find('.export-csv, .export-xls, .export-xlsx')
      .off('click.dtgWooFrontendFileExport')
      .on('click.dtgWooFrontendFileExport', function (e) {
        var exportLink = $(this),
          exportType = exportLink.attr('data-export-type') || '';

        if (!exportType) {
          return false;
        }

        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
          e.stopImmediatePropagation();
        }

        toggleButton.attr('aria-expanded', 'false');
        toggleDropdown.addClass('stHidden');
        exportWooVisibleTable(tableWrapper, exportType);

        return false;
      });

    $(document).off('click' + namespace).on('click' + namespace, function (e) {
      if ($(e.target).closest('.stbWooExportToggle').length) {
        return;
      }

      toggleButton.attr('aria-expanded', 'false');
      toggleDropdown.addClass('stHidden');
    });
  }

  function initWooCategoryListFilter(table) {
    var tableWrapper = table.closest('.supsystic-tables-wrap'),
      filterWrap = tableWrapper.find('.stbWooCategoryFilter').first(),
      storedFilterFn = tableWrapper.data('dtgWooCategoryFilterFn'),
      extSearch = $.fn.dataTable.ext.search;

    if (storedFilterFn) {
      var existingIndex = $.inArray(storedFilterFn, extSearch);
      if (existingIndex !== -1) {
        extSearch.splice(existingIndex, 1);
      }
      tableWrapper.removeData('dtgWooCategoryFilterFn');
    }

    if (!filterWrap.length) {
      return;
    }

    var api = table.api(),
      tableNode = table.get(0),
      toggleButton = filterWrap.find('.stbWooCategoryFilterButton'),
      dropdown = filterWrap.find('.stbWooCategoryFilterDropdown'),
      toggleAllButton = filterWrap.find('.stbWooCategoryFilterToggleAll'),
      namespace = '.dtgWooCategoryFilter' + getWooColumnToggleKey(table),
      applyCategoryFilter = function () {
        api.draw();
      },
      setExpandedState = function (expanded, applyOnClose) {
        var wasExpanded = toggleButton.attr('aria-expanded') === 'true';

        updateWooMobileDropdownViewportOffset(filterWrap, toggleButton);
        toggleButton.attr('aria-expanded', expanded ? 'true' : 'false');
        dropdown.toggleClass('stHidden', !expanded);

        if (!expanded && wasExpanded && applyOnClose) {
          applyCategoryFilter();
        }
      },
      filterFn = function (settings, data, dataIndex) {
        if (settings.nTable !== tableNode || isWooServerSideTable(table)) {
          return true;
        }

        var selection = getWooCategoryFilterSelection(tableWrapper);
        if (!selection.total || selection.selected.length === selection.total) {
          return true;
        }
        if (!selection.selected.length) {
          return false;
        }

        var aoData = settings.aoData && settings.aoData[dataIndex] ? settings.aoData[dataIndex] : null,
          rowNode = aoData && aoData.nTr ? aoData.nTr : null,
          row = rowNode ? $(rowNode) : $(),
          rowMeta = row.find('.stbWooRowMeta').first(),
          rawCategories = row.attr('data-woo-categories') || (rowMeta.length ? rowMeta.attr('data-woo-categories') : '') || '',
          rowCategories = [];

        if (rawCategories) {
          try {
            rowCategories = JSON.parse(rawCategories) || [];
          } catch (e) {
            rowCategories = [];
          }
        }

        rowCategories = $.map(rowCategories, function (value) {
          value = parseInt(value, 10);
          return isNaN(value) ? null : value;
        });

        if (!rowCategories.length) {
          return false;
        }

        for (var i = 0; i < selection.selected.length; i++) {
          if ($.inArray(selection.selected[i], rowCategories) !== -1) {
            return true;
          }
        }

        return false;
      };

    extSearch.push(filterFn);
    tableWrapper.data('dtgWooCategoryFilterFn', filterFn);
    bindWooMobileDropdownViewportOffset(filterWrap, toggleButton, namespace);

    filterWrap.find('.stbWooCategoryFilterItem').each(function () {
      syncWooCategoryFilterItemState($(this), $(this).find('.stbWooCategoryFilterCheckbox').is(':checked'));
    });

    toggleButton.off('click' + namespace).on('click' + namespace, function (e) {
      e.preventDefault();
      e.stopPropagation();

      if (toggleButton.attr('aria-expanded') !== 'true') {
        closeWooToggleDropdowns(tableWrapper, filterWrap);
      }
      setExpandedState(toggleButton.attr('aria-expanded') !== 'true', true);
    });

    filterWrap.off('click' + namespace, '.stbWooCategoryFilterItem').on('click' + namespace, '.stbWooCategoryFilterItem', function (e) {
      e.preventDefault();
      e.stopPropagation();

      var item = $(this),
        checkbox = item.find('.stbWooCategoryFilterCheckbox'),
        checked = !checkbox.is(':checked');

      syncWooCategoryFilterItemState(item, checked);
    });

    toggleAllButton.off('click' + namespace).on('click' + namespace, function (e) {
      e.preventDefault();
      e.stopPropagation();

      var items = filterWrap.find('.stbWooCategoryFilterItem'),
        shouldCheckAll = items.filter(function () {
          return !$(this).find('.stbWooCategoryFilterCheckbox').is(':checked');
        }).length > 0;

      items.each(function () {
        syncWooCategoryFilterItemState($(this), shouldCheckAll);
      });
    });

    dropdown.off('mouseleave' + namespace).on('mouseleave' + namespace, function () {
      if (toggleButton.attr('aria-expanded') === 'true') {
        setExpandedState(false, true);
      }
    });

    $(document).off('click' + namespace).on('click' + namespace, function (e) {
      if ($(e.target).closest('.stbWooCategoryFilter').length) {
        return;
      }

      setExpandedState(false, true);
    });
  }

  app.getServerSideSearchParams = function (table, searchParams) {
    var params = $.extend(true, {}, searchParams || {}),
      wooFilters = getWooServerSideFilters(table);

    if (wooFilters.length) {
      params.wooFilters = wooFilters;
    } else if (typeof params.wooFilters !== 'undefined') {
      delete params.wooFilters;
    }

    return params;
  };
  app.setTableAddFilters = function (table) {
    var self = this,
      tableWrapper = table.closest('.supsystic-tables-wrap');

    initWooExportToggle(table);
    initWooColumnVisibilityToggle(table);
    initWooFiltersToggle(table);
    initWooCategoryListFilter(table);
    initWooStockStatusFilter(table);
    initWooAttributeFilters(table);
    initWooPriceFilter(table);
    initWooPriceColumnSearch(table);
    initWooColumnSearchVisibility(table);

    var filtersInput = tableWrapper.find('.stbWooFilterWrapper select');
    filtersInput.each(function () {
      var oControl = $(this),
        filterType = oControl.attr('data-filter-type') || '',
        tableId = table.attr('id'),
        columns = table.api().settings()[0].aoColumns,
        keys = oControl.attr('data-column-keys'),
        columnIds = typeof keys !== 'undefined' ? keys.split(',').map(Number) : [],
        serverSide = isWooServerSideTable(table);

      if (filterType === 'stock_status' || filterType === 'attribute_term') {
        oControl.off('change.dtg').on('change.dtg', function () {
          table.api().draw();
        });
        return;
      }

      if (columnIds.length > 0) {
        oControl.off('change.dtg').on('change.dtg', function () {
          var value = $(this).val();
          if (serverSide) {
            $.each(columnIds, function (_, numColumn) {
              getWooColumnSearchInputs(tableWrapper, numColumn).each(function () {
                $(this).val('');
              });
              table.api().column(numColumn).search('');
            });
            table.api().draw();
            return;
          }
          for (var i = 0; i < columnIds.length; i++) {
            var numColumn = columnIds[i],
              column = table.api().column(numColumn);
            if (column.search() !== value) {
              if (value != '') {
                getWooColumnSearchInputs(tableWrapper, numColumn).each(function () {
                  $(this).val('');
                });
              }
              column.search(value).draw();
              setTimeout(function () {
                column.draw();
              }, 50);
              return;
            }
          }
        });
      }
    });

    tableWrapper
      .find('.stbResetWooFilters')
      .off('click')
      .on('click', function (e) {
        e.preventDefault();
        self.resetTableAddFilters(table);
      });
  };

  app.resetTableAddFilters = function (table) {
    var doDraw = false,
      tableWrapper = table.closest('.supsystic-tables-wrap');

    tableWrapper.find('.stbWooCategoryFilter').each(function () {
      var filterWrap = $(this),
        items = filterWrap.find('.stbWooCategoryFilterItem'),
        selection = getWooCategoryFilterSelection(tableWrapper);

      if (selection.total && selection.selected.length !== selection.total) {
        items.each(function () {
          syncWooCategoryFilterItemState($(this), true);
        });
        doDraw = true;
      }
    });

    tableWrapper
      .find('.stbWooFilterWrapper select')
      .each(function () {
        var select = $(this),
          firstVal = select.find('option:first').val();
        if (select.val() != firstVal) {
          select.val(firstVal).trigger('change.dtg');
          doDraw = true;
        }
      });

    tableWrapper.find('.stWooFilterPrice[data-filter-type="price_range"]').each(function () {
      var priceFilter = $(this);

      syncWooPriceFilterUi(priceFilter, {
        min: null,
        max: null,
      });
      storeWooPriceFilterState(tableWrapper, {
        min: null,
        max: null,
      });
      doDraw = true;
    });

    tableWrapper.find('.stWooColumnPriceSearch').each(function () {
      var priceSearch = $(this);

      priceSearch.find('.stWooColumnPriceSearchMin, .stWooColumnPriceSearchMax').val('');
      storeWooPriceColumnSearchState(tableWrapper, {
        min: null,
        max: null,
      });
      doDraw = true;
    });

    if (doDraw) {
      table.api().draw();
    }
  };

  app._beforeShowWooTable = function ($table) {
    var self = this,
      tableWrapper = $table.closest('.supsystic-tables-wrap'),
      addonData = tableWrapper.find('.supsystic-tables-addon');
    if (addonData.length == 0) return;

    var beforeBlock = addonData.find('.stb-before-woo'),
      caption = tableWrapper.find('.supsystic-table-caption:first');
    if (beforeBlock.length) {
      if (caption.length) {
        caption.after(beforeBlock);
      } else {
        tableWrapper.prepend(beforeBlock);
      }
    }
  };

  app._initTablesOnPageWoo = function (id) {
    var supsysticTables = $(typeof id != 'undefined' ? '#supsystic-table-' + id + ':not(.dataTable)' : '.supsystic-table');

    supsysticTables.each(function () {
      var $curTable = $(this);

      $curTable.on('beforeShowTable', function () {
        app._beforeShowWooTable($curTable);
      });
    });
  };
})(window.jQuery, window.supsystic.Tables);
