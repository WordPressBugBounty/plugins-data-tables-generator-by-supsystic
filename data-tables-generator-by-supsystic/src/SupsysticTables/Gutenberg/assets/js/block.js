/**
 * Data Tables by Supsystic block. The table is picked in the block sidebar, or in the placeholder until one is picked.
 * When the editor canvas is an iframe the block shows the table as on the page: the block-preview REST route renders it
 * and lists the CSS/JS this table needs; they are added to the canvas iframe once (never to the admin screen itself),
 * and tables.shortcode.js initializes the inserted table there (sorting, search, paging work). Otherwise the block keeps
 * the picker. The saved block is unchanged: name, tableId attribute, save() = null.
 */
(function (wp) {
  if (!wp || !wp.blocks || !wp.element) {
    return;
  }
  var blocks = wp.blocks;
  var element = wp.element;
  var components = wp.components;
  var __ = wp.i18n.__;
  var blockEditor = wp.blockEditor || {};
  var apiFetch = wp.apiFetch;
  var el = element.createElement;
  var useState = element.useState;
  var useEffect = element.useEffect;
  var useRef = element.useRef;
  var data = window.SupsysticTablesBlockData || {};
  var tables = data.tables || [];
  var options = tables.length
    ? [{ label: __('— Select a table —', 'supsystic_tables'), value: '' }].concat(tables)
    : [{ label: __('No tables yet', 'supsystic_tables'), value: '' }];
  var previews = {};
  // The Supsystic icon and the Edit / Create links, like CES (plugins/.claude/fix/shared/builder-blocks-branding.md)
  // draggable=false + pointer-events:none (B073): otherwise dragging the block from the inserter drags the picture itself
  // and Gutenberg inserts a core/image with the logo instead of the block
  var icon = data.icon ? el('img', { src: data.icon, alt: '', width: 24, height: 24, draggable: false, style: { width: 24, height: 24, pointerEvents: 'none' } }) : 'editor-table';
  var EDIT_TEXT = {
    woo_product_table: __('Edit this WooCommerce product table in Data Tables', 'supsystic_tables'),
    pricing_table: __('Edit this pricing table in Data Tables', 'supsystic_tables'),
  };
  var CREATE = [
    ['#add', __('Create a table in Data Tables', 'supsystic_tables')],
    ['#add-woo', __('Create a WooCommerce product table in Data Tables', 'supsystic_tables')],
    ['#add-pricing', __('Create a pricing table in Data Tables', 'supsystic_tables')],
  ];

  function tableType(id) {
    return (tables.filter(function (o) { return o.value === id; })[0] || {}).type || 'default';
  }

  function links(id) {
    if (!data.canManage || !components.ExternalLink) {
      return null;
    }
    var items = [];
    if (id && data.editUrl) {
      items.push(el(components.ExternalLink, { key: 'edit', href: data.editUrl + encodeURIComponent(id) }, EDIT_TEXT[tableType(id)] || __('Edit this table in Data Tables', 'supsystic_tables')));
    }
    if (data.createUrl) {
      CREATE.forEach(function (c) {
        items.push(el(components.ExternalLink, { key: c[0], href: data.createUrl + c[0] }, c[1]));
      });
    }
    return el('div', { className: 'stb-block-links', style: { display: 'flex', flexDirection: 'column', gap: '8px', marginTop: '8px' } }, items);
  }

  function fetchPreview(id) {
    if (!previews[id]) {
      previews[id] = apiFetch({ path: (data.previewPath || '/supsystic-tables/v1/block-preview/') + encodeURIComponent(id) }).catch(function (error) {
        delete previews[id];
        throw error;
      });
    }
    return previews[id];
  }

  // In the canvas: links and forms of a preview must not take the editor away; the edit link of the table is hidden
  function prepareCanvas(doc) {
    if (doc.__stbPreviewReady) {
      return;
    }
    doc.__stbPreviewReady = true;
    var style = doc.createElement('style');
    style.id = 'supsystic-tables-block-preview-css';
    style.textContent = '.stb-block-preview .tblEditLink{display:none!important}';
    doc.head.appendChild(style);
    doc.addEventListener('click', function (event) {
      if (event.defaultPrevented) {
        return;
      }
      var link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
      if (link && link.closest('.stb-block-preview')) {
        event.preventDefault();
      }
    });
    doc.addEventListener('submit', function (event) {
      if (event.target && event.target.closest && event.target.closest('.stb-block-preview')) {
        event.preventDefault();
      }
    }, true);
  }

  // The <link>/<style>/<script> tags from the server, added to the canvas in order. A file already there is skipped;
  // an inline script (localized data of this table) runs when its text is new.
  function loadAssets(doc, html) {
    var win = doc.defaultView;
    doc.__stbInline = doc.__stbInline || {};
    doc.__stbQueue = (doc.__stbQueue || Promise.resolve()).then(function () {
      return new Promise(function (resolve) {
        var holder = doc.createElement('template');
        holder.innerHTML = html || '';
        var nodes = Array.prototype.slice.call(holder.content.childNodes);
        var index = 0;
        function next() {
          if (index >= nodes.length) {
            resolve();
            return;
          }
          var node = nodes[index++];
          if (node.nodeType !== 1) {
            next();
            return;
          }
          var id = node.id || '';
          if (node.tagName === 'LINK') {
            if (!(id && doc.getElementById(id)) && !doc.querySelector('link[href="' + node.getAttribute('href') + '"]')) {
              doc.head.appendChild(doc.importNode(node, true));
            }
            next();
            return;
          }
          if (node.tagName === 'STYLE') {
            if (doc.__stbInline[id || node.textContent] !== node.textContent) {
              doc.__stbInline[id || node.textContent] = node.textContent;
              doc.head.appendChild(doc.importNode(node, true));
            }
            next();
            return;
          }
          if (node.tagName !== 'SCRIPT') {
            next();
            return;
          }
          var src = node.getAttribute('src');
          if (src) {
            // The canvas may have jQuery already: a second copy would drop what other scripts attached to the first
            if ((id && doc.getElementById(id)) || doc.querySelector('script[src="' + src + '"]') || (win.jQuery && /^jquery(-core|-migrate)?-js$/.test(id))) {
              next();
              return;
            }
          } else if (doc.__stbInline[id || node.textContent] === node.textContent) {
            next();
            return;
          }
          var script = doc.createElement('script');
          Array.prototype.forEach.call(node.attributes, function (attribute) {
            if (!(attribute.name === 'id' && doc.getElementById(attribute.value))) {
              script.setAttribute(attribute.name, attribute.value);
            }
          });
          if (src) {
            script.onload = next;
            script.onerror = next;
            doc.head.appendChild(script);
          } else {
            doc.__stbInline[id || node.textContent] = node.textContent;
            script.text = node.textContent;
            doc.head.appendChild(script);
            next();
          }
        }
        next();
      });
    });
    return doc.__stbQueue;
  }

  function tableLabel(id) {
    return (tables.filter(function (o) { return o.value === id; })[0] || {}).label || '';
  }

  function Edit(props) {
    var ref = useRef(null);
    var blockProps = blockEditor.useBlockProps ? blockEditor.useBlockProps({ ref: ref }) : { className: props.className, ref: ref };
    var id = props.attributes.tableId || '';
    // null: not known yet; false: no iframe canvas here (picker only); true: preview
    var canvasState = useState(null);
    var canvas = canvasState[0];
    var viewState = useState({ status: 'idle', html: '' });
    var view = viewState[0];
    var setView = viewState[1];

    useEffect(function () {
      var doc = ref.current && ref.current.ownerDocument;
      canvasState[1](!!(doc && doc !== window.document && apiFetch));
    }, []);

    useEffect(function () {
      if (!id || canvas !== true) {
        return;
      }
      var alive = true;
      var doc = ref.current.ownerDocument;
      prepareCanvas(doc);
      setView({ status: 'loading', html: '' });
      fetchPreview(id)
        .then(function (response) {
          return loadAssets(doc, response.assets || '').then(function () {
            return response;
          });
        })
        .then(function (response) {
          if (alive) {
            setView({ status: 'ready', html: response.html || '' });
          }
        })
        .catch(function () {
          if (alive) {
            setView({ status: 'error', html: '' });
          }
        });
      return function () {
        alive = false;
      };
    }, [id, canvas]);

    function select(label) {
      return el(components.SelectControl, {
        label: label,
        value: id,
        options: options,
        onChange: function (value) {
          props.setAttributes({ tableId: value });
        },
      });
    }

    var inspector = blockEditor.InspectorControls
      ? el(
          blockEditor.InspectorControls,
          {},
          el(
            components.PanelBody,
            { title: __('Table', 'supsystic_tables'), initialOpen: true },
            select(__('Select table', 'supsystic_tables')),
            links(id)
          )
        )
      : null;

    function placeholder(instructions, withSelect) {
      return el(
        'div',
        blockProps,
        inspector,
        el(
          components.Placeholder,
          { icon: icon, label: __('Data Tables', 'supsystic_tables'), instructions: instructions },
          withSelect ? select(__('Select table', 'supsystic_tables')) : null,
          // Nothing picked yet: the Create links right in the placeholder
          id ? null : links('')
        )
      );
    }

    if (!id) {
      return placeholder(
        tables.length
          ? __('Pick a table to show it here as on the page.', 'supsystic_tables')
          : __('No tables yet. Create one in Data Tables, then pick it here.', 'supsystic_tables'),
        tables.length > 0
      );
    }
    if (canvas === false) {
      return placeholder(__('Selected: ', 'supsystic_tables') + tableLabel(id) + '. ' + __('It appears on the published page.', 'supsystic_tables'), true);
    }
    if (view.status === 'error') {
      return placeholder(__('The preview of this table could not be loaded. It still appears on the published page.', 'supsystic_tables'), true);
    }
    if (view.status !== 'ready') {
      return el('div', blockProps, inspector, el(components.Spinner, {}));
    }
    return el(
      'div',
      blockProps,
      inspector,
      el(
        'div',
        { className: 'stb-block-preview', style: { position: 'relative' } },
        el('div', { className: 'stb-block-preview__table', dangerouslySetInnerHTML: { __html: view.html } }),
        // Until the block is selected a click selects it, then the table works (sort, search, pages)
        props.isSelected
          ? null
          : el('div', { className: 'stb-block-preview__cover', style: { position: 'absolute', top: 0, right: 0, bottom: 0, left: 0, zIndex: 50, cursor: 'pointer' } })
      )
    );
  }

  blocks.registerBlockType('supsystic-tables/data-table', {
    // In the shared "Supsystic" category, so the title has no "by Supsystic" (plugins/.claude/fix/shared/gutenberg-supsystic-category.md)
    title: __('Data Tables', 'supsystic_tables'),
    description: __('Insert one of your tables: a data table, a WooCommerce product table or a pricing table, shown here as on the page.', 'supsystic_tables'),
    icon: icon,
    category: 'supsystic',
    keywords: [__('table', 'supsystic_tables'), __('data table', 'supsystic_tables'), __('pricing table', 'supsystic_tables'), 'supsystic'],
    attributes: {
      tableId: { type: 'string', default: '' },
    },
    edit: Edit,
    save: function () {
      return null;
    },
  });

  // The Supsystic icon on the shared category (every Supsystic plugin may set it; the same icon)
  if (blocks.updateCategory && data.icon) {
    blocks.updateCategory('supsystic', { icon: icon });
  }
})(window.wp);
