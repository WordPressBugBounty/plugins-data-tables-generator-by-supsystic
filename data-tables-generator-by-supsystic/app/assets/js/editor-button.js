/**
 * Classic editor (wp_editor / TinyMCE) toolbar button "Insert a Data Table": opens a dialog with the user's tables and
 * inserts the picked table's shortcode at the cursor. One button of the shared Supsystic group
 * (plugins/.claude/fix/shared/classic-editor-buttons.md). The tables, the shortcode name and the texts come in the
 * TinyMCE settings (supsystic_data_table), so the button needs no request and nothing loads outside the editor.
 *
 * window.SupsysticMceDialog is the dialog shared by every Supsystic plugin: whichever plugin loads first defines it,
 * the others reuse it, so all the buttons open the same dialog.
 */
(function (window) {
  'use strict';
  var tinymce = window.tinymce;
  if (!tinymce || !tinymce.PluginManager) {
    return;
  }

  if (!window.SupsysticMceDialog) {
    window.SupsysticMceDialog = (function () {
      var STYLE_ID = 'supsystic-mce-style';
      var CSS =
        // Toolbar buttons: native look, the Supsystic cyan on hover / focus, like the sidebar gear
        '.mce-toolbar .mce-btn.mce-supsystic-mce-btn:hover i.mce-ico,.mce-toolbar .mce-btn.mce-supsystic-mce-btn:focus i.mce-ico{color:#4ae8ea}' +
        '.supsystic-mce-backdrop{position:fixed;top:0;right:0;bottom:0;left:0;z-index:160000;display:flex;align-items:center;justify-content:center;padding:16px;background:rgba(15,23,42,.55);box-sizing:border-box}' +
        '.supsystic-mce-dialog{display:flex;flex-direction:column;width:440px;max-width:100%;max-height:calc(100vh - 32px);overflow:hidden;background:#fff;border-radius:12px;box-shadow:0 24px 64px rgba(15,23,42,.35);color:#1d2327;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen-Sans,Ubuntu,Cantarell,"Helvetica Neue",sans-serif;font-size:14px;line-height:1.5;text-align:left;box-sizing:border-box}' +
        '.supsystic-mce-dialog *{box-sizing:border-box}' +
        '.supsystic-mce-head{display:flex;align-items:center;gap:10px;padding:14px 12px 12px 20px;border-bottom:1px solid #e5e7eb}' +
        '.supsystic-mce-head .dashicons{width:22px;height:22px;color:#0e8a93;font-size:22px;line-height:1}' +
        '.supsystic-mce-head h2{flex:1;margin:0;padding:0;color:#1d2327;font-size:16px;font-weight:600;line-height:1.3}' +
        '.supsystic-mce-x{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;margin:0;padding:0;border:0;border-radius:50%;background:transparent;color:#50575e;font-size:24px;line-height:1;cursor:pointer;box-shadow:none}' +
        '.supsystic-mce-x:hover,.supsystic-mce-x:focus{background:#f0f0f1;color:#1d2327;outline:none}' +
        '.supsystic-mce-body{padding:16px 20px;overflow:auto}' +
        '.supsystic-mce-label{display:block;margin:0 0 6px;color:#1d2327;font-size:13px;font-weight:600}' +
        '.supsystic-mce-dialog .supsystic-mce-search,.supsystic-mce-dialog .supsystic-mce-select{display:block;width:100%;max-width:none;height:38px;min-height:38px;margin:0 0 12px;padding:0 12px;border:1px solid #c3c4c7;border-radius:8px;background-color:#fff;color:#1d2327;font-family:inherit;font-size:14px;line-height:36px;box-shadow:none}' +
        '.supsystic-mce-dialog .supsystic-mce-select{padding-right:32px}' +
        '.supsystic-mce-dialog .supsystic-mce-search:focus,.supsystic-mce-dialog .supsystic-mce-select:focus{border-color:#0e8a93;box-shadow:0 0 0 1px #0e8a93;outline:none}' +
        '.supsystic-mce-empty{margin:0 0 10px;color:#50575e}' +
        '.supsystic-mce-create{color:#0e8a93;font-weight:500;text-decoration:none}' +
        '.supsystic-mce-create:hover,.supsystic-mce-create:focus{color:#0b737a;text-decoration:underline}' +
        '.supsystic-mce-foot{display:flex;justify-content:flex-end;gap:8px;padding:12px 20px 16px;border-top:1px solid #e5e7eb;background:#f9fafb}' +
        '.supsystic-mce-dialog .supsystic-mce-cancel,.supsystic-mce-dialog .supsystic-mce-insert{min-height:36px;margin:0;padding:0 18px;border-radius:8px;font-family:inherit;font-size:13px;font-weight:600;line-height:34px;text-shadow:none;box-shadow:none;cursor:pointer}' +
        '.supsystic-mce-dialog .supsystic-mce-cancel{border:1px solid #c3c4c7;background:#fff;color:#1d2327}' +
        '.supsystic-mce-dialog .supsystic-mce-cancel:hover{border-color:#8c8f94;background:#f6f7f7}' +
        '.supsystic-mce-dialog .supsystic-mce-insert{border:1px solid #0e8a93;background:#0e8a93;color:#fff}' +
        '.supsystic-mce-dialog .supsystic-mce-insert:hover{border-color:#0b737a;background:#0b737a}' +
        '.supsystic-mce-dialog button:focus-visible,.supsystic-mce-create:focus-visible{outline:2px solid #4ae8ea;outline-offset:2px}';

      function ensureStyle(doc) {
        if (doc && doc.head && !doc.getElementById(STYLE_ID)) {
          var style = doc.createElement('style');
          style.id = STYLE_ID;
          style.textContent = CSS;
          doc.head.appendChild(style);
        }
      }

      function make(doc, tag, className, text) {
        var node = doc.createElement(tag);
        if (className) {
          node.className = className;
        }
        if (text) {
          node.textContent = text;
        }
        return node;
      }

      /**
       * @param {Object} o editor, icon (dashicon class), title, label, items [{id, title}] newest first, searchText,
       *   emptyText, createUrl, createText, insertText, cancelText, closeText, shortcode(id)
       */
      function open(o) {
        var editor = o.editor;
        var container = editor && editor.getContainer ? editor.getContainer() : null;
        var doc = container && container.ownerDocument ? container.ownerDocument : window.document;
        var items = o.items || [];
        ensureStyle(doc);

        var backdrop = make(doc, 'div', 'supsystic-mce-backdrop');
        var dialog = make(doc, 'div', 'supsystic-mce-dialog');
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        var head = make(doc, 'div', 'supsystic-mce-head');
        var heading = make(doc, 'h2', '', o.title);
        heading.id = 'supsystic-mce-title-' + Date.now();
        dialog.setAttribute('aria-labelledby', heading.id);
        var close = make(doc, 'button', 'supsystic-mce-x', '×');
        close.type = 'button';
        close.setAttribute('aria-label', o.closeText);
        head.appendChild(make(doc, 'span', 'dashicons ' + (o.icon || 'dashicons-shortcode')));
        head.appendChild(heading);
        head.appendChild(close);

        var body = make(doc, 'div', 'supsystic-mce-body');
        var foot = make(doc, 'div', 'supsystic-mce-foot');
        var cancel = make(doc, 'button', 'supsystic-mce-cancel', o.cancelText);
        cancel.type = 'button';
        foot.appendChild(cancel);
        var select = null;
        var insert = null;

        if (items.length) {
          var selectId = 'supsystic-mce-select-' + Date.now();
          var label = make(doc, 'label', 'supsystic-mce-label', o.label);
          label.setAttribute('for', selectId);
          select = make(doc, 'select', 'supsystic-mce-select');
          select.id = selectId;
          var fill = function (query) {
            var q = (query || '').toLowerCase();
            select.innerHTML = '';
            items.forEach(function (item) {
              var text = item.title + ' (#' + item.id + ')';
              if (!q || text.toLowerCase().indexOf(q) !== -1) {
                var option = make(doc, 'option', '', text);
                option.value = String(item.id);
                select.appendChild(option);
              }
            });
            if (insert) {
              insert.disabled = !select.options.length;
            }
          };
          body.appendChild(label);
          if (items.length > 15) {
            var search = make(doc, 'input', 'supsystic-mce-search');
            search.type = 'search';
            search.placeholder = o.searchText;
            search.setAttribute('aria-label', o.searchText);
            search.addEventListener('input', function () {
              fill(search.value);
            });
            body.appendChild(search);
          }
          body.appendChild(select);
          insert = make(doc, 'button', 'supsystic-mce-insert', o.insertText);
          insert.type = 'button';
          foot.appendChild(insert);
          fill('');
        } else {
          body.appendChild(make(doc, 'p', 'supsystic-mce-empty', o.emptyText));
        }
        if (o.createUrl) {
          var create = make(doc, 'a', 'supsystic-mce-create', o.createText + ' ↗');
          create.href = o.createUrl;
          create.target = '_blank';
          create.rel = 'noopener';
          body.appendChild(create);
        }

        dialog.appendChild(head);
        dialog.appendChild(body);
        dialog.appendChild(foot);
        backdrop.appendChild(dialog);
        doc.body.appendChild(backdrop);

        var done = function (shortcode) {
          doc.removeEventListener('keydown', onKey, true);
          if (backdrop.parentNode) {
            backdrop.parentNode.removeChild(backdrop);
          }
          if (editor) {
            editor.focus();
            if (shortcode) {
              editor.insertContent(shortcode);
            }
          }
        };
        var submit = function () {
          if (select && select.value) {
            done(o.shortcode(select.value));
          }
        };
        var onKey = function (event) {
          if (event.key === 'Escape' || event.keyCode === 27) {
            event.preventDefault();
            event.stopPropagation();
            done('');
          } else if ((event.key === 'Enter' || event.keyCode === 13) && insert && dialog.contains(event.target) && event.target.tagName !== 'BUTTON' && event.target.tagName !== 'A') {
            event.preventDefault();
            submit();
          }
        };
        doc.addEventListener('keydown', onKey, true);
        close.addEventListener('click', function () {
          done('');
        });
        cancel.addEventListener('click', function () {
          done('');
        });
        backdrop.addEventListener('mousedown', function (event) {
          if (event.target === backdrop) {
            done('');
          }
        });
        if (insert) {
          insert.addEventListener('click', submit);
        }
        var first = body.querySelector('input, select, a') || cancel;
        first.focus();
      }

      return { version: 1, open: open, ensureStyle: ensureStyle };
    })();
  }

  tinymce.PluginManager.add('supsysticDataTable', function (editor) {
    var raw = editor.getParam('supsystic_data_table');
    var data = raw;
    if (typeof raw === 'string') {
      try {
        data = JSON.parse(raw);
      } catch (e) {
        data = null;
      }
    }
    if (!data || !data.i18n) {
      return;
    }
    var t = data.i18n;
    editor.addCommand('supsysticDataTable', function () {
      window.SupsysticMceDialog.open({
        editor: editor,
        icon: 'dashicons-editor-table',
        title: t.title,
        label: t.label,
        items: data.items || [],
        searchText: t.search,
        emptyText: t.empty,
        createUrl: data.createUrl,
        createText: t.create,
        insertText: t.insert,
        cancelText: t.cancel,
        closeText: t.close,
        shortcode: function (id) {
          return '[' + data.shortcode + ' id=' + parseInt(id, 10) + ']';
        },
      });
    });
    var button = {
      tooltip: t.title,
      icon: 'dashicon dashicons-editor-table',
      classes: 'supsystic-mce-btn',
      cmd: 'supsysticDataTable',
    };
    editor.addButton('supsysticDataTable', button);
    // The old id of this button, for toolbars set up by hand (e.g. TinyMCE Advanced); not in the default toolbar
    editor.addButton('addShortcodeDataTable', button);
    editor.on('init', function () {
      var container = editor.getContainer();
      window.SupsysticMceDialog.ensureStyle(container ? container.ownerDocument : window.document);
    });
  });
})(window);
