// Moves tables from other table plugins: an offer on the pages of the plugin, and a window from the Settings page.
(function ($, window, document) {
  'use strict';

  var boot = window.STB_IMPORT_COMPETITOR;
  if (!boot) {
    return;
  }
  var strings = boot.strings || {};
  var ROUTE = 'importcompetitor';
  var CLOSED_KEY = 'stbci_closed_';
  var data = null;
  var state = { source: null, selected: {}, palette: '', queue: [], stopped: false, running: false };
  var $window = null;
  var offerFocus = null;

  function t(key) {
    var text = strings[key] !== undefined ? strings[key] : key;
    var values = Array.prototype.slice.call(arguments, 1);
    var at = 0;
    return String(text).replace(/%[ds]/g, function () {
      return values[at] !== undefined ? values[at++] : '';
    });
  }

  function esc(text) {
    return $('<div>')
      .text(text === undefined || text === null ? '' : String(text))
      .html();
  }

  function request(action, fields) {
    return window.supsystic.Tables.request({ module: ROUTE, action: action, nonce: window.DTGS_NONCE }, fields || {});
  }

  function readClosed(source) {
    try {
      return window.sessionStorage.getItem(CLOSED_KEY + source) === '1';
    } catch (e) {
      return false;
    }
  }

  function writeClosed(source) {
    try {
      window.sessionStorage.setItem(CLOSED_KEY + source, '1');
    } catch (e) {
      // Without storage the offer simply comes back on the next page
    }
  }

  function open(html, wide) {
    close();
    offerFocus = document.activeElement;
    $window = $(
      '<div class="stbci" role="dialog" aria-modal="true" aria-labelledby="stbci-title">' +
        '<div class="stbci-box' + (wide ? ' stbci-wide' : '') + '">' + html + '</div></div>'
    ).appendTo(document.body);
    $('html').addClass('stbci-lock');
    var first = $window.find('button:not([disabled]):not(.stbci-x), input:not([disabled])').first();
    (first.length ? first : $window.find('button').first()).trigger('focus');
  }

  function fill(html, wide) {
    if (!$window) {
      open(html, wide);
      return;
    }
    $window.find('.stbci-box').toggleClass('stbci-wide', !!wide).html(html);
    var first = $window.find('button:not([disabled]):not(.stbci-x), input:not([disabled])').first();
    (first.length ? first : $window.find('button').first()).trigger('focus');
  }

  function close() {
    if ($window) {
      $window.remove();
      $window = null;
      $('html').removeClass('stbci-lock');
      if (offerFocus && offerFocus.focus) {
        offerFocus.focus();
      }
    }
  }

  function head(title, text) {
    return (
      '<header class="stbci-head"><span class="stbci-mark"><i class="fa fa-download" aria-hidden="true"></i></span><div>' +
      '<h2 id="stbci-title">' + esc(title) + '</h2>' + (text ? '<p>' + esc(text) + '</p>' : '') +
      '</div><button type="button" class="stbci-x" data-action="close" aria-label="' + esc(t('close')) + '">&times;</button></header>'
    );
  }

  /* ---- The offer ---- */

  function showOffer(offer) {
    var title = offer.count === 1 ? t('offerTitleOne', offer.name) : t('offerTitle', offer.count, offer.name);
    state.source = offer.source;
    open(
      head(title, '') +
        '<div class="stbci-body"><p class="stbci-lead">' + esc(t('offerText')) + '</p></div>' +
        '<footer class="stbci-foot"><button type="button" class="stbci-link" data-action="never">' + esc(t('offerNever')) + '</button><span class="stbci-gap"></span>' +
        '<button type="button" class="stbci-btn" data-action="later">' + esc(t('offerLater')) + '</button>' +
        '<button type="button" class="stbci-btn stbci-primary" data-action="load">' + esc(t('offerGo')) + '</button></footer>'
    );
    $window.addClass('stbci-offer');
  }

  function postpone(mode) {
    request('postpone', { source: state.source, mode: mode });
    close();
  }

  /* ---- The list of tables ---- */

  function load(source) {
    var wanted = source;
    fill(head(t('pickTitle', '...'), '') + '<div class="stbci-body stbci-loading"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i></div>', true);
    if ($window) {
      $window.removeClass('stbci-offer');
    }
    request('scan')
      .done(function (response) {
        data = response;
        var found = null;
        $.each(data.sources, function (index, item) {
          if ((wanted && item.key === wanted) || (!wanted && !found && item.tables.length)) {
            found = item;
          }
        });
        state.source = found ? found.key : null;
        state.palette = state.palette || defaultPalette();
        if (found) {
          state.selected = {};
          $.each(found.tables, function (index, table) {
            if (table.supported && !table.importedAs) {
              state.selected[table.id] = true;
            }
          });
        }
        showList();
      })
      .fail(function (message) {
        fill(head(t('pickTitle', ''), '') + '<div class="stbci-body"><p class="stbci-error">' + esc(message || t('loadError')) + '</p></div>' + footClose(), true);
      });
  }

  function defaultPalette() {
    var key = data.palettes.length ? data.palettes[0].key : '';
    $.each(data.palettes, function (index, palette) {
      if (palette.key === 'ocean') {
        key = 'ocean';
      }
    });
    return key;
  }

  function footClose() {
    return '<footer class="stbci-foot"><span class="stbci-gap"></span><button type="button" class="stbci-btn" data-action="close">' + esc(t('close')) + '</button></footer>';
  }

  function current() {
    var found = null;
    $.each(data.sources, function (index, source) {
      if (source.key === state.source) {
        found = source;
      }
    });
    return found;
  }

  function showList() {
    var source = current();
    if (!source || !source.tables.length) {
      fill(
        head(t('noTables'), '') + '<div class="stbci-body"><p class="stbci-lead">' + esc(t('noTablesHint')) + '</p></div>' + footClose(),
        true
      );
      return;
    }
    var html = head(t('pickTitle', source.name), t('pickText')) + '<div class="stbci-body">';
    html += '<label class="stbci-all"><input type="checkbox" data-role="all" /> ' + esc(t('selectAll')) + '</label><ul class="stbci-tables">';
    $.each(source.tables, function (index, table) {
      var facts = table.supported ? (table.rows < 0 ? t('rowsRemote') : t('rows', table.rows)) + ' &middot; ' + t('columns', table.columns) : '';
      html += '<li class="' + (table.supported ? '' : 'is-off') + '"><label><input type="checkbox" data-table="' + table.id + '"' + (state.selected[table.id] ? ' checked' : '') + (table.supported ? '' : ' disabled') + ' />';
      html += '<span class="stbci-table"><strong>' + esc(table.title) + '</strong>';
      html += table.supported ? '<small>' + facts + '</small>' : '<small class="stbci-why">' + esc(t('notAvailable')) + ': ' + esc(table.reason) + '</small>';
      if (table.importedAs) {
        html += '<small class="stbci-was">' + esc(t('imported', table.importedAs)) + ' ' + esc(t('importAgain')) + '</small>';
      }
      html += '</span></label></li>';
    });
    html += '</ul>';
    html += '<div class="stbci-design"><label for="stbci-palette">' + esc(t('design')) + '</label><select id="stbci-palette" data-role="palette">';
    $.each(data.palettes, function (index, palette) {
      html += '<option value="' + esc(palette.key) + '"' + (palette.key === state.palette ? ' selected' : '') + '>' + esc(palette.name) + '</option>';
    });
    html += '</select>';
    if (!data.isPro) {
      html += '<small><a href="' + esc(data.proUrl) + '" target="_blank" rel="noopener">' + esc(t('designProNote')) + '</a></small>';
    }
    html += '</div></div>';
    html += '<footer class="stbci-foot"><span class="stbci-gap"></span><button type="button" class="stbci-btn" data-action="close">' + esc(t('close')) + '</button>';
    html += '<button type="button" class="stbci-btn stbci-primary" data-action="review">' + esc(t('next')) + '</button></footer>';
    fill(html, true);
    syncList();
  }

  function chosenTables() {
    var list = [];
    $.each(current().tables, function (index, table) {
      if (state.selected[table.id] && table.supported) {
        list.push(table);
      }
    });
    return list;
  }

  function syncList() {
    if (!$window) {
      return;
    }
    var boxes = $window.find('[data-table]:not(:disabled)');
    var checked = boxes.filter(':checked').length;
    $window.find('[data-role="all"]').prop('checked', boxes.length > 0 && checked === boxes.length).prop('indeterminate', checked > 0 && checked < boxes.length);
    $window.find('[data-action="review"]').prop('disabled', checked === 0);
  }

  /* ---- What happens with the options ---- */

  function collectReport(tables) {
    var groups = { moved: [], replaced: [], pro: [], skipped: [] };
    var seen = {};
    $.each(tables, function (index, table) {
      $.each(table.report || [], function (i, item) {
        var id = item.kind + '|' + item.label;
        if (!seen[id]) {
          seen[id] = { kind: item.kind, label: item.label, note: item.note, count: 0 };
          groups[item.kind].push(seen[id]);
        }
        seen[id].count++;
      });
    });
    return groups;
  }

  function countLabel(count) {
    return count === 1 ? t('tablesCountOne') : t('tablesCount', count);
  }

  function reportGroup(kind, title, items, total) {
    if (!items.length) {
      return '';
    }
    var icons = { moved: 'fa-check', replaced: 'fa-exchange', skipped: 'fa-minus' };
    var html = '<section class="stbci-group stbci-group-' + kind + '"><h3><i class="fa ' + icons[kind] + '" aria-hidden="true"></i> ' + esc(title) + '</h3><ul>';
    $.each(items, function (index, item) {
      html += '<li><strong>' + esc(item.label) + '</strong>' + (total > 1 && item.count < total ? ' <em>' + esc(countLabel(item.count)) + '</em>' : '');
      html += item.note ? '<small>' + esc(item.note) + '</small>' : '';
      html += '</li>';
    });
    return html + '</ul></section>';
  }

  function showReview() {
    var tables = chosenTables();
    var report = collectReport(tables);
    var html = head(t('reviewTitle'), t('reviewText')) + '<div class="stbci-body">';
    if (report.pro.length) {
      html += '<section class="stbci-pro"><div class="stbci-pro-head"><div><h3>' + esc(t('proTitle')) + '</h3><p>' + esc(t('proText')) + '</p></div>';
      html += '<a class="stbci-get-pro" href="' + esc(data.proUrl) + '" target="_blank" rel="noopener">' + esc(t('getPro')) + ' <i class="fa fa-arrow-right" aria-hidden="true"></i></a></div><ul>';
      $.each(report.pro, function (index, item) {
        html += '<li><span class="stbci-chip">PRO</span><div><strong>' + esc(item.label) + '</strong>' + (tables.length > 1 && item.count < tables.length ? ' <em>' + esc(countLabel(item.count)) + '</em>' : '') + (item.note ? '<small>' + esc(item.note) + '</small>' : '') + '</div></li>';
      });
      html += '</ul></section>';
    }
    html += reportGroup('moved', t('kindMoved'), report.moved, tables.length);
    html += reportGroup('replaced', t('kindReplaced'), report.replaced, tables.length);
    html += reportGroup('skipped', t('kindSkipped'), report.skipped, tables.length);
    html += '</div><footer class="stbci-foot"><button type="button" class="stbci-btn" data-action="list">' + esc(t('back')) + '</button><span class="stbci-gap"></span>';
    var label = report.pro.length ? t('importWithout') : tables.length === 1 ? t('importTable') : t('importTables', tables.length);
    html += '<button type="button" class="stbci-btn stbci-primary" data-action="import">' + esc(label) + '</button></footer>';
    fill(html, true);
  }

  /* ---- The import ---- */

  function showProgress() {
    var html = head(t('workingTitle'), t('workingText')) + '<div class="stbci-body"><ul class="stbci-progress">';
    $.each(state.queue, function (index, item) {
      html += '<li data-at="' + index + '"><div class="stbci-progress-line"><strong>' + esc(item.table.title) + '</strong><span class="stbci-status">' + esc(t('waiting')) + '</span></div><div class="stbci-bar"><i></i></div></li>';
    });
    html += '</ul></div><footer class="stbci-foot"><span class="stbci-gap"></span><button type="button" class="stbci-btn" data-action="stop">' + esc(t('stop')) + '</button></footer>';
    fill(html, true);
    $window.find('.stbci-x').remove();
  }

  function setProgress(index, status, part, failed) {
    if (!$window) {
      return;
    }
    var line = $window.find('[data-at="' + index + '"]');
    line.find('.stbci-status').text(status);
    line.toggleClass('is-failed', !!failed);
    if (part !== null) {
      line.find('.stbci-bar i').css('width', Math.round(part * 100) + '%');
    }
  }

  function startImport() {
    state.queue = [];
    state.stopped = false;
    state.running = true;
    $.each(chosenTables(), function (index, table) {
      state.queue.push({ table: table, result: null, error: '' });
    });
    showProgress();
    $(window).on('beforeunload.stbci', function () {
      return t('leave');
    });
    importNext(0);
  }

  function importNext(index) {
    if (index >= state.queue.length) {
      finishAll();
      return;
    }
    if (state.stopped) {
      state.queue[index].error = t('stopped');
      setProgress(index, t('stopped'), null, true);
      importNext(index + 1);
      return;
    }
    var item = state.queue[index];
    var failNow = function (message) {
      item.error = message || t('failed');
      setProgress(index, item.error, null, true);
      importNext(index + 1);
    };
    setProgress(index, t('preparing'), 0);
    request('start', { source: state.source, table: item.table.id, palette: state.palette })
      .done(function (response) {
        importRows(index, response.table, 0, failNow);
      })
      .fail(failNow);
  }

  function importRows(index, created, offset, failNow) {
    var item = state.queue[index];
    var abortAndFail = function (message) {
      request('abort', { newId: created.id });
      failNow(message);
    };
    if (state.stopped) {
      abortAndFail(t('stopped'));
      return;
    }
    if (offset >= created.total) {
      request('finish', { source: state.source, table: item.table.id, newId: created.id, total: created.total })
        .done(function (response) {
          item.result = response.table;
          item.title = created.title;
          setProgress(index, t('working', created.total, created.total), 1);
          importNext(index + 1);
        })
        .fail(abortAndFail);
      return;
    }
    setProgress(index, t('working', offset, created.total), created.total ? offset / created.total : 1);
    request('rows', { source: state.source, table: item.table.id, newId: created.id, offset: offset })
      .done(function (response) {
        var next = parseInt(response.offset, 10);
        // The source gave nothing new: the count of its rows has changed
        if (next <= offset) {
          created.total = offset;
          next = offset;
        }
        importRows(index, created, next, failNow);
      })
      .fail(abortAndFail);
  }

  function finishAll() {
    state.running = false;
    $(window).off('beforeunload.stbci');
    var failed = 0;
    $.each(state.queue, function (index, item) {
      if (!item.result) {
        failed++;
      }
    });
    var html = head(failed ? t('doneTitleSome') : t('doneTitle'), t('doneText')) + '<div class="stbci-body"><ul class="stbci-done">';
    $.each(state.queue, function (index, item) {
      if (!item.result) {
        html += '<li class="is-failed"><strong>' + esc(item.table.title) + '</strong><small>' + esc(item.error) + '</small></li>';
        return;
      }
      html += '<li><div class="stbci-done-line"><strong>' + esc(item.title || item.table.title) + '</strong><a class="stbci-btn" href="' + esc(item.result.url) + '">' + esc(t('open')) + '</a></div>';
      html += '<div class="stbci-code"><code>' + esc(item.result.shortcode) + '</code><button type="button" class="stbci-link" data-copy="' + esc(item.result.shortcode) + '">' + esc(t('copy')) + '</button></div>';
      if (item.table.usedOn && item.table.usedOn.length) {
        html += '<small class="stbci-used">' + esc(t('usedOn')) + ' ';
        $.each(item.table.usedOn, function (i, page) {
          html += (i ? ', ' : '') + '<a href="' + esc(page.url) + '" target="_blank" rel="noopener">' + esc(page.title || '#' + page.id) + '</a>';
        });
        html += '</small>';
      }
      html += '</li>';
    });
    html += '</ul></div><footer class="stbci-foot"><span class="stbci-gap"></span><button type="button" class="stbci-btn" data-action="close">' + esc(t('close')) + '</button>';
    html += '<a class="stbci-btn stbci-primary" href="' + esc(data.tablesUrl) + '">' + esc(t('allTables')) + '</a></footer>';
    fill(html, true);
  }

  /* ---- Events ---- */

  $(document).on('click', '[data-stb-import-competitor]', function (event) {
    event.preventDefault();
    load(null);
  });

  $(document).on('click', '.stbci [data-action]', function () {
    var action = $(this).data('action');
    switch (action) {
      case 'close':
        if (state.source && $window && $window.hasClass('stbci-offer')) {
          writeClosed(state.source);
        }
        close();
        break;
      case 'later':
        postpone('later');
        break;
      case 'never':
        postpone('never');
        break;
      case 'load':
        load(state.source);
        break;
      case 'list':
        showList();
        break;
      case 'review':
        showReview();
        break;
      case 'import':
        startImport();
        break;
      case 'stop':
        state.stopped = true;
        $(this).prop('disabled', true);
        break;
    }
  });

  $(document).on('change', '.stbci [data-table]', function () {
    state.selected[$(this).data('table')] = this.checked;
    syncList();
  });

  $(document).on('change', '.stbci [data-role="all"]', function () {
    var checked = this.checked;
    $window.find('[data-table]:not(:disabled)').each(function () {
      this.checked = checked;
      state.selected[$(this).data('table')] = checked;
    });
    syncList();
  });

  $(document).on('change', '.stbci [data-role="palette"]', function () {
    state.palette = $(this).val();
  });

  $(document).on('click', '.stbci [data-copy]', function () {
    var button = $(this);
    var text = button.data('copy');
    var done = function () {
      button.text(t('copied'));
    };
    var copyByField = function () {
      var field = $('<textarea>').val(text).appendTo(button.closest('.stbci-box')).trigger('select');
      try {
        if (document.execCommand('copy')) {
          done();
        }
      } catch (e) {
        // The code stays visible to copy by hand
      }
      field.remove();
    };
    if (window.navigator.clipboard && window.navigator.clipboard.writeText) {
      window.navigator.clipboard.writeText(text).then(done, copyByField);
      return;
    }
    copyByField();
  });

  $(document).on('keydown', function (event) {
    if (event.key === 'Escape' && $window && !state.running) {
      if ($window.hasClass('stbci-offer') && state.source) {
        writeClosed(state.source);
      }
      close();
    }
  });

  $(function () {
    var offer = boot.offer;
    // The wizard opens by this address: one window at a time
    if (!offer || /^#add(-[a-z]+)?$/.test(window.location.hash) || readClosed(offer.source)) {
      return;
    }
    window.setTimeout(function () {
      if (!$window && !$('.stbw').length) {
        showOffer(offer);
      }
    }, 900);
  });
})(window.jQuery, window, document);
