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
              td.find('del').remove();
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
    return table.closest('.supsystic-tables-wrap').find('.stbWooFilterWrapper select').length > 0;
  };

  function isWooServerSideTable(table) {
    var apiSettings = table.api().settings()[0];

    return table.attr('data-server-side-processing') === 'on' || (apiSettings && apiSettings.oFeatures && apiSettings.oFeatures.bServerSide);
  }

  function getWooServerSideFilters(table) {
    var filters = [];

    table
      .closest('.supsystic-tables-wrap')
      .find('.stbWooFilterWrapper select')
      .each(function () {
        var select = $(this),
          value = select.val(),
          keys = select.attr('data-column-keys'),
          columns = typeof keys !== 'undefined' ? keys.split(',') : [];

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

    return filters;
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

    var filtersInput = tableWrapper.find('.stbWooFilterWrapper select');
    filtersInput.each(function () {
      var oControl = $(this),
        tableId = table.attr('id'),
        columns = table.api().settings()[0].aoColumns,
        keys = oControl.attr('data-column-keys'),
        columnIds = typeof keys !== 'undefined' ? keys.split(',').map(Number) : [],
        serverSide = isWooServerSideTable(table);
      if (columnIds.length > 0) {
        oControl.off('change.dtg').on('change.dtg', function () {
          var value = $(this).val();
          if (serverSide) {
            $.each(columnIds, function (_, numColumn) {
              tableWrapper.find('.stbColumnsSearchWrapper .search-column[data-column-num="' + numColumn + '"]').each(function () {
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
                tableWrapper.find('.stbColumnsSearchWrapper .search-column[data-column-num="' + numColumn + '"]').each(function () {
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
    var doDraw = false;
    table
      .closest('.supsystic-tables-wrap')
      .find('.stbWooFilterWrapper select')
      .each(function () {
        var select = $(this),
          firstVal = select.find('option:first').val();
        if (select.val() != firstVal) {
          select.val(firstVal).trigger('change.dtg');
          doDraw = true;
        }
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
