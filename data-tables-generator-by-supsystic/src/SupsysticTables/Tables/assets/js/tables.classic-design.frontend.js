(function ($) {
  'use strict';
  function copyTokens(source, target) {
    if (!source || !target) return;
    // Copy only our public tokens. Never copy table layout or unrelated styles.
    for (var i = 0; i < source.style.length; i++) {
      var key = source.style[i];
      if (key.indexOf('--stb-') === 0) target.style.setProperty(key, source.style.getPropertyValue(key));
    }
  }
  function globalSearch() {
    $('.supsystic-tables-global-page-search-wrapper').each(function () {
      var id = Number($(this).attr('data-stb-style-table'));
      var table = id ? $('table.stb-classic-table[data-id="' + id + '"]').first() : $('.stb-classic-design table.stb-classic-table').first();
      $(this).toggleClass('stb-classic-global-search', !!table.length);
      if (table.length) copyTokens(table[0], this);
    });
  }
  function sortingArrows() {
    $('table.stb-classic-sortable').find('th.sorting,th.sorting_asc,th.sorting_desc').each(function () {
      // Also replaces the bitmap from the legacy "sorting on hover" setting.
      this.style.setProperty('background-image', 'none', 'important');
      if (!$(this).children('.stb-classic-sort').length) $(this).append('<span class="stb-classic-sort" aria-hidden="true"></span>');
    });
  }
  function refresh() { sortingArrows(); globalSearch(); }
  $(function () {
    refresh();
    $(document).on('init.dt.stbClassic draw.dt.stbClassic stb:classic-design', refresh);
    // jQuery UI and contextMenu append their surfaces to body, outside the table.
    $(document).on('focusin.stbClassic click.stbClassic', '.stb-classic-design', function (event) {
      var source = this;
      if ($(event.target).is('.datetimeArea,.datefield,input')) {
        var calendar = document.getElementById('ui-datepicker-div');
        if (calendar) { copyTokens(source, calendar); $(calendar).addClass('stb-classic-calendar'); }
      }
    });
    // contextMenu stops bubbling on the cell. Observe the opening gesture in
    // capture phase so its body-level menu gets the correct table's palette.
    document.addEventListener('contextmenu', function (event) {
      var surface = $(event.target).closest('.supsystic-tables-wrap');
      if (!surface.length) return;
      setTimeout(function () {
        $('.context-menu-list:visible').each(function () {
          var active = surface.hasClass('stb-classic-design');
          $(this).toggleClass('stb-classic-context', active);
          if (active) copyTokens(surface[0], this);
        });
      }, 0);
    }, true);
    $(document).on('focusin.stbClassic', '.supsystic-tables-wrap:not(.stb-classic-design) .datetimeArea', function () { $('#ui-datepicker-div').removeClass('stb-classic-calendar'); });
    // Table previews and page builders can insert tables after document ready.
    if (window.MutationObserver) new MutationObserver(function (changes) {
      var relevant = changes.some(function (change) { return Array.prototype.some.call(change.addedNodes, function (node) { return node.nodeType === 1 && (node.matches('.supsystic-tables-wrap,.supsystic-tables-global-page-search-wrapper') || node.querySelector('.supsystic-tables-wrap,.supsystic-tables-global-page-search-wrapper')); }); });
      if (relevant) refresh();
    }).observe(document.body, { childList: true, subtree: true });
  });
})(jQuery);
