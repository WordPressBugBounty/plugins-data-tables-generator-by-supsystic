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

    function initWooSettingsSidebar() {
      var $wrap = $('.woo-settings-scroll'),
        $links = $('.woo-settings-sidebar .stb-anchor-nav-links'),
        $sections = $wrap.find('> .settings-blocks > .supRow-settings-block');

      if (!$wrap.length || !$links.length || !$sections.length) {
        return;
      }

      function setActiveLink() {
        var currentId = '#' + $sections.first().attr('id'),
          currentScroll = $wrap.scrollTop();

        $sections.each(function () {
          if (this.offsetTop <= currentScroll + 40) {
            currentId = '#' + this.id;
          }
        });

        $links.removeClass('active');
        $links.filter('[href="' + currentId + '"]').addClass('active');
        $('#slimScrollStartPos').val(currentScroll);
      }

      $links.on('click', function (e) {
        var $target = $sections.filter($(this).attr('href'));

        e.preventDefault();
        if (!$target.length) {
          return;
        }

        $links.removeClass('active');
        $(this).addClass('active');
        $wrap.stop(true).animate(
          {
            scrollTop: Math.max(0, $target.get(0).offsetTop - 16),
          },
          180,
          setActiveLink
        );
      });

      $wrap.on('scroll', setActiveLink);
      setTimeout(setActiveLink, 0);
    }


    function initWooStickySidebar() {
      var $window = $(window),
        $wooTab = $('#row-tab-woocommerce'),
        $layout = $wooTab.find('.woo-settings-layout'),
        $sidebar = $layout.find('.woo-settings-sidebar').first(),
        $inner = $sidebar.find('.woo-settings-sidebar-inner').first(),
        stickyTop = 20,
        resizeTimer = null;

      if (!$wooTab.length || !$layout.length || !$sidebar.length || !$inner.length) {
        return;
      }

      function resetStickyState() {
        $sidebar.removeClass('is-fixed is-stopped').css('min-height', '');
        $inner.css({
          width: '',
          left: '',
          top: '',
        });
      }

      function updateStickyState() {
        var isDesktop = window.matchMedia ? window.matchMedia('(min-width: 961px)').matches : $window.width() > 960,
          isVisible = $wooTab.hasClass('active') && $wooTab.is(':visible'),
          sidebarOffset,
          layoutOffset,
          sidebarRect,
          innerHeight,
          layoutHeight,
          startFixAt,
          stopFixAt,
          windowTop;

        if (!isDesktop || !isVisible) {
          resetStickyState();
          return;
        }

        sidebarOffset = $sidebar.offset();
        layoutOffset = $layout.offset();
        sidebarRect = $sidebar.get(0).getBoundingClientRect();
        innerHeight = $inner.outerHeight(true);
        layoutHeight = $layout.outerHeight(true);

        if (!sidebarOffset || !layoutOffset || !innerHeight || !layoutHeight) {
          resetStickyState();
          return;
        }

        startFixAt = sidebarOffset.top - stickyTop;
        stopFixAt = layoutOffset.top + layoutHeight - innerHeight - stickyTop;
        windowTop = $window.scrollTop();

        $sidebar.css('min-height', innerHeight);

        if (windowTop <= startFixAt) {
          resetStickyState();
          return;
        }

        if (windowTop >= stopFixAt) {
          $sidebar.removeClass('is-fixed').addClass('is-stopped');
          $inner.css({
            width: $sidebar.outerWidth(),
            left: '',
            top: '',
          });
          return;
        }

        $sidebar.removeClass('is-stopped').addClass('is-fixed');
        $inner.css({
          width: $sidebar.outerWidth(),
          left: sidebarRect.left,
          top: stickyTop,
        });
      }

      function queueStickyUpdate() {
        if (resizeTimer) {
          window.clearTimeout(resizeTimer);
        }

        resizeTimer = window.setTimeout(updateStickyState, 20);
      }

      $window.on('scroll.wooStickySidebar resize.wooStickySidebar', queueStickyUpdate);
      $(document.body).on('draw.dt.wooStickySidebar changeOrderPosition.wooStickySidebar', queueStickyUpdate);
      $('.subsubsub.tabs-wrapper .button[href="#row-tab-woocommerce"]').on('click.wooStickySidebar', function () {
        window.setTimeout(updateStickyState, 20);
      });

      window.setTimeout(updateStickyState, 0);
      window.setTimeout(updateStickyState, 300);
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

    initWooSettingsSidebar();

    initWooStickySidebar();

    initWooLoaderControls();
    initWooSettingsToggles();

    $('#woocommerce-settings').on('change ifChanged sortupdate', ':input', setSaveButtonDirty);
    $(document.body).on('changeOrderPosition', setSaveButtonDirty);
  });
})(window.jQuery, window.supsystic.Tables);
