/**
 * Table settings tab and WooCommerce table editor: the sections are tabs, the rows of each group are shown as a card,
 * locked PRO options are marked and a search finds any setting. Only the layout changes: the fields, their names and
 * their values stay as they were.
 */
(function ($) {
  'use strict';

  var STORAGE_KEY = 'stbSettingsSection';

  function readStore(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  }

  function writeStore(key, value) {
    try {
      window.localStorage.setItem(key, value);
    } catch (e) {}
  }

  function newCard(title) {
    var card = document.createElement('div'),
      body = document.createElement('div');
    card.className = 'stb-card';
    body.className = 'stb-card-body';
    if (title) {
      var icon = document.createElement('span');
      icon.className = 'stb-card-icon';
      icon.setAttribute('aria-hidden', 'true');
      icon.innerHTML = '<i class="fa fa-fw ' + (title.getAttribute('data-icon') || 'fa-sliders') + '"></i>';
      title.insertBefore(icon, title.firstChild);
      card.appendChild(title);
    }
    card.appendChild(body);
    return card;
  }

  /**
   * Every heading (.setting-title) starts a card with the rows after it. Rows are moved with the DOM API, so their
   * events, plugins (iCheck, chosen, tooltips) and inline scripts are kept and not run again. Anything else in the
   * section (dialogs of PRO features for example) stays where it is.
   */
  function buildCards(panel) {
    var grid = document.createElement('div'),
      card = null,
      heading = null,
      children = Array.prototype.slice.call(panel.children);
    grid.className = 'stb-cards';
    children.forEach(function (child) {
      var classes = child.classList;
      if (classes.contains('settings-block-title') || classes.contains('stb-panel-top')) {
        heading = child;
      } else if (classes.contains('setting-title')) {
        card = newCard(child);
        grid.appendChild(card);
      } else if (classes.contains('setting-wrapper')) {
        if (!card) {
          card = newCard(null);
          grid.appendChild(card);
        }
        card.lastChild.appendChild(child);
      }
    });
    panel.insertBefore(grid, heading ? heading.nextSibling : panel.firstChild);
  }

  function initRoot($root) {
    var isPro = $root.attr('data-pro') === '1',
      storageKey = $root.attr('data-storage-key') || STORAGE_KEY,
      $nav = $root.find('.stb-settings-nav'),
      $tabs = $nav.find('.stb-settings-tabs .stb-anchor-nav-links'),
      $panels = $(),
      $search = $root.find('.stb-settings-search input'),
      $status = $root.find('.stb-settings-search-status'),
      $empty = $root.find('.stb-settings-search-empty');

    $tabs.each(function () {
      var $tab = $(this),
        $panel = $root.find($tab.attr('href'));
      if (!$panel.length) {
        return;
      }
      $panels = $panels.add($panel);
      $tab.attr({ id: 'stb-tab-' + $panel.attr('id'), 'aria-controls': $panel.attr('id'), 'aria-selected': 'false', tabindex: '-1' });
      $panel.attr({ role: 'tabpanel', 'aria-labelledby': 'stb-tab-' + $panel.attr('id') }).addClass('stb-settings-panel');
    });

    $panels.not('#stb-anl-styling, #stb-anl-pro, .stb-panel-custom').each(function () {
      buildCards(this);
    });

    // Locked options of the free version: a PRO pill on the row, and a card of PRO options only gets one call to action
    if (!isPro) {
      $root.find('.stb-card .supsystic-pro-feature').each(function () {
        var $link = $(this);
        $link.closest('.setting-wrapper').addClass('stb-row-locked');
        $link.html('<i class="fa fa-lock" aria-hidden="true"></i> PRO').attr('title', $root.attr('data-label-pro'));
      });
      var unlockOnce = $root.attr('data-unlock-once') === '1',
        unlocked = {};
      $root.find('.stb-card').each(function () {
        var $card = $(this),
          $rows = $card.find('.stb-card-body > .setting-wrapper');
        if (!$rows.length || $rows.not('.stb-row-locked').length) {
          return;
        }
        var href = $card.find('.supsystic-pro-feature').first().attr('href') || $root.attr('data-pro-url'),
          section = $card.closest('.stb-settings-panel').attr('id');
        $card.addClass('stb-card-pro');
        $card.children('.setting-title').append('<span class="stb-pro-badge">PRO</span>');
        if (unlockOnce && unlocked[section]) {
          return;
        }
        unlocked[section] = true;
        $('<a class="stb-card-unlock" target="_blank" rel="noopener"></a>')
          .attr('href', href)
          .append($('<span></span>').text($root.attr('data-label-pro')))
          .append($('<strong></strong>').text($root.attr('data-label-unlock')).append(' <i class="fa fa-arrow-right" aria-hidden="true"></i>'))
          .appendTo($card);
      });
    }

    function activate(id) {
      var $tab = $tabs.filter('[href="#' + id + '"]');
      if (!$tab.length || !$root.find('#' + id).length) {
        $tab = $tabs.first();
        id = ($tab.attr('href') || '').substring(1);
      }
      $tabs.removeClass('active').attr({ 'aria-selected': 'false', tabindex: '-1' });
      $tab.addClass('active').attr({ 'aria-selected': 'true', tabindex: '0' });
      $panels.removeClass('is-active');
      $root.find('#' + id).addClass('is-active');
      writeStore(storageKey, id);
      $root.trigger('stbSectionChange', [id]);
      return $tab;
    }

    // Back to the top of the settings when the navigation is stuck above a long section
    function revealNav() {
      var top = $root.offset().top - ($('#wpadminbar').outerHeight() || 0) - 12;
      if ($(window).scrollTop() > top) {
        window.scrollTo(0, Math.max(0, top));
      }
    }

    $tabs.on('click', function (e) {
      e.preventDefault();
      if ($search.val()) {
        $search.val('');
        runSearch();
      }
      activate($(this).attr('href').substring(1));
      revealNav();
    });

    $tabs.on('keydown', function (e) {
      var index = $tabs.index(this),
        next = null;
      if (e.key === 'ArrowRight') next = (index + 1) % $tabs.length;
      else if (e.key === 'ArrowLeft') next = (index - 1 + $tabs.length) % $tabs.length;
      else if (e.key === 'Home') next = 0;
      else if (e.key === 'End') next = $tabs.length - 1;
      if (next !== null) {
        e.preventDefault();
        activate($tabs.eq(next).attr('href').substring(1)).trigger('focus');
      }
    });

    activate(readStore(storageKey) || $root.attr('data-default-section') || 'stb-anl-styling');

    // Search through all the settings
    function matches(text, words) {
      text = (text || '').replace(/\s+/g, ' ').toLowerCase();
      for (var i = 0; i < words.length; i++) {
        if (text.indexOf(words[i]) === -1) {
          return false;
        }
      }
      return true;
    }

    function isShown(row) {
      return row.style.display !== 'none' && !row.classList.contains('stHidden');
    }

    // The first option of the section, for sub-options that it opens in other cards
    function sectionOpener($panel) {
      return $panel
        .find('.stb-card-body > .setting-wrapper')
        .not('.setting-suboption')
        .filter(function () {
          return isShown(this);
        })
        .first();
    }

    function runSearch() {
      var query = $.trim($search.val() || '').toLowerCase(),
        words = query ? query.split(/\s+/) : [],
        total = 0;
      $root.find('.stb-search-hit').removeClass('stb-search-hit');
      $root.find('.stb-search-miss').removeClass('stb-search-miss');
      $tabs.removeClass('has-hits').removeAttr('data-hits');
      if (!words.length) {
        $root.removeClass('is-searching');
        $status.prop('hidden', true).text('');
        $empty.prop('hidden', true);
        return;
      }
      $root.addClass('is-searching');
      $panels.not('#stb-anl-pro').each(function () {
        var $panel = $(this),
          found = 0;
        if (this.id === 'stb-anl-styling') {
          // The palettes for words about them; style fields that can be seen (a closed group of them is opened)
          var $list = $panel.find('.stb-design-palette-list');
          if (matches($panel.children('.settings-block-title').text() + ' ' + $list.children('summary').text() + ' palette style theme', words)) {
            found++;
          } else {
            $list.addClass('stb-search-miss');
          }
          $panel.find('.stb-design-field label, .stb-design-enable').each(function () {
            if (!matches($(this).text(), words)) {
              return;
            }
            var group = $(this).closest('details').get(0);
            if (!this.getClientRects().length && group && !group.open && group.getClientRects().length) {
              group.open = true;
            }
            if (this.getClientRects().length) {
              $(this).closest('.stb-design-field, .stb-design-enable').addClass('stb-search-hit');
              found++;
            }
          });
        } else {
          $panel.find('.stb-card').each(function () {
            var $card = $(this),
              all = matches($card.children('.setting-title').text(), words),
              cardFound = 0,
              $rows = $card.find('.stb-card-body > .setting-wrapper');
            $rows.each(function (index) {
              var hit = all || matches($(this).children('.setting-label').text(), words);
              if (!hit) {
                return;
              }
              $(this).addClass('stb-search-hit');
              if (isShown(this)) {
                cardFound++;
                return;
              }
              // A hidden sub-option: show the option that opens it
              var opened = false;
              for (var j = index - 1; j >= 0; j--) {
                var parent = $rows.get(j);
                if (!$(parent).hasClass('setting-suboption') && isShown(parent)) {
                  if (!$(parent).hasClass('stb-search-hit')) {
                    $(parent).addClass('stb-search-hit');
                    cardFound++;
                  }
                  opened = true;
                  break;
                }
              }
              var $opener = opened ? $() : sectionOpener($panel);
              if ($opener.length && !$opener.hasClass('stb-search-hit')) {
                $opener.removeClass('stb-search-miss').addClass('stb-search-hit').closest('.stb-card').removeClass('stb-search-miss');
                found++;
              }
            });
            $rows.not('.stb-search-hit').addClass('stb-search-miss');
            if (!cardFound) {
              $card.addClass('stb-search-miss');
            }
            found += cardFound;
          });
        }
        if (!found) {
          $panel.addClass('stb-search-miss');
        } else {
          $tabs.filter('[href="#' + this.id + '"]').addClass('has-hits').attr('data-hits', found);
        }
        total += found;
      });
      $status.prop('hidden', !total).text(total ? $root.attr('data-label-found') + ' ' + total : '');
      $empty.prop('hidden', !!total);
    }

    $search.on('input search', runSearch).on('keydown', function (e) {
      if (e.key === 'Escape') {
        e.preventDefault();
        $search.val('');
        runSearch();
      }
    });

    // "/" opens the search while the settings are on screen
    $(document).on('keydown', function (e) {
      if (e.key !== '/' || e.ctrlKey || e.metaKey || e.altKey || !$root.hasClass('active')) {
        return;
      }
      var target = e.target,
        tag = (target.tagName || '').toLowerCase();
      if (tag === 'input' || tag === 'textarea' || tag === 'select' || target.isContentEditable) {
        return;
      }
      e.preventDefault();
      $search.trigger('focus');
      revealNav();
    });

    // The preview frame follows the chosen device (the width itself is set by tables.view.js)
    $root.find('.stb-device-switch .stb-anchor-nav-links').on('click', function () {
      $root.attr('data-preview-device', ($(this).attr('href') || '').replace('#stb-style-', ''));
    });

    $root.find('.stb-settings-jump').on('click', function (e) {
      var $preview = $root.find('#stb-preview-card');
      e.preventDefault();
      if ($preview.length) {
        $('html, body').animate({ scrollTop: $preview.offset().top - ($('#wpadminbar').outerHeight() || 0) - 12 }, 250);
      }
    });

    // The navigation gets a shadow once it sticks to the top
    if ('IntersectionObserver' in window && $nav.length) {
      var sentinel = document.createElement('div');
      sentinel.className = 'stb-settings-nav-sentinel';
      $nav[0].parentNode.insertBefore(sentinel, $nav[0]);
      new IntersectionObserver(
        function (entries) {
          $nav.toggleClass('is-stuck', !entries[0].isIntersecting && entries[0].boundingClientRect.top < 0);
        },
        { rootMargin: '-' + (($('#wpadminbar').outerHeight() || 0) + 1) + 'px 0px 0px 0px' }
      ).observe(sentinel);
    }
  }

  $(function () {
    $('.stb-settings-ui').each(function () {
      initRoot($(this));
    });
  });
})(jQuery);
