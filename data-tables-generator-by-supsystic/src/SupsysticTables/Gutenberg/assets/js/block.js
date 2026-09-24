(function (blocks, element, components, i18n) {
  var el = element.createElement;
  var __ = i18n.__;
  var blockData = window.SupsysticTablesBlockData || {};
  var hasTables = !!(blockData.tables && blockData.tables.length);
  var tableOptions = hasTables
    ? [{ label: __('— Select a table —', 'supsystic_tables'), value: '' }].concat(blockData.tables)
    : [{ label: __('No tables yet', 'supsystic_tables'), value: '' }];

  blocks.registerBlockType('supsystic-tables/data-table', {
    title: __('Data Tables by Supsystic', 'supsystic_tables'),
    description: __('Insert one of your Data Tables. Appears on the published page; no live preview here in the editor.', 'supsystic_tables'),
    icon: 'editor-table',
    category: 'widgets',
    keywords: [__('table', 'supsystic_tables'), __('data table', 'supsystic_tables'), 'supsystic'],
    attributes: {
      tableId: { type: 'string', default: '' },
    },
    edit: function (props) {
      var attrs = props.attributes;
      var selectedId = attrs.tableId || '';
      var blockProps = { className: props.className };

      var selectedLabel = selectedId
        ? (tableOptions.filter(function (o) { return o.value === selectedId; })[0] || {}).label
        : '';

      return el(
        'div',
        blockProps,
        el(
          components.PanelBody,
          { title: __('Table', 'supsystic_tables'), initialOpen: true },
          el(components.SelectControl, {
            label: __('Select table', 'supsystic_tables'),
            value: selectedId,
            options: tableOptions,
            onChange: function (value) {
              props.setAttributes({ tableId: value });
            },
          }),
        ),
        el(
          'p',
          {},
          selectedLabel
            ? __('Selected: ', 'supsystic_tables') + selectedLabel
            : __('Select a Data Table above. It will appear on the published page.', 'supsystic_tables'),
        ),
      );
    },
    save: function () {
      return null;
    },
  });
})(window.wp.blocks, window.wp.element, window.wp.components, window.wp.i18n);
