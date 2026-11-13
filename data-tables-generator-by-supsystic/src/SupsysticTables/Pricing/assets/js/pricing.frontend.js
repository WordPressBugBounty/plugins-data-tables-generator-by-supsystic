(function () {
  'use strict';

  var observed = typeof WeakSet === 'function' ? new WeakSet() : null;
  var observedGrids = typeof WeakSet === 'function' ? new WeakSet() : null;
  var countdownTimers = typeof WeakMap === 'function' ? new WeakMap() : null;
  var observedCountdowns = typeof WeakSet === 'function' ? new WeakSet() : null;
  var resizeObserver = 'ResizeObserver' in window ? new ResizeObserver(function () { scheduleComparisonLayout(document); }) : null;
  var layoutFrame = 0;
  var layoutId = 0;

  function pauseFrame(frame) {
    if (!frame.contentWindow) { return; }
    frame.contentWindow.postMessage(JSON.stringify({ event: 'command', func: 'pauseVideo', args: [] }), '*');
    frame.contentWindow.postMessage(JSON.stringify({ method: 'pause' }), '*');
  }

  function pauseMedia(media) {
    if (media.tagName === 'VIDEO') {
      media.pause();
    } else if (media.tagName === 'IFRAME') {
      pauseFrame(media);
    }
  }

  function setup(root) {
    var mediaNodes = [];
    root = root || document;
    if (root.matches && root.matches('[data-pause-out-of-view]')) { mediaNodes.push(root); }
    mediaNodes = mediaNodes.concat(Array.prototype.slice.call(root.querySelectorAll('[data-pause-out-of-view]')));
    if ('IntersectionObserver' in window) { mediaNodes.forEach(function (media) {
      if (observed && observed.has(media)) { return; }
      if (observed) { observed.add(media); }
      new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) { if (!entry.isIntersecting) { pauseMedia(entry.target); } });
      }, { threshold: 0.05 }).observe(media);
    }); }
    setupCountdowns(root);
    observeComparisonGrids(root || document);
    scheduleComparisonLayout(root || document);
  }

  function countdownNodes(root) {
    var nodes = [];
    if (root.matches && root.matches('.st-pricing-countdown')) { nodes.push(root); }
    if (root.querySelectorAll) { nodes = nodes.concat(Array.prototype.slice.call(root.querySelectorAll('.st-pricing-countdown'))); }
    return nodes;
  }

  function countdownStorageKey(timer) {
    var table = timer.closest('.st-pricing-table');
    var content = timer.closest('.st-pricing-variant-content');
    var timers = table ? table.querySelectorAll('.st-pricing-countdown') : document.querySelectorAll('.st-pricing-countdown');
    return 'stPricingCountdown:' + (table ? table.getAttribute('data-id') : 'page') + ':' + (content ? content.getAttribute('data-variant') || 'default' : 'default') + ':' + Array.prototype.indexOf.call(timers, timer) + ':' + (timer.getAttribute('data-st-countdown-duration') || '0');
  }

  function renderCountdown(timer, deadline) {
    var remaining = Math.max(0, Math.floor((deadline - Date.now()) / 1000));
    var values = {
      days: Math.floor(remaining / 86400),
      hours: Math.floor((remaining % 86400) / 3600),
      minutes: Math.floor((remaining % 3600) / 60),
      seconds: remaining % 60
    };
    Object.keys(values).forEach(function (unit) {
      var target = timer.querySelector('[data-st-countdown-unit="' + unit + '"]');
      if (target) { target.textContent = String(values[unit]).padStart(2, '0'); }
    });
    if (remaining > 0) { return true; }
    timer.classList.add('is-expired');
    var label = timer.querySelector('.st-pricing-countdown-label');
    if (label) { label.textContent = timer.getAttribute('data-st-countdown-expired') || 'Offer ended'; }
    return false;
  }

  function startCountdown(timer) {
    if (countdownTimers && countdownTimers.has(timer)) { return; }
    var mode = timer.getAttribute('data-st-countdown-mode') === 'date' ? 'date' : 'duration';
    var deadline = mode === 'date' ? new Date(timer.getAttribute('data-st-countdown-target') || '').getTime() : 0;
    if (mode === 'duration') {
      var key = countdownStorageKey(timer);
      try { deadline = parseInt(window.sessionStorage.getItem(key), 10) || 0; } catch (ignore) {}
      if (!deadline) {
        deadline = Date.now() + Math.max(1, parseInt(timer.getAttribute('data-st-countdown-duration'), 10) || 600) * 1000;
        try { window.sessionStorage.setItem(key, String(deadline)); } catch (ignore) {}
      }
    }
    if (!isFinite(deadline)) { return; }
    var tick = function () {
      if (!timer.isConnected) {
        var detachedHandle = countdownTimers && countdownTimers.get(timer);
        if (detachedHandle) { window.clearInterval(detachedHandle); }
        if (countdownTimers) { countdownTimers.delete(timer); }
        return;
      }
      if (!renderCountdown(timer, deadline)) {
        var handle = countdownTimers && countdownTimers.get(timer);
        if (handle) { window.clearInterval(handle); }
        if (countdownTimers) { countdownTimers.delete(timer); }
      }
    };
    tick();
    if (!timer.classList.contains('is-expired')) {
      var handle = window.setInterval(tick, 1000);
      if (countdownTimers) { countdownTimers.set(timer, handle); }
    }
  }

  function setupCountdowns(root) {
    countdownNodes(root).forEach(function (timer) {
      if (observedCountdowns && observedCountdowns.has(timer)) { return; }
      if (observedCountdowns) { observedCountdowns.add(timer); }
      if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) { if (entry.isIntersecting) { observer.disconnect(); startCountdown(timer); } });
        }, { threshold: 0.1 });
        observer.observe(timer);
      } else { startCountdown(timer); }
    });
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.st-pricing-woo-cart-button');
    if (!button) { return; }
    var block = button.closest('.st-pricing-woo-cart');
    var table = button.closest('.st-pricing-table');
    var status = block.querySelector('.st-pricing-woo-cart-status');
    if (!status && block.matches('.st-pricing-woo-cart-button')) {
      status = block.nextElementSibling;
      if (!status || !status.classList.contains('st-pricing-woo-cart-status')) {
        status = document.createElement('span');
        status.className = 'st-pricing-woo-cart-status';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        block.insertAdjacentElement('afterend', status);
      }
    }
    if (!table || table.getAttribute('data-woo-active') !== '1') {
      if (status) { status.textContent = 'WooCommerce is unavailable.'; status.classList.add('is-error'); }
      return;
    }
    var redirectUrl = block.getAttribute('data-st-redirect-url') || '';
    var redirectTarget = block.getAttribute('data-st-redirect-target') === '_blank' ? '_blank' : '_self';
    var redirectWindow = null;
    if (redirectUrl && redirectTarget === '_blank') {
      redirectWindow = window.open('about:blank', '_blank');
      if (redirectWindow) { redirectWindow.opener = null; }
    }
    button.disabled = true;
    if (status) { status.textContent = 'Adding to cart…'; status.classList.remove('is-error', 'is-success'); }
    var body = new URLSearchParams({
      action: 'supsystic_pricing_woo_cart', nonce: table.getAttribute('data-woo-nonce') || '',
      products: block.getAttribute('data-st-products') || '', coupon: block.getAttribute('data-st-coupon') || ''
    });
    fetch(table.getAttribute('data-woo-url'), { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' }, body: body.toString() })
      .then(function (response) { return response.json(); })
      .then(function (response) {
        if (!response.success) { throw new Error(response.data && response.data.message || 'Could not add products'); }
        if (status) { status.textContent = response.data.message; status.classList.add('is-success'); }
        document.dispatchEvent(new CustomEvent('st-pricing-cart-updated', { detail: response.data }));
        if (window.jQuery) { window.jQuery(document.body).trigger('wc_fragment_refresh'); }
        if (redirectUrl) {
          window.setTimeout(function () {
            if (redirectTarget === '_blank' && redirectWindow && !redirectWindow.closed) { redirectWindow.location.href = redirectUrl; }
            else if (redirectTarget === '_blank') { window.open(redirectUrl, '_blank', 'noopener'); }
            else { window.location.assign(redirectUrl); }
          }, 650);
        } else if (redirectWindow && !redirectWindow.closed) { redirectWindow.close(); }
      })
      .catch(function (error) {
        if (redirectWindow && !redirectWindow.closed) { redirectWindow.close(); }
        if (status) { status.textContent = error.message; status.classList.remove('is-success'); status.classList.add('is-error'); }
      })
      .finally(function () { button.disabled = false; });
  });

  function escapeSelector(value) {
    if (window.CSS && window.CSS.escape) { return window.CSS.escape(value); }
    return String(value).replace(/([^a-zA-Z0-9_-])/g, '\\$1');
  }

  function comparisonSelector(grid) {
    var table = grid.closest('.st-pricing-table[id]');
    if (table) { return '#' + escapeSelector(table.id) + ' .st-pricing-grid'; }
    if (grid.id) { return '#' + escapeSelector(grid.id); }
    var runtimeId = grid.getAttribute('data-st-pricing-layout-id');
    if (!runtimeId) {
      layoutId += 1;
      runtimeId = 'grid-' + layoutId;
      grid.setAttribute('data-st-pricing-layout-id', runtimeId);
    }
    return '[data-st-pricing-layout-id="' + escapeSelector(runtimeId) + '"]';
  }

  function comparisonCards(grid) {
    return Array.prototype.filter.call(grid.children, function (child) { return child.classList.contains('st-pricing-card'); });
  }

  function comparisonCardsWrap(cards, view) {
    var visibleCards = cards.filter(function (card) { return view.getComputedStyle(card).display !== 'none'; });
    if (visibleCards.length < 2) { return false; }
    var firstRowTop = visibleCards[0].offsetTop;
    return visibleCards.some(function (card) { return Math.abs(card.offsetTop - firstRowTop) > 1; });
  }

  function featureList(card) {
    return Array.prototype.find.call(card.children, function (child) { return child.classList.contains('st-pricing-features'); }) || card.querySelector('.st-pricing-features');
  }

  function featureRows(list) {
    return list ? Array.prototype.filter.call(list.children, function (child) { return child.tagName === 'LI'; }) : [];
  }

  function clearGeneratedRows(grid) {
    Array.prototype.forEach.call(grid.querySelectorAll('[data-st-pricing-generated-row]'), function (row) { row.remove(); });
  }

  function applyResponsiveFeatureLabels(grid, descriptionCard) {
    var descriptionList = featureList(descriptionCard);
    if (!descriptionList) { return; }
    var labels = featureRows(descriptionList).map(function (row) { return row.textContent.trim(); });
    comparisonCards(grid).filter(function (card) { return card !== descriptionCard; }).forEach(function (card) {
      var list = featureList(card);
      if (!list) { return; }
      var rows = featureRows(list);
      labels.forEach(function (label, index) {
        var row = rows[index];
        if (!row) {
          row = document.createElement('li');
          row.setAttribute('data-st-pricing-generated-row', '1');
          row.innerHTML = '<span class="st-pricing-feature-empty" aria-hidden="true">—</span>';
          list.appendChild(row);
          rows.push(row);
        }
        row.setAttribute('data-st-feature-label', label);
        if (!row.hasAttribute('aria-label')) {
          row.setAttribute('aria-label', label + ': ' + (row.textContent.trim() || 'Not included'));
          row.setAttribute('data-st-pricing-generated-aria', '1');
        }
      });
      featureRows(list).slice(labels.length).forEach(function (row) {
        if (row.getAttribute('data-st-pricing-generated-row') === '1') { row.remove(); }
      });
    });
  }

  function clearResponsiveFeatureLabels(grid) {
    clearGeneratedRows(grid);
    Array.prototype.forEach.call(grid.querySelectorAll('[data-st-feature-label]'), function (row) {
      row.removeAttribute('data-st-feature-label');
      if (row.getAttribute('data-st-pricing-generated-aria') === '1') {
        row.removeAttribute('aria-label');
        row.removeAttribute('data-st-pricing-generated-aria');
      }
    });
  }

  function setRuntimeStyle(doc, rules) {
    var style = doc.getElementById('st-pricing-comparison-layout');
    if (!style) {
      style = doc.createElement('style');
      style.id = 'st-pricing-comparison-layout';
      doc.head.appendChild(style);
    }
    style.textContent = rules.join('\n');
  }

  function alignFeatureRows(root) {
    var grids = [];
    if (root.matches && root.matches('.st-pricing-grid')) { grids.push(root); }
    if (root.querySelectorAll) { grids = grids.concat(Array.prototype.slice.call(root.querySelectorAll('.st-pricing-grid'))); }
    if (!grids.length && root.nodeType === 9) { grids = Array.prototype.slice.call(root.querySelectorAll('.st-pricing-grid')); }
    var doc = root.nodeType === 9 ? root : (root.ownerDocument || document);
    var rules = [];
    setRuntimeStyle(doc, rules);

    grids.forEach(function (grid) {
      var descriptionCard = grid.querySelector('.st-pricing-description-card');
      if (!descriptionCard) { return; }
      grid.classList.add('st-pricing-has-description');
      var view = grid.ownerDocument.defaultView || window;
      grid.classList.remove('st-pricing-responsive-comparison');
      var cards = comparisonCards(grid);
      var compact = comparisonCardsWrap(cards, view);
      if (compact) {
        grid.classList.add('st-pricing-responsive-comparison');
        applyResponsiveFeatureLabels(grid, descriptionCard);
        return;
      }

      applyResponsiveFeatureLabels(grid, descriptionCard);
      var selector = comparisonSelector(grid);
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
    });

    setRuntimeStyle(doc, rules);
    grids.forEach(function (grid) {
      if (grid.classList.contains('st-pricing-responsive-comparison') || !grid.querySelector('.st-pricing-description-card')) { return; }
      var selector = comparisonSelector(grid);
      var cards = comparisonCards(grid);
      var lists = cards.map(featureList).filter(Boolean);
      var listTops = lists.map(function (list) { return list.getBoundingClientRect().top - list.closest('.st-pricing-card').getBoundingClientRect().top; });
      var maxTop = listTops.reduce(function (max, top) { return Math.max(max, top); }, 0);
      lists.forEach(function (list, index) {
        var delta = Math.ceil(maxTop - listTops[index]);
        if (!delta) { return; }
        var cardIndex = Array.prototype.indexOf.call(grid.children, list.closest('.st-pricing-card')) + 1;
        rules.push(selector + ' > .st-pricing-card:nth-child(' + cardIndex + ') > .st-pricing-features{margin-top:' + delta + 'px!important}');
      });
      var rowsByList = lists.map(featureRows);
      var maxRows = rowsByList.reduce(function (max, rows) { return Math.max(max, rows.length); }, 0);
      for (var index = 0; index < maxRows; index += 1) {
        var rows = rowsByList.map(function (listRows) { return listRows[index]; }).filter(Boolean);
        var rowTops = rows.map(function (row) { return row.getBoundingClientRect().top - row.closest('.st-pricing-card').getBoundingClientRect().top; });
        var maxRowTop = rowTops.reduce(function (max, top) { return Math.max(max, top); }, 0);
        rows.forEach(function (row, rowIndex) {
          var delta = Math.ceil(maxRowTop - rowTops[rowIndex]);
          if (!delta) { return; }
          var cardIndex = Array.prototype.indexOf.call(grid.children, row.closest('.st-pricing-card')) + 1;
          rules.push(selector + ' > .st-pricing-card:nth-child(' + cardIndex + ') > .st-pricing-features > li:nth-of-type(' + (index + 1) + '){margin-top:' + delta + 'px!important}');
        });
        setRuntimeStyle(doc, rules);
        var height = rows.reduce(function (max, row) { return Math.max(max, row.getBoundingClientRect().height); }, 0);
        if (height) { rules.push(selector + ' > .st-pricing-card > .st-pricing-features > li:nth-of-type(' + (index + 1) + '){min-height:' + Math.ceil(height) + 'px!important}'); }
        setRuntimeStyle(doc, rules);
      }
    });
    setRuntimeStyle(doc, rules);
  }

  function scheduleComparisonLayout(root) {
    window.cancelAnimationFrame(layoutFrame);
    var doc = root && root.nodeType === 9 ? root : ((root && root.ownerDocument) || document);
    layoutFrame = window.requestAnimationFrame(function () { alignFeatureRows(doc); });
  }

  function observeComparisonGrids(root) {
    if (!resizeObserver || !root.querySelectorAll) { return; }
    var grids = [];
    if (root.matches && root.matches('.st-pricing-grid')) { grids.push(root); }
    grids = grids.concat(Array.prototype.slice.call(root.querySelectorAll('.st-pricing-grid')));
    grids.forEach(function (grid) {
      if (observedGrids && observedGrids.has(grid)) { return; }
      if (observedGrids) { observedGrids.add(grid); }
      resizeObserver.observe(grid);
    });
  }

  setup(document);
  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () { scheduleComparisonLayout(document); }, 100);
  });
  if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { scheduleComparisonLayout(document); }); }
  if ('MutationObserver' in window) {
    new MutationObserver(function (changes) {
      changes.forEach(function (change) {
        change.addedNodes.forEach(function (node) { if (node.nodeType === 1) { setup(node); } });
      });
    }).observe(document.documentElement, { childList: true, subtree: true });
  }
})();
