(function ($, app) {
  'use strict';

  $(function () {
    var $section = $('#dtgs-tables-list');

    if (!$section.length) {
      return;
    }

    var $table = $('#dtgs-tables-table'),
      $tbody = $('#dtgs-tables-tbody'),
      $checkAll = $('#dtgs-check-all'),
      $deleteSelected = $('#ddtTableRemoveGroupBtn'),
      $exportSelected = $('#export-group'),
      $status = $('#dtgs-list-status'),
      state = {
        page: parseInt($section.attr('data-page'), 10) || 1,
        perPage: parseInt($section.attr('data-per-page'), 10) || 20,
        sort: $section.attr('data-sort') || 'id',
        dir: $section.attr('data-dir') === 'asc' ? 'asc' : 'desc',
        recordsTotal: parseInt($section.attr('data-records-total'), 10) || 0,
        search: '',
      },
      searchTimer = null,
      activeRequest = null,
      requestSequence = 0;

    function totalPages() {
      return Math.max(1, Math.ceil(state.recordsTotal / state.perPage));
    }

    function setStatus(message, type) {
      $status.removeClass('is-error is-visible').text(message || '');
      if (message) {
        $status.addClass('is-visible');
        if (type === 'error') {
          $status.addClass('is-error');
        }
      }
    }

    function normalizeNativeCheckboxes($context) {
      var $checkboxes = $context.find('.dtgs-native-check').addBack('.dtgs-native-check');

      $checkboxes.each(function () {
        var $input = $(this);
        if ($input.parent().hasClass('icheckbox_minimal') && $.fn.iCheck) {
          $input.iCheck('destroy');
        }
        $input.addClass('dtgs-native-check');
      });
    }

    function updateSortHeaders() {
      $table.find('.dtgs-sortable').each(function () {
        var $header = $(this),
          key = $header.attr('data-sort-key'),
          $icon = $header.find('.dtgs-sort-icon');

        $header.removeClass('dtgs-sort-active').attr('aria-sort', 'none');
        $icon.removeClass('fa-sort-asc fa-sort-desc').addClass('fa-sort');

        if (key === state.sort) {
          $header
            .addClass('dtgs-sort-active')
            .attr('aria-sort', state.dir === 'asc' ? 'ascending' : 'descending');
          $icon.removeClass('fa-sort').addClass(state.dir === 'asc' ? 'fa-sort-asc' : 'fa-sort-desc');
        }
      });
    }

    function getPageItems(pages, current) {
      var items = [],
        page;

      if (pages <= 7) {
        for (page = 1; page <= pages; page += 1) {
          items.push(page);
        }
        return items;
      }

      items.push(1);
      if (current > 3) {
        items.push('start-ellipsis');
      }
      for (page = Math.max(2, current - 1); page <= Math.min(pages - 1, current + 1); page += 1) {
        items.push(page);
      }
      if (current < pages - 2) {
        items.push('end-ellipsis');
      }
      items.push(pages);
      return items;
    }

    function updatePagination() {
      var pages = totalPages(),
        from = state.recordsTotal ? (state.page - 1) * state.perPage + 1 : 0,
        to = Math.min(state.page * state.perPage, state.recordsTotal),
        $numbers = $('#dtgs-page-numbers').empty();

      $('#dtgs-pagination-info').text(from + '–' + to + ' / ' + state.recordsTotal);

      $.each(getPageItems(pages, state.page), function (_, item) {
        if (typeof item === 'string') {
          $('<span class="dtgs-page-ellipsis" aria-hidden="true">…</span>').appendTo($numbers);
          return;
        }

        $('<button type="button" class="dtgs-page-number"></button>')
          .text(item)
          .toggleClass('dtgs-page-active', item === state.page)
          .attr({
            'data-page': item,
            'aria-label': 'Page ' + item,
            'aria-current': item === state.page ? 'page' : null,
          })
          .appendTo($numbers);
      });

      $section.find('.dtgs-page-btn[data-page-action="prev"]').prop('disabled', state.page <= 1);
      $section.find('.dtgs-page-btn[data-page-action="next"]').prop('disabled', state.page >= pages);
      $('#dtgs-per-page').val(String(state.perPage));
    }

    function selectedIds() {
      return $tbody
        .find('.dtgs-row-checkbox:checked')
        .map(function () {
          return parseInt($(this).attr('data-table-id'), 10);
        })
        .get()
        .filter(function (id) {
          return id > 0;
        });
    }

    function updateSelection() {
      var selected = selectedIds().length,
        available = $tbody.find('.dtgs-row-checkbox').length,
        allSelected = available > 0 && selected === available;

      $checkAll.prop({
        checked: allSelected,
        indeterminate: selected > 0 && !allSelected,
      });
      $deleteSelected.prop('disabled', selected === 0);
      $exportSelected.prop('disabled', selected === 0);
    }

    function resetSelection() {
      $checkAll.prop({ checked: false, indeterminate: false });
      $deleteSelected.prop('disabled', true);
      $exportSelected.prop('disabled', true);
    }

    function setLoading(loading) {
      $section.toggleClass('is-loading', loading);
      $table.attr('aria-busy', loading ? 'true' : 'false');
      $section.find('button, select, input[type="search"]').prop('disabled', loading);

      if (!loading) {
        $('#ddtTableTblSearchTxt').prop('disabled', false);
        $('#dtgs-per-page').prop('disabled', false);
        $('#import-group').prop('disabled', false);
        updateSelection();
        updatePagination();
      }
    }

    function fetchAndRender() {
      var sequence = ++requestSequence;

      if (activeRequest && activeRequest.readyState !== 4) {
        activeRequest.abort();
      }

      setLoading(true);
      setStatus('Loading tables…');

      activeRequest = $.ajax({
        url: window.ajaxurl,
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'supsystic-tables',
          route: {
            module: 'tables',
            action: 'tablesData',
            nonce: window.DTGS_NONCE,
          },
          page: state.page,
          perPage: state.perPage,
          sort: state.sort,
          dir: state.dir,
          search: state.search,
        },
      })
        .done(function (response) {
          if (sequence !== requestSequence) {
            return;
          }
          if (!response || response.success !== true) {
            setStatus((response && response.message) || 'Could not load the tables list.', 'error');
            return;
          }

          state.recordsTotal = parseInt(response.recordsTotal, 10) || 0;
          state.page = parseInt(response.page, 10) || 1;
          state.perPage = parseInt(response.perPage, 10) || state.perPage;
          state.sort = response.sort || state.sort;
          state.dir = response.dir === 'asc' ? 'asc' : 'desc';
          $tbody.html(response.html || '');
          normalizeNativeCheckboxes($tbody);
          resetSelection();
          updateSortHeaders();
          updatePagination();
          setStatus('');
        })
        .fail(function (xhr, status) {
          if (status !== 'abort' && sequence === requestSequence) {
            setStatus('Could not load the tables list. Please try again.', 'error');
          }
        })
        .always(function () {
          if (sequence === requestSequence) {
            setLoading(false);
          }
        });
    }

    function applySort($header) {
      var key = $header.attr('data-sort-key');

      if (state.sort === key) {
        state.dir = state.dir === 'asc' ? 'desc' : 'asc';
      } else {
        state.sort = key;
        state.dir = 'asc';
      }
      state.page = 1;
      fetchAndRender();
    }

    $table.on('click', '.dtgs-sortable', function () {
      applySort($(this));
    });

    $table.on('keydown', '.dtgs-sortable', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        applySort($(this));
      }
    });

    $section.on('click', '.dtgs-page-btn', function () {
      var action = $(this).attr('data-page-action');

      if (action === 'prev' && state.page > 1) {
        state.page -= 1;
        fetchAndRender();
      } else if (action === 'next' && state.page < totalPages()) {
        state.page += 1;
        fetchAndRender();
      }
    });

    $section.on('click', '.dtgs-page-number', function () {
      var page = parseInt($(this).attr('data-page'), 10);
      if (page && page !== state.page) {
        state.page = page;
        fetchAndRender();
      }
    });

    $('#dtgs-per-page').on('change', function () {
      state.perPage = parseInt($(this).val(), 10) || 20;
      state.page = 1;
      fetchAndRender();
    });

    $('#ddtTableTblSearchTxt').on('input', function () {
      var value = $.trim($(this).val());
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        state.search = value;
        state.page = 1;
        fetchAndRender();
      }, 350);
    });

    $checkAll.on('change', function () {
      $tbody.find('.dtgs-row-checkbox').prop('checked', $(this).is(':checked'));
      updateSelection();
    });

    $tbody.on('change', '.dtgs-row-checkbox', updateSelection);

    function removeTables(ids, $trigger) {
      if (!ids.length) {
        return;
      }

      if (!window.confirm(ids.length > 1 ? 'Delete ' + ids.length + ' selected tables?' : 'Delete this table?')) {
        return;
      }

      if ($trigger && $trigger.length) {
        $trigger.prop('disabled', true).find('i').removeClass('fa-trash-o').addClass('fa-spinner fa-spin');
      }
      setLoading(true);

      app
        .request(
          { module: 'tables', action: 'remove', nonce: window.DTGS_NONCE },
          { id: ids }
        )
        .done(function () {
          state.page = Math.min(state.page, totalPages());
          fetchAndRender();
        })
        .fail(function (message) {
          setStatus(typeof message === 'string' ? message : 'Could not delete the table.', 'error');
          setLoading(false);
          if ($trigger && $trigger.length) {
            $trigger.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-trash-o');
          }
        });
    }

    $deleteSelected.on('click', function () {
      removeTables(selectedIds(), $(this));
    });

    $tbody.on('click', '.dtgs-delete-table', function () {
      removeTables([parseInt($(this).attr('data-table-id'), 10)], $(this));
    });

    $tbody.on('click', '.dtgs-copy-code', function () {
      this.focus();
      this.select();
    });

    $('.pro-notify[data-dialog]').each(function () {
      var $button = $(this),
        $dialog = $($button.attr('data-dialog'));

      if (!$dialog.length || !$dialog.dialog) {
        return;
      }
      $dialog.dialog({
        autoOpen: false,
        title: $button.attr('data-dtitle'),
        width: parseInt($button.attr('data-dwidth'), 10) || 480,
        modal: true,
        buttons: {
          Close: function () {
            $(this).dialog('close');
          },
        },
      });
    });

    $('.pro-notify[data-dialog]').on('click', function (event) {
      event.preventDefault();
      $($(this).attr('data-dialog')).dialog('open');
    });

    normalizeNativeCheckboxes($section);
    resetSelection();
    updateSortHeaders();
    updatePagination();
  });
})(window.jQuery, window.supsystic.Tables);
