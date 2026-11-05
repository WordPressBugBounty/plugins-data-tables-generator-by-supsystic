/**
 * Header of the table page: the title input and the copy button of the shortcode shown right now.
 */
(function ($) {
  'use strict';

  $(function () {
    // The title input is ready to type in as soon as it opens
    $('#stbTableTitleShell').on('click', function () {
      setTimeout(function () {
        var $input = $('#stbTableTitleTxt');
        if ($input.is(':visible') && !$input.is(':focus')) {
          $input.trigger('focus').trigger('select');
        }
      }, 350);
    });

    $('.stb-shortcode-copy').on('click', function () {
      var $button = $(this),
        $label = $button.find('span'),
        text = $label.text(),
        $input = $('.stbCopyTextCodeShowBlock:visible .stbCopyTextCode').first();

      if (!$input.length || $button.hasClass('is-done')) {
        return;
      }

      function done() {
        $button.addClass('is-done');
        $label.text($button.attr('data-copied') || text);
        setTimeout(function () {
          $button.removeClass('is-done');
          $label.text(text);
        }, 1500);
      }

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText($input.val()).then(done, function () {
          $input.trigger('select');
        });
        return;
      }
      $input.trigger('select');
      try {
        if (document.execCommand('copy')) {
          done();
        }
      } catch (e) {}
    });
  });
})(jQuery);
