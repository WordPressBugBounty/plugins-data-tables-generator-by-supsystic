// The page has this script only when a save in the free version would reset PRO settings of the table.
(function ($) {
  var answered = false,
    pendingSave = null,
    previousFocus = null;

  function getDialog() {
    return $('#stb-pro-guard');
  }

  function close() {
    getDialog().prop('hidden', true);
    $(document).off('keydown.stbProGuard');
    pendingSave = null;
    if (previousFocus && previousFocus.focus) {
      previousFocus.focus();
    }
    previousFocus = null;
  }

  function keepFocusInside(e) {
    var $controls = getDialog().find('a[href], button'),
      first = $controls.get(0),
      last = $controls.get($controls.length - 1);

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  function open(save) {
    var $dialog = getDialog();

    pendingSave = save;
    previousFocus = document.activeElement;
    // The editors have their own stacking contexts: on the body it covers the whole page
    $dialog.appendTo(document.body).prop('hidden', false);
    $dialog.find('[data-stb-pg="cancel"]').trigger('focus');
    $(document)
      .off('keydown.stbProGuard')
      .on('keydown.stbProGuard', function (e) {
        if (e.key === 'Escape') {
          close();
        } else if (e.key === 'Tab') {
          keepFocusInside(e);
        }
      });
  }

  window.stbProGuard = {
    // A save still has to ask. After the answer nothing is left to protect on this page.
    isArmed: function () {
      return !answered && getDialog().length > 0;
    },
    // Runs the save at once, or when the person agrees to reset the PRO settings
    run: function (save) {
      if (!this.isArmed()) {
        save();
        return;
      }
      open(save);
    },
  };

  $(document).on('click', '#stb-pro-guard [data-stb-pg="cancel"]', close);
  $(document).on('mousedown', '#stb-pro-guard', function (e) {
    if (e.target === this) {
      close();
    }
  });
  $(document).on('click', '#stb-pro-guard [data-stb-pg="confirm"]', function () {
    var save = pendingSave;

    answered = true;
    close();
    if (save) {
      save();
    }
  });
})(window.jQuery);
