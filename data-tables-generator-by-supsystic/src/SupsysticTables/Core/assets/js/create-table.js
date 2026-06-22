(function ($, app) {
  $(document).ready(function () {
    $('a[href="admin.php?page=supsystic-tables#add"]').attr('href', '#add');

    var currentStep = 1,
      totalSteps = 2,
      $createBtn = $('.create-table'),
      $error = $('#formError'),
      $input = $('#dtgAddDialog_title'),
      $cols = $('#dtgAddDialog_cols'),
      $rows = $('#dtgAddDialog_rows'),
      $tableType = $('input[name="dtgs_table_type"]'),
      wooAvailable = $('#dtgAddDialog input[name="dtgs_table_type"][value="woocommerce"]').prop('disabled') !== true,
      $stepTwoTitle = $('#dtgsWizardStepTwoTitle'),
      $dataSizeStep = $('#dtgsWizardDataSize'),
      $wooReadyStep = $('#dtgsWizardWooReady'),
      $steps = $('.dtgs-wizard-step'),
      $progress = $('#dtgsWizardProgress'),
      $dialog = $('#dtgAddDialog').dialog({
        width: 760,
        modal: true,
        autoOpen: false,
        close: function () {
          window.location.hash = '';
          resetCreateButton();
        },
        buttons: [
          {
            text: 'Back',
            class: 'dtgsWizardBack',
            click: function () {
              if (currentStep > 1) {
                currentStep--;
                updateWizard();
              }
            },
          },
          {
            text: 'Next',
            class: 'dtgsWizardNext button-primary',
            click: function () {
              if (currentStep < totalSteps) {
                currentStep++;
                updateWizard();
              }
            },
          },
          {
            text: 'Create',
            class: 'dtgsWizardCreate button-primary',
            click: function () {
              submitCreate($(this).closest('.ui-dialog').find('.dtgsWizardCreate'));
            },
          },
          {
            text: 'Cancel',
            click: function () {
              $dialog.dialog('close');
            },
          },
        ],
      });

    function getCheckedValue($items, fallback) {
      var $checked = $items.filter(':checked');
      return $checked.length ? $checked.val() : fallback;
    }

    function setRadioValue($items, value) {
      var $target = $items.filter('[value="' + value + '"]');

      if (!$target.length || $target.prop('disabled')) {
        return;
      }

      $items.prop('checked', false);
      $target.prop('checked', true);

      if ($.fn.iCheck) {
        $items.iCheck('update');
        $target.iCheck('check');
      }
    }

    function showError(message) {
      $error.find('p').text(message);
      $error.fadeIn();
    }

    function resetCreateButton() {
      var $button = $dialog.dialog('widget').find('.dtgsWizardCreate');
      $button.attr('disabled', false).html('Create');
    }

    function syncProOptions() {
      if (typeof SDT_DATA === 'undefined' || !SDT_DATA.isPro) {
        return;
      }

      $('#dtgAddDialog .dtgs-create-option.is-disabled')
        .filter(function () {
          return $(this).attr('data-disabled-reason') !== 'woocommerce';
        })
        .removeClass('is-disabled')
        .find('input')
        .prop('disabled', false);
    }

    function updateWizard() {
      syncProOptions();

      var tableType = getCheckedValue($tableType, 'data');
      if (tableType === 'woocommerce' && !wooAvailable) {
        setRadioValue($tableType, 'data');
        tableType = 'data';
      }
      var isWooCatalog = tableType === 'woocommerce';

      $steps.removeClass('is-active').hide();
      $steps.filter('[data-wizard-step="' + currentStep + '"]').addClass('is-active').show();
      $progress.text('Step ' + currentStep + ' of ' + totalSteps);
      $stepTwoTitle.text(isWooCatalog ? 'Step 2: Ready to Create' : 'Step 2: Rows x Columns');

      $dataSizeStep.toggle(!isWooCatalog && currentStep === 2);
      $wooReadyStep.toggle(isWooCatalog && currentStep === 2);

      syncDialogButtons();
    }

    function syncDialogButtons() {
      var $pane = $dialog.dialog('widget').find('.ui-dialog-buttonpane');
      $pane.find('.dtgsWizardBack').toggle(currentStep > 1);
      $pane.find('.dtgsWizardNext').toggle(currentStep < totalSteps);
      $pane.find('.dtgsWizardCreate').toggle(currentStep === totalSteps);
    }

    function submitCreate($button) {
      if ($input.val().length == 0 || $input.val().length > 255) {
        showError("Title can't be empty or more than 255 characters");
        currentStep = 1;
        updateWizard();
        return;
      }

      if ($dataSizeStep.is(':visible')) {
        if (isNaN($cols.val()) || !$cols.val().length || isNaN($rows.val()) || !$rows.val().length) {
          showError('Columns and rows value must be a numbers and not empty.');
          currentStep = 2;
          updateWizard();
          return;
        }

        if (parseInt($cols.val()) < $cols.attr('min')) {
          showError("Columns value can't be less then " + $cols.attr('min') + '.');
          currentStep = 2;
          updateWizard();
          return;
        }

        if (parseInt($rows.val()) < $rows.attr('min')) {
          showError("Rows value can't be less then " + $rows.attr('min') + '.');
          currentStep = 2;
          updateWizard();
          return;
        }
      }

      $button.attr('disabled', true);
      $button.html(app.createSpinner());

      $error.fadeOut();

      var cols = getCheckedValue($tableType, 'data') === 'woocommerce' ? 1 : $cols.val();
      var rows = getCheckedValue($tableType, 'data') === 'woocommerce' ? 1 : $rows.val();
      var tableType = getCheckedValue($tableType, 'data');
      if (tableType === 'woocommerce' && !wooAvailable) {
        tableType = 'data';
      }
      var sourceType = tableType === 'woocommerce' ? 'woocommerce' : 'manual';

      app
        .request(
          { module: 'tables', action: 'create', nonce: DTGS_NONCE },
          {
            title: $input.val(),
            rows: rows,
            cols: cols,
            table_type: tableType,
            source_type: sourceType,
            update_mode: 'manual',
            design_mode: 'default',
          }
        )
        .done(function (response) {
          window.location.href = response.url + '&new=1&cols=' + cols + '&rows=' + rows;
        })
        .fail(function (message) {
          showError(message);
          resetCreateButton();
        });
    }

    $input.on('focus', function () {
      $error.fadeOut();
    });

    $('#dtgAddDialog').on('click', '.dtgs-create-option:not(.is-disabled)', function () {
      var $radio = $(this).find('input[type="radio"]');

      if ($radio.length) {
        setRadioValue($('input[name="' + $radio.attr('name') + '"]'), $radio.val());
      }
    });

    $tableType.on('change ifChanged', function () {
      updateWizard();
    });

    $createBtn.on('click', function () {
      currentStep = 1;
      updateWizard();
      $dialog.dialog('open');
    });

    $(window)
      .on('hashchange', function () {
        if (window.location.hash === '#add') {
          // To prevent error if data not loaded completely
          setTimeout(function () {
            if (typeof window.editor != 'undefined') {
              window.editor.deselectCell();
            }
            currentStep = 1;
            updateWizard();
            $dialog.dialog('open');
          }, 500);
        }
      })
      .trigger('hashchange');

    updateWizard();
  });
})(jQuery, window.supsystic.Tables);
