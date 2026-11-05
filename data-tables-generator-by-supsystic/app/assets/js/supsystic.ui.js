/*
 * Main UI file.
 *
 * Here we activate and configure all scripts or
 * jQuery plugins required for UI.
 *
 */
(function ($, window, vendor, undefined) {
  // Every notice saves its own answer (the WordPress close button counts as "hide")
  jQuery(document).on('click', '.supsystic-admin-notice [data-response-code], .supsystic-admin-notice .notice-dismiss', function (event) {
    var $notice = jQuery(this).closest('.supsystic-admin-notice');
    var responseCode = jQuery(this).data('response-code') || 'hide';
    var responseType = jQuery(this).data('response-type') || $notice.find('[data-response-type]').first().data('response-type');

    if (!responseType || $notice.data('responded')) {
      return;
    }
    $notice.data('responded', true);

    $notice.slideUp(200, function () {
      jQuery(this).remove();
    });

    window.supsystic.Tables.request(
      {
        module: 'tables',
        action: 'reviewNoticeResponse',
        nonce: DTGS_NONCE,
      },
      {
        responseCode: responseCode,
        responseType: responseType,
      }
    );
  });

  $(document).ready(function () {
    var activModule = /\module=([^&#]*)/i.test($('.supsystic-navigation').find('li.active').find('a').attr('href')) ? RegExp.$1 : 'tables';
    $('#toplevel_page_supsystic-tables')
      .find('.wp-submenu li')
      .removeClass('current')
      .each(function () {
        if (
          $(this)
            .find('a[href$="&module=' + activModule + '"]')
            .size()
        ) {
          $(this).addClass('current');
        }
      });
    var navWidth = $('.supsystic-navigation').width();
    $('.supsystic-navigation a span').each(function () {
      var el = $(this),
        fontSize = parseFloat(el.css('font-size'));
      while (el.width() > navWidth) {
        el.css('font-size', (fontSize -= 1));
      }
    });

    $('[data-toggle="tooltip"]').tooltipster({
      contentAsHTML: true,
      interactive: true,
      position: 'top-left',
      delay: 1000,
      updateAnimation: false,
      animation: '',
      functionReady: function (origin, e) {
        $('img').load(function () {
          origin.tooltipster('reposition');
        });
      },
      hideOnClick: 1,
    });

    // The options of a checkbox follow its state, so a double click or a change from code
    // cannot leave them out of step. With data-target-toggle-invert they show while it is off.
    $('[data-target-toggle]')
      .each(function () {
        $(this).data('toggleOn', this.checked);
      })
      .on('change ifChanged', function () {
        var $this = $(this),
          isOn = $this.is(':checked');

        // One click fires both events
        if ($this.data('toggleOn') === isOn) {
          return;
        }
        $this.data('toggleOn', isOn);

        $($this.data('target-toggle'))
          .stop(true, true)
          [isOn !== Boolean($this.data('target-toggle-invert')) ? 'fadeIn' : 'fadeOut'](200);
      });

    $('input.stbCopyTextCode').click(function () {
      this.select();
    });

    /* Minimum height for the container */
    var $autoHeight = $('.supsystic-item'),
      naviationHeight = $('.supsystic-navigation').outerHeight();

    $autoHeight.each(function () {
      $(this).css({ minHeight: naviationHeight });
    });

    $('input').iCheck({
      checkboxClass: 'icheckbox_minimal',
      radioClass: 'iradio_minimal',
    });
  });
})(jQuery, window, 'supsystic');
