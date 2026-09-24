(function ($, app) {
  $(document).ready(function () {
    app.initTablesOnPage();

    // Some contexts insert a table's markup into the DOM after this
    // document-ready handler already ran once - e.g. Elementor's editor
    // updating a widget via ajax, or a Gutenberg block's <ServerSideRender>
    // preview injecting its fetched HTML. Watch for that and initialize
    // just the newly added, not-yet-initialized tables.
    if (typeof MutationObserver !== 'undefined' && document.body) {
      var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
          Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
            if (node.nodeType !== 1) {
              return;
            }

            var tables = node.classList && node.classList.contains('supsystic-table') ? $(node) : $(node).find('.supsystic-table');

            tables.each(function () {
              var $table = $(this);
              if ($table.hasClass('dataTable')) {
                return;
              }
              var id = $table.data('id');
              if (id) {
                app.initTablesOnPage(id);
              }
            });
          });
        });
      });

      observer.observe(document.body, { childList: true, subtree: true });
    }
  });
})(window.jQuery, window.supsystic.Tables);
