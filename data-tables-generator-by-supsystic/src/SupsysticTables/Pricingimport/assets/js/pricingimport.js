/*
 * The page that moves the tables of Pricing Table by Supsystic into Data Tables.
 * Tables are moved one by one, so the page shows the progress and one broken table never stops the others.
 */
(function ($) {
  'use strict';

  var ROUTE = 'pricingimport';
  var KINDS = [
    { key: 'moved', icon: 'fa-check', label: 'kindMoved' },
    { key: 'replaced', icon: 'fa-refresh', label: 'kindReplaced' },
    { key: 'pro', icon: 'fa-diamond', label: 'kindPro' },
    { key: 'skipped', icon: 'fa-minus', label: 'kindSkipped' }
  ];

  $(function () {
    var $root = $('#stb-pti');
    if (!$root.length) { return; }
    var payload = JSON.parse($('#stb-pti-data').text() || '{}');
    var S = payload.strings || {};
    var data = payload.data || {};
    var selected = {};
    var selectedPages = {};
    var status = {}; // id => {state: waiting|working|failed, message}
    var openDetails = {};
    var busy = false;

    (payload.preselect && payload.preselect.length ? payload.preselect : []).forEach(function (id) { selected[id] = true; });

    function t(key) { return S[key] || key; }
    function f(key) {
      var args = Array.prototype.slice.call(arguments, 1);
      return t(key).replace(/%[sd]/g, function () { return String(args.shift()); });
    }
    function esc(value) { return $('<div>').text(value == null ? '' : String(value)).html(); }
    function request(action, fields) {
      return window.supsystic.Tables.request({ module: ROUTE, action: action, nonce: window.DTGS_NONCE }, fields || {});
    }

    // Our checkboxes are styled natively: the global iCheck of the plugin pages must not wrap them
    function unwrapICheck() {
      if (!$.fn.iCheck) { return; }
      $root.find('.stb-pti-checkbox').each(function () {
        var $input = $(this);
        if ($input.parent().is('[class*="icheckbox"]')) { $input.iCheck('destroy'); }
      });
    }
    $(window).on('load', unwrapICheck);
    window.setTimeout(unwrapICheck, 0);

    /* ---- Tables ---- */

    function tables() { return data.tables || []; }
    function notMoved() { return tables().filter(function (table) { return !table.moved; }); }

    function statusHtml(table) {
      var state = status[table.id];
      if (state && state.state === 'working') {
        return '<span class="stb-pti-pill stb-pti-pill-working"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i>' + esc(t('inProgress')) + '</span>';
      }
      if (state && state.state === 'waiting') {
        return '<span class="stb-pti-pill">' + esc(t('waiting')) + '</span>';
      }
      if (state && state.state === 'failed') {
        return '<span class="stb-pti-pill stb-pti-pill-error"><i class="fa fa-exclamation-circle" aria-hidden="true"></i>' + esc(t('failed')) + '</span>';
      }
      if (table.moved) {
        return '<span class="stb-pti-pill stb-pti-pill-done"><i class="fa fa-check" aria-hidden="true"></i>' + esc(f(table.moved.status === 'updated' ? 'updated' : 'movedAs', table.moved.id)) + '</span>';
      }
      return '<span class="stb-pti-pill">' + esc(t('notMoved')) + '</span>';
    }

    function reportHtml(report) {
      return '<ul class="stb-pti-report">' + (report || []).map(function (item) {
        var kind = KINDS.filter(function (k) { return k.key === item.kind; })[0] || KINDS[0];
        var text = esc(item.label) + (item.count ? ' <span class="stb-pti-count">' + esc(item.count) + '</span>' : '') + (item.note ? '<small>' + esc(item.note) + '</small>' : '');
        return '<li class="stb-pti-report-' + esc(item.kind) + '"><i class="fa ' + kind.icon + '" aria-hidden="true"></i><span>' + text + '</span></li>';
      }).join('') + '</ul>';
    }

    function renderTables() {
      var list = tables();
      var $list = $root.find('[data-pti-table-list]');
      $root.find('[data-pti-tables-empty]').prop('hidden', list.length > 0);
      $root.find('[data-pti-table-tools]').prop('hidden', !list.length);
      var moved = list.filter(function (table) { return table.moved; }).length;
      $root.find('[data-pti-tables-summary]').text(list.length === 1 ? f('summaryOne', moved) : f('summary', list.length, moved));
      $list.html(list.map(function (table) {
        var meta = [];
        meta.push('#' + table.id);
        if (table.template) { meta.push(f('fromTemplate', table.template)); }
        if (table.created) { meta.push(f('created', table.created)); }
        var actions = '';
        if (table.moved) {
          actions += '<a class="button stb-pti-small" href="' + esc(table.moved.url) + '"><i class="fa fa-pencil" aria-hidden="true"></i>' + esc(t('open')) + '</a>';
          actions += '<button type="button" class="button stb-pti-small stb-pti-code" data-pti-copy="' + esc(table.moved.shortcode) + '" title="' + esc(t('copy')) + '"><code>' + esc(table.moved.shortcode) + '</code><i class="fa fa-clone" aria-hidden="true"></i></button>';
          actions += '<button type="button" class="button-link stb-pti-link" data-pti-details="' + table.id + '" aria-expanded="' + (openDetails[table.id] ? 'true' : 'false') + '">' + esc(openDetails[table.id] ? t('hideDetails') : t('details')) + '</button>';
          actions += '<button type="button" class="button-link stb-pti-link" data-pti-update="' + table.id + '">' + esc(t('update')) + '</button>';
        }
        var state = status[table.id];
        var error = state && state.state === 'failed' ? '<p class="stb-pti-error">' + esc(state.message) + '</p>' : '';
        var details = table.moved && openDetails[table.id] ? '<div class="stb-pti-details">' + reportHtml(table.moved.report) + '</div>' : '';
        return '<li class="stb-pti-row' + (table.moved ? ' is-moved' : '') + '" data-id="' + table.id + '">' +
          '<label class="stb-pti-check"><input type="checkbox" class="stb-pti-checkbox" data-pti-select="' + table.id + '"' + (selected[table.id] ? ' checked' : '') + (busy ? ' disabled' : '') + '><span class="screen-reader-text">' + esc(table.title) + '</span></label>' +
          '<div class="stb-pti-row-main"><strong class="stb-pti-row-title">' + esc(table.title) + '</strong><span class="stb-pti-row-meta">' + esc(meta.join(' · ')) + '</span>' + error + '</div>' +
          '<div class="stb-pti-row-status">' + statusHtml(table) + '</div>' +
          '<div class="stb-pti-row-actions">' + actions + '</div>' + details +
          '</li>';
      }).join(''));
      syncTableTools();
    }

    function selectedIds() {
      return tables().filter(function (table) { return selected[table.id]; }).map(function (table) { return table.id; });
    }

    function syncTableTools() {
      var ids = selectedIds();
      var list = tables();
      var $all = $root.find('[data-pti-select-all]');
      $all.prop('checked', list.length > 0 && ids.length === list.length).prop('indeterminate', ids.length > 0 && ids.length < list.length).prop('disabled', busy);
      $root.find('[data-pti-move-selected]').prop('disabled', busy || !ids.length);
      $root.find('[data-pti-move-selected-label]').text(ids.length ? t('moveSelected') + ' (' + ids.length + ')' : t('moveSelected'));
      var left = notMoved().length;
      var $moveAll = $root.find('[data-pti-move-all]');
      $moveAll.prop('hidden', !left).prop('disabled', busy);
      $root.find('[data-pti-move-all-label]').text(t('moveAll') + ' (' + left + ')');
    }

    /* ---- Result ---- */

    function renderResult() {
      var moved = tables().filter(function (table) { return table.moved; });
      var $card = $root.find('[data-pti-result]');
      $card.prop('hidden', !moved.length);
      if (!moved.length) { return; }
      $root.find('[data-pti-result-title]').text(moved.length === 1 ? t('doneTitleOne') : f('doneTitle', moved.length));
      var groups = {};
      moved.forEach(function (table) {
        (table.moved.report || []).forEach(function (item) {
          var key = item.kind + '|' + item.label;
          if (!groups[key]) { groups[key] = { kind: item.kind, label: item.label, tables: 0 }; }
          groups[key].tables++;
        });
      });
      var hasPro = false;
      $root.find('[data-pti-kinds]').html(KINDS.map(function (kind) {
        var items = Object.keys(groups).map(function (key) { return groups[key]; }).filter(function (item) { return item.kind === kind.key; });
        if (!items.length) { return ''; }
        if (kind.key === 'pro') { hasPro = true; }
        return '<div class="stb-pti-kind stb-pti-kind-' + kind.key + '"><h4><i class="fa ' + kind.icon + '" aria-hidden="true"></i>' + esc(t(kind.label)) + '</h4><ul>' + items.map(function (item) {
          return '<li><span>' + esc(item.label) + '</span><small>' + esc(item.tables === 1 ? t('inOneTable') : f('inTables', item.tables)) + '</small></li>';
        }).join('') + '</ul></div>';
      }).join(''));
      var showCoupon = !!data.coupon && !data.isPro;
      var $coupon = $root.find('[data-pti-coupon]').prop('hidden', !showCoupon);
      if (showCoupon) {
        $coupon.find('[data-pti-coupon-link]').attr('href', data.proUrl);
        $coupon.find('[data-pti-coupon-left]').prop('hidden', !hasPro);
        $coupon.find('[data-pti-coupon-more]').prop('hidden', hasPro);
      }
      $root.find('[data-pti-pro-active]').prop('hidden', !(hasPro && data.isPro)).text(t('proActive'));
    }

    /* ---- Pages ---- */

    function renderPages() {
      var places = (data.shortcodes && data.shortcodes.places) || [];
      var replaced = (data.shortcodes && data.shortcodes.replaced) || [];
      var $list = $root.find('[data-pti-page-list]');
      $root.find('[data-pti-pages-empty]').prop('hidden', places.length > 0 || replaced.length > 0);
      $root.find('[data-pti-page-tools]').prop('hidden', !places.length);
      Object.keys(selectedPages).forEach(function (key) {
        if (!places.some(function (place) { return place.key === key && place.ready; })) { delete selectedPages[key]; }
      });
      $list.html(places.map(function (place) {
        var meta = [place.type, place.where, place.status].filter(Boolean).join(' · ');
        var codes = place.items.map(function (item) {
          return '<li><code>' + esc(item.shortcode) + '</code><i class="fa fa-long-arrow-right" aria-hidden="true"></i>' +
            (item.replacement ? '<code class="stb-pti-code-new">' + esc(item.replacement) + '</code>' : '<em>' + esc(f('notMovedTable', item.source)) + '</em>') + '</li>';
        }).join('');
        var title = place.edit ? '<a href="' + esc(place.edit) + '" target="_blank" rel="noopener">' + esc(place.title) + '</a>' : esc(place.title);
        var view = place.view ? '<a class="button stb-pti-small" href="' + esc(place.view) + '" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i>' + esc(t('open')) + '</a>' : '';
        return '<li class="stb-pti-row stb-pti-row-page">' +
          '<label class="stb-pti-check"><input type="checkbox" class="stb-pti-checkbox" data-pti-page="' + esc(place.key) + '"' + (selectedPages[place.key] ? ' checked' : '') + (place.ready ? '' : ' disabled') + '><span class="screen-reader-text">' + esc(place.title) + '</span></label>' +
          '<div class="stb-pti-row-main"><strong class="stb-pti-row-title">' + title + '</strong><span class="stb-pti-row-meta">' + esc(meta) + '</span><ul class="stb-pti-codes">' + codes + '</ul></div>' +
          '<div class="stb-pti-row-actions">' + view + '</div>' +
          '</li>';
      }).join(''));
      var $replaced = $root.find('[data-pti-replaced]').prop('hidden', !replaced.length);
      $replaced.find('[data-pti-replaced-list]').html(replaced.map(function (place) {
        var meta = [place.type, place.where, f('replacedOn', place.date)].filter(Boolean).join(' · ');
        var title = place.edit ? '<a href="' + esc(place.edit) + '" target="_blank" rel="noopener">' + esc(place.title) + '</a>' : esc(place.title);
        var view = place.view ? '<a class="button stb-pti-small" href="' + esc(place.view) + '" target="_blank" rel="noopener"><i class="fa fa-external-link" aria-hidden="true"></i>' + esc(t('open')) + '</a>' : '';
        return '<li class="stb-pti-row stb-pti-row-replaced">' +
          '<span class="stb-pti-done-mark"><i class="fa fa-check" aria-hidden="true"></i></span>' +
          '<div class="stb-pti-row-main"><strong class="stb-pti-row-title">' + title + '</strong><span class="stb-pti-row-meta">' + esc(meta) + ' · ' + esc(place.count === 1 ? t('shortcodesOne') : f('shortcodesCount', place.count)) + '</span></div>' +
          '<div class="stb-pti-row-actions">' + view + '<button type="button" class="button stb-pti-small" data-pti-undo="' + esc(place.key) + '"><i class="fa fa-undo" aria-hidden="true"></i>' + esc(t('undo')) + '</button></div>' +
          '</li>';
      }).join(''));
      syncPageTools();
    }

    function selectedPageKeys() { return Object.keys(selectedPages).filter(function (key) { return selectedPages[key]; }); }

    function syncPageTools() {
      var places = ((data.shortcodes && data.shortcodes.places) || []).filter(function (place) { return place.ready; });
      var keys = selectedPageKeys();
      $root.find('[data-pti-select-pages]').prop('checked', places.length > 0 && keys.length === places.length).prop('indeterminate', keys.length > 0 && keys.length < places.length).prop('disabled', busy || !places.length);
      $root.find('[data-pti-replace]').prop('disabled', busy || !keys.length);
      $root.find('[data-pti-replace-label]').text(keys.length ? (keys.length === 1 ? t('replaceOne') : f('replacePages', keys.length)) : t('replaceSelected'));
    }

    /* ---- Steps ---- */

    function renderSteps() {
      var list = tables();
      var allMoved = list.length > 0 && !notMoved().length;
      var places = (data.shortcodes && data.shortcodes.places) || [];
      var replaced = (data.shortcodes && data.shortcodes.replaced) || [];
      var pagesDone = allMoved && !places.some(function (place) { return place.ready; });
      var state = { move: allMoved ? 'done' : 'current', pages: allMoved ? (pagesDone ? 'done' : 'current') : '', check: pagesDone && replaced.length ? 'current' : '' };
      $root.find('[data-pti-step]').each(function () {
        var $step = $(this);
        var value = state[$step.data('pti-step')];
        $step.toggleClass('is-done', value === 'done').toggleClass('is-current', value === 'current');
        $step.find('.stb-pti-step-num').html(value === 'done' ? '<i class="fa fa-check" aria-hidden="true"></i>' : esc($step.index() + 1));
      });
    }

    function render() {
      renderTables();
      renderResult();
      renderPages();
      renderSteps();
    }

    /* ---- Dialog and messages ---- */

    var dialog = $root.find('[data-pti-dialog]')[0];
    function confirmBox(title, text, ok) {
      return new Promise(function (resolve) {
        if (!dialog || typeof dialog.showModal !== 'function') { resolve(window.confirm(title + '\n\n' + text)); return; }
        $(dialog).find('[data-pti-dialog-title]').text(title);
        $(dialog).find('[data-pti-dialog-text]').text(text);
        $(dialog).find('[data-pti-dialog-ok]').text(ok);
        $(dialog).find('[data-pti-dialog-cancel]').text(t('cancel'));
        dialog.returnValue = '';
        $(dialog).one('close', function () { resolve(dialog.returnValue === 'ok'); });
        dialog.showModal();
        $(dialog).find('[data-pti-dialog-ok]').trigger('focus');
      });
    }
    $(dialog).on('click', function (event) { if (event.target === dialog) { dialog.close('cancel'); } });

    var toastTimer;
    function toast(message, isError) {
      var $toast = $root.find('[data-pti-toast]');
      $toast.text(message).toggleClass('is-error', !!isError).prop('hidden', false);
      window.clearTimeout(toastTimer);
      toastTimer = window.setTimeout(function () { $toast.prop('hidden', true); }, 4000);
    }

    function setBusy(value) {
      busy = value;
      $root.toggleClass('is-busy', value);
    }

    /* ---- Moving ---- */

    function move(ids, update) {
      if (busy || !ids.length) { return; }
      setBusy(true);
      ids.forEach(function (id) { status[id] = { state: 'waiting' }; });
      var $progress = $root.find('[data-pti-progress]').prop('hidden', false);
      var done = 0;
      function progress() {
        $progress.find('[data-pti-progress-bar]').css('width', Math.round(done / ids.length * 100) + '%');
        $progress.find('[data-pti-progress-text]').text(f('moving', Math.min(done + 1, ids.length), ids.length));
      }
      var chain = Promise.resolve();
      ids.forEach(function (id) {
        chain = chain.then(function () {
          status[id] = { state: 'working' };
          progress();
          renderTables();
          return new Promise(function (resolve) {
            request('import', { table: id, update: update ? 1 : 0 }).done(function (response) {
              delete status[id];
              tables().forEach(function (table) { if (table.id === id) { table.moved = response.result; } });
              resolve();
            }).fail(function (message) {
              status[id] = { state: 'failed', message: typeof message === 'string' && message ? message : t('error') };
              resolve();
            });
          }).then(function () {
            done++;
            progress();
            render();
          });
        });
      });
      chain.then(function () {
        setBusy(false);
        selected = {};
        $progress.prop('hidden', true);
        // The pages list knows the new tables only after a fresh look at the site
        request('state').done(function (response) {
          data = response.data;
          render();
        }).always(function () { render(); });
      });
    }

    $root.on('click', '[data-pti-move-all]', function () {
      move(notMoved().map(function (table) { return table.id; }), false);
    });
    $root.on('click', '[data-pti-move-selected]', function () {
      move(selectedIds(), false);
    });
    $root.on('click', '[data-pti-update]', function () {
      var id = parseInt($(this).attr('data-pti-update'), 10);
      var table = tables().filter(function (item) { return item.id === id; })[0];
      if (!table || !table.moved) { return; }
      confirmBox(t('confirmUpdateTitle'), f('confirmUpdateText', table.moved.id), t('confirmUpdate')).then(function (ok) {
        if (ok) { move([id], true); }
      });
    });
    $root.on('change', '[data-pti-select]', function () {
      selected[$(this).attr('data-pti-select')] = this.checked;
      syncTableTools();
    });
    $root.on('change', '[data-pti-select-all]', function () {
      var checked = this.checked;
      selected = {};
      if (checked) { tables().forEach(function (table) { selected[table.id] = true; }); }
      $root.find('[data-pti-select]').prop('checked', checked);
      syncTableTools();
    });
    $root.on('click', '[data-pti-details]', function () {
      var id = $(this).attr('data-pti-details');
      openDetails[id] = !openDetails[id];
      renderTables();
    });
    $root.on('click', '[data-pti-copy]', function () {
      var $button = $(this);
      var text = $button.attr('data-pti-copy');
      var copied = function () {
        $button.addClass('is-copied');
        toast(t('copied') + ': ' + text);
        window.setTimeout(function () { $button.removeClass('is-copied'); }, 1500);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(copied);
      } else {
        var $area = $('<textarea>').val(text).css({ position: 'fixed', top: '-1000px' }).appendTo('body');
        $area[0].select();
        try { document.execCommand('copy'); copied(); } catch (e) { /* the shortcode stays visible on the button */ }
        $area.remove();
      }
    });

    /* ---- Shortcodes ---- */

    $root.on('change', '[data-pti-page]', function () {
      selectedPages[$(this).attr('data-pti-page')] = this.checked;
      syncPageTools();
    });
    $root.on('change', '[data-pti-select-pages]', function () {
      var checked = this.checked;
      selectedPages = {};
      ((data.shortcodes && data.shortcodes.places) || []).forEach(function (place) { if (checked && place.ready) { selectedPages[place.key] = true; } });
      renderPages();
    });
    function placesRequest(action, places) {
      setBusy(true);
      syncPageTools();
      request(action, { places: places }).done(function (response) {
        data = response.data;
        selectedPages = {};
        render();
      }).fail(function (message) {
        toast(typeof message === 'string' && message ? message : t('error'), true);
      }).always(function () {
        setBusy(false);
        syncPageTools();
        syncTableTools();
      });
    }
    $root.on('click', '[data-pti-replace]', function () {
      var keys = selectedPageKeys();
      if (!keys.length) { return; }
      confirmBox(t('confirmReplaceTitle'), t('confirmReplaceText'), t('confirmReplace')).then(function (ok) {
        if (ok) { placesRequest('replace', keys); }
      });
    });
    $root.on('click', '[data-pti-undo]', function () {
      var key = $(this).attr('data-pti-undo');
      confirmBox(t('confirmUndoTitle'), t('confirmUndoText'), t('confirmUndo')).then(function (ok) {
        if (ok) { placesRequest('restore', [key]); }
      });
    });
    $root.on('click', '[data-pti-undo-all]', function () {
      confirmBox(t('confirmUndoTitle'), t('confirmUndoText'), t('confirmUndo')).then(function (ok) {
        if (ok) { placesRequest('restore', 'all'); }
      });
    });

    $(window).on('beforeunload', function (event) {
      if (!busy) { return undefined; }
      event.preventDefault();
      return t('leave');
    });

    render();
  });
})(jQuery);
