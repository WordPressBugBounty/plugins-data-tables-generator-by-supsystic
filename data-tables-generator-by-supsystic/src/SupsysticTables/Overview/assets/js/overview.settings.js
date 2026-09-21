(function ($) {
  'use strict';

  $(document).ready(function () {
    $('.dtgs-overview a[href^="#"]')
      .not('.dtgs-overview-create-table')
      .on('click', function (event) {
        var target = $(this.getAttribute('href'));

        if (!target.length) {
          return;
        }

        event.preventDefault();
        $('html, body').animate(
          {
            scrollTop: Math.max(0, target.offset().top - 38),
          },
          260
        );
      });

    $('.dtgs-overview-create-table').on('click', function (event) {
      event.preventDefault();
      if (window.location.hash === '#add') {
        $(window).trigger('hashchange');
      } else {
        window.location.hash = 'add';
      }
    });
  });
})(jQuery);
