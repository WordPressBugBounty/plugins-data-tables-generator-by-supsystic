(function ($, app) {
  $(document).ready(function () {
    var tablesModel = app.Models.Tables,
      $mainTabsContent = $('.row-tab'),
      $mainTabs = $('.subsubsub.tabs-wrapper .button');

    g_stbDoSaving = false;
    window.g_stbIsDataEdited = window.g_stbIsDataEdited || { settings: false, source: false, history: false, woocommerce: false, data: false };
    g_stbIsDataEdited = window.g_stbIsDataEdited;

    function activateTab($tab) {
      var target = $tab.attr('href');

      if (!target) {
        return;
      }

      $mainTabs.removeClass('current');
      $mainTabsContent.removeClass('active');
      $tab.addClass('current');
      $mainTabsContent.filter(target).addClass('active');
    }

    function setSaveButtonDirty() {
      g_stbIsDataEdited.woocommerce = true;
      $('#buttonSave').prop('disabled', false);
    }

    function toggleSettingGroup(selector, isVisible) {
      var $group = $(selector);

      if (!$group.length) {
        return;
      }

      $group.stop(true, true)[isVisible ? 'slideDown' : 'slideUp'](150);
    }

    // The preview of classic tables: the table is saved and rendered as on the site
    function initWooPreview() {
      var $card = $('#stb-preview-card'),
        $preview = $('#table-preview'),
        $start = $('#loadPreviewBtn, #loadPreviewAutoBtn'),
        autoload = false,
        timer = null;

      if (!$card.length) {
        return;
      }

      function refresh() {
        tablesModel.saveTable('#table-preview', 2);
        $preview.show();
      }

      function onOptionsChanged() {
        if (autoload) {
          window.clearTimeout(timer);
          timer = window.setTimeout(refresh, 400);
          return;
        }
        if ($preview.children().length) {
          $preview.html('').hide();
          $start.fadeIn();
        }
      }

      $start.on('click', function () {
        autoload = this.id === 'loadPreviewAutoBtn';
        refresh();
        $start.fadeOut();
      });
      $start.on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          $(this).trigger('click');
        }
      });

      $('#woocommerce-settings').on('change ifChanged sortupdate', ':input', function () {
        // Searching in the lists of products and palettes changes nothing in the table
        if (this.id === 'chooseColumns' || $(this).closest('.dataTables_wrapper, .woo-exclude-search-wrap, .stb-palette-tools, .stb-settings-nav').length) {
          return;
        }
        onOptionsChanged();
      });
      $(document.body).on('changeOrderPosition', onOptionsChanged);

      $card.find('.stb-device-switch .stb-anchor-nav-links').on('click', function (e) {
        var href = $(this).attr('href'),
          profile = href === '#stb-style-tablet' ? 'tablet' : href === '#stb-style-mobile' ? 'mobile' : 'desktop';

        e.preventDefault();
        $card.find('.stb-device-switch .stb-anchor-nav-links').removeClass('active');
        $(this).addClass('active');
        g_stbMobilePreview = profile !== 'desktop';
        $preview
          .css('max-width', profile === 'tablet' ? '768px' : profile === 'mobile' ? '380px' : 'none')
          .attr('data-responsive-column-profile', profile)
          .find('table.supsystic-table')
          .attr('data-responsive-column-preview-profile', profile);
        if ($preview.find('table').length) {
          tablesModel.reinitPreview($preview);
        }
      });
    }

    function initWooLoaderControls() {
      var $dialog = $('#tableLoaderIconDialog'),
        $button = $('.selectTableLoaderIcon'),
        $iconName = $('input[name="tableLoader[iconName]"]'),
        $iconItems = $('input[name="tableLoader[iconItems]"]'),
        $iconPreview = $('#tableLoaderIconPreview'),
        $colorInput = $('#woo-table-loader-color');

      function renderPreview(iconName, iconItems, color) {
        var itemsHtml = '',
          i = 0;

        $iconPreview.empty();
        if (iconName === 'default') {
          $iconPreview.append('<div class="supsystic-table-loader spinner" style="background-color:' + color + '"></div>');
          return;
        }

        for (i = 0; i < iconItems; i++) {
          itemsHtml += '<div></div>';
        }

        $iconPreview.append('<div class="supsystic-table-loader la-' + iconName + ' la-2x" style="color:' + color + '">' + itemsHtml + '</div>');
      }

      if (!$dialog.length || !$button.length || !$iconPreview.length) {
        return;
      }

      $dialog.dialog({
        autoOpen: false,
        modal: true,
        width: 900,
        dialogClass: 'stb-modal stb-modal-loader stb-modal-plain',
        open: function () {
          var color = $colorInput.val() || '#000000';

          $dialog.find('.preicon_img').css('color', color);
          $dialog.find('.preicon_img .spinner').css('backgroundColor', color);
        },
        buttons: {
          Cancel: function () {
            $(this).dialog('close');
          },
        },
      });

      $button.on('click', function (e) {
        e.preventDefault();
        $dialog.dialog('open');
      });

      $dialog.on('click', '.item-inner', function () {
        var $icon = $(this).find('.preicon_img'),
          color = $colorInput.val() || '#000000',
          name = String($icon.data('name') || 'default'),
          items = parseInt($icon.data('items'), 10);

        items = isNaN(items) ? 0 : items;
        $iconName.val(name);
        $iconItems.val(items);
        renderPreview(name, items, color);
        $dialog.dialog('close');
        setSaveButtonDirty();
      });

      $colorInput.on('change input', function () {
        var color = $(this).val() || '#000000',
          name = String($iconName.val() || 'default'),
          items = parseInt($iconItems.val(), 10);

        items = isNaN(items) ? 0 : items;
        renderPreview(name, items, color);
      });
    }

    function initWooSettingsToggles() {
      var $ordering = $('#woo-features-ordering'),
        $pagination = $('#woo-features-pagination'),
        $searching = $('#woo-features-searching'),
        $columnSearch = $('#woo-features-search-by-column'),
        $resultOnly = $('#woo-searching-result-only'),
        $autoWidth = $('#woo-features-auto-width'),
        $tableWidthType = $('input[name="tableWidthType"]'),
        $tableWidthMobileType = $('input[name="tableWidthMobileType"]'),
        $loaderDisabled = $('#woo-hide-table-loader');

      function syncOrdering() {
        toggleSettingGroup('.woo-sorting-options', $ordering.is(':checked'));
      }

      function syncPagination() {
        toggleSettingGroup('.woo-pagination-options', $pagination.is(':checked'));
      }

      function syncSearching() {
        var isSearchingEnabled = $searching.is(':checked');

        toggleSettingGroup('.woo-searching-options', isSearchingEnabled);
        toggleSettingGroup('.woo-searching-column-options', isSearchingEnabled && $columnSearch.is(':checked'));
        toggleSettingGroup('.woo-searching-result-options', isSearchingEnabled && $resultOnly.is(':checked'));
      }

      function syncWidthInputs() {
        var selectedWidthType = $tableWidthType.filter(':checked').val() || '%',
          selectedMobileWidthType = $tableWidthMobileType.filter(':checked').val() || '%',
          showWidthOptions = !$autoWidth.is(':checked');

        toggleSettingGroup('.woo-width-options', showWidthOptions);
        $('.woo-table-width-input').toggle(showWidthOptions && selectedWidthType !== 'auto');
        $('.woo-table-width-mobile-input').toggle(showWidthOptions && selectedMobileWidthType !== 'auto');
      }

      function syncLoader() {
        toggleSettingGroup('.woo-loader-options', !$loaderDisabled.is(':checked'));
      }

      $ordering.on('change ifChanged', syncOrdering);
      $pagination.on('change ifChanged', syncPagination);
      $searching.on('change ifChanged', syncSearching);
      $columnSearch.on('change ifChanged', syncSearching);
      $resultOnly.on('change ifChanged', syncSearching);
      $autoWidth.on('change ifChanged', syncWidthInputs);
      $tableWidthType.on('change ifChecked', syncWidthInputs);
      $tableWidthMobileType.on('change ifChecked', syncWidthInputs);
      $loaderDisabled.on('change ifChanged', syncLoader);

      syncOrdering();
      syncPagination();
      syncSearching();
      syncWidthInputs();
      syncLoader();
    }

    function downloadJson(filename, payload) {
      var blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' }),
        url = window.URL.createObjectURL(blob),
        link = document.createElement('a');

      link.href = url;
      link.download = filename || 'woo-product-table-settings.json';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    }

    function exportWooSettings() {
      var $button = $('#export');

      app.createSpinner($button);
      app
        .request(
          {
            module: 'woocommerce',
            action: 'exportSettings',
            nonce: DTGS_NONCE,
          },
          {
            id: app.getParameterByName('id'),
          }
        )
        .done(function (response) {
          downloadJson(response.filename, response.payload);
        })
        .fail(function (message) {
          alert(message);
        })
        .always(function () {
          app.deleteSpinner($button);
        });
    }

    function importWooSettings(file) {
      var reader = new FileReader(),
        $button = $('#import');

      reader.onload = function (event) {
        app.createSpinner($button);
        app
          .request(
            {
              module: 'woocommerce',
              action: 'importSettings',
              nonce: DTGS_NONCE,
            },
            {
              id: app.getParameterByName('id'),
              settings: event.target.result,
            }
          )
          .done(function () {
            window.location.reload();
          })
          .fail(function (message) {
            alert(message);
          })
          .always(function () {
            app.deleteSpinner($button);
          });
      };
      reader.readAsText(file);
    }

    $mainTabs.on('click', function (e) {
      e.preventDefault();
      activateTab($(this));
    });

    activateTab($mainTabs.filter('.current').length ? $mainTabs.filter('.current').first() : $mainTabs.first());

    if ($.fn.tooltipster) {
      $('[data-toggle="tooltip"]').tooltipster();
    }

    $('#stbCopyTextCodeExamples')
      .on('change', function () {
        $('.stbCopyTextCodeShowBlock')
          .hide()
          .filter('[data-for="' + $(this).val() + '"]')
          .show();
      })
      .trigger('change');

    $('#stbTableTitleShell').on('click', function () {
      var $shell = $(this),
        $labelHtml = $('#stbTableTitleLabel'),
        $labelTxt = $('#stbTableTitleTxt');

      if ($shell.data('edit-on')) {
        return;
      }

      $labelTxt.val($labelHtml.text());
      $labelHtml.hide(g_stbAnimationSpeed);
      $labelTxt.show(g_stbAnimationSpeed, function () {
        $(this).data('ready', 1);
      });
      $shell.data('edit-on', 1);
    });

    $('#stbTableTitleTxt')
      .on('blur', function () {
        tablesModel.renameTable($(this).val());
      })
      .on('keydown', function (e) {
        if (e.keyCode === 13) {
          tablesModel.renameTable($(this).val());
        }
      });

    var $cloneDialog = $('#cloneDialog');
    if ($cloneDialog.length) {
      $cloneDialog.dialog({
        autoOpen: false,
        width: 480,
        modal: true,
        dialogClass: 'stb-modal stb-modal-clone',
        open: function () {
          var dialog = $(this);
          dialog.find('.message').remove();
          dialog.find('.input-group').show();
          dialog.find('input').val($.trim($('#stbTableTitleLabel').text()) + '_Clone');
          dialog.next().find('button:first-of-type').removeAttr('disabled').html('Clone').show();
        },
        buttons: {
          Clone: function (e) {
            var $dialog = $(this),
              $button = $(e.target).closest('button');

            $button.attr('disabled', true).html(app.createSpinner());
            tablesModel
              .request('cloneTable', {
                id: app.getParameterByName('id'),
                title: $dialog.find('input').val(),
              })
              .done(function (response) {
                if (response.success) {
                  var html = '<a href="' + app.replaceParameterByName(window.location.href, 'id', response.id) + '" class="ui-button" style="text-decoration: none !important;">Open cloned table</a><div style="float: right; margin-top: 5px;">Done!</div>';

                  $button.hide();
                  $dialog.find('.input-group').hide();
                  $dialog.find('.input-group').after($('<div>', { class: 'message', html: html }));
                }
              })
              .fail(function (message) {
                $button.removeAttr('disabled').html('Clone');
                alert(message);
              });
          },
          Cancel: function () {
            $(this).dialog('close');
          },
        },
      });
    }

    $('#buttonClone').on('click', function () {
      $cloneDialog.dialog('open');
    });

    $('#buttonSave').prop('disabled', false).on('click', function () {
      tablesModel.saveTable();
    });

    $('#export, #import').removeClass('pro-notify').removeAttr('data-dialog data-dtitle data-dwidth');
    $('#export').on('click', function (e) {
      e.preventDefault();
      exportWooSettings();
    });
    $('#import').on('click', function (e) {
      var input = document.createElement('input');

      e.preventDefault();
      input.type = 'file';
      input.accept = '.json,application/json';
      input.onchange = function () {
        if (input.files && input.files[0]) {
          importWooSettings(input.files[0]);
        }
      };
      input.click();
    });

    $('#buttonDelete').on('click', function () {
      var $button = $(this);

      if (!confirm('Are you sure you want to delete the this table?')) {
        return;
      }

      app.createSpinner($button);
      tablesModel
        .remove(app.getParameterByName('id'))
        .done(function () {
          window.location.href = $('#menuItem_tables').attr('href');
        })
        .fail(function (error) {
          alert('Failed to delete table: ' + error);
        })
        .always(function () {
          app.deleteSpinner($button);
        });
    });

    $('#buttonClearData').on('click', function () {
      if (!confirm('Are you sure you want to clear all products from this table?')) {
        return;
      }

      $('input[name="woocommerce[order]"]').val('');
      $('input[name="woocommerce[productids]"]').val('');
      setSaveButtonDirty();
    });

    initWooLoaderControls();
    initWooSettingsToggles();
    initWooPreview();

    $('#woocommerce-settings').on('change ifChanged sortupdate', ':input', setSaveButtonDirty);
    $(document.body).on('changeOrderPosition', setSaveButtonDirty);
  });
})(window.jQuery, window.supsystic.Tables);
