/**
 * PRO showcase of the free version (Diagrams and Table History tabs): chart engine filter, screenshot switches,
 * a lightbox for the pictures and smooth links inside the showcase.
 */
(function ($) {
  'use strict';

  $(function () {
    $('[data-showcase]').each(function () {
      var $showcase = $(this),
        $box = $showcase.find('.stb-sc-lightbox'),
        $items = $(),
        index = 0,
        opener = null;

      // Above the WordPress admin menu and bar
      $box.toggleClass('stb-sc-brand', $showcase.hasClass('stb-sc-brand')).appendTo(document.body);

      // Chart engine filter
      $showcase.on('click', '.stb-sc-filter button', function () {
        var filter = $(this).attr('data-filter');
        $(this).addClass('is-active').attr('aria-pressed', 'true').siblings().removeClass('is-active').attr('aria-pressed', 'false');
        $showcase.find('.stb-sc-chart').each(function () {
          this.hidden = filter !== 'all' && $(this).attr('data-engine') !== filter;
        });
      });

      // Screenshots with a switch above them
      $showcase.on('click', '.stb-sc-shot-tabs button', function () {
        var $shot = $(this).closest('.stb-sc-shot');
        $(this).addClass('is-active').attr('aria-pressed', 'true').siblings().removeClass('is-active').attr('aria-pressed', 'false');
        $shot.find('.stb-sc-window .stb-sc-zoom').removeClass('is-active').eq(parseInt($(this).attr('data-shot'), 10) || 0).addClass('is-active');
      });

      // Lightbox: goes through the pictures that can be seen now
      function show(i) {
        if (!$items.length) {
          return;
        }
        index = (i + $items.length) % $items.length;
        var $item = $items.eq(index),
          $img = $item.find('img');
        $box.find('figure img').attr({ src: $item.attr('data-full') || $img.attr('src'), alt: $img.attr('alt') || '' });
        $box.find('figcaption').text($item.attr('data-caption') || $img.attr('alt') || '');
        $box.find('.stb-sc-lightbox-nav').prop('hidden', $items.length < 2);
      }

      function open(item) {
        $items = $showcase.find('.stb-sc-zoom').filter(function () {
          return $(this).is(':visible');
        });
        opener = item;
        show(Math.max(0, $items.index(item)));
        $box.prop('hidden', false);
        $('body').addClass('stb-sc-noscroll');
        $box.find('.stb-sc-lightbox-close').trigger('focus');
      }

      function close() {
        $box.prop('hidden', true);
        $('body').removeClass('stb-sc-noscroll');
        if (opener) {
          opener.focus();
        }
      }

      $showcase.on('click', '.stb-sc-zoom', function () {
        open(this);
      });
      $box.on('click', function (e) {
        if (e.target === this || $(e.target).is('figure')) {
          close();
        }
      });
      $box.on('click', '.stb-sc-lightbox-close', close);
      $box.on('click', '.stb-sc-lightbox-prev', function () {
        show(index - 1);
      });
      $box.on('click', '.stb-sc-lightbox-next', function () {
        show(index + 1);
      });
      $(document).on('keydown', function (e) {
        if (!$box.length || $box.prop('hidden')) {
          return;
        }
        if (e.key === 'Escape') {
          e.preventDefault();
          close();
        } else if (e.key === 'ArrowLeft') {
          show(index - 1);
        } else if (e.key === 'ArrowRight') {
          show(index + 1);
        }
      });

      // Links to the parts of the showcase
      $showcase.on('click', 'a[href^="#stb-sc-"]', function (e) {
        var $target = $($(this).attr('href'));
        if ($target.length) {
          e.preventDefault();
          $('html, body').animate({ scrollTop: $target.offset().top - ($('#wpadminbar').outerHeight() || 0) - 16 }, 300);
        }
      });
    });
  });
})(jQuery);
