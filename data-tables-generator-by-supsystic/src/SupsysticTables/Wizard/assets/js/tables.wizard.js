// The table wizard: questions on the left, a live preview on the right. Opens by the #add link on any page of the plugin.
(function ($, window, document) {
  'use strict';

  var cfg = null,
    $root = null,
    state = null,
    hover = {},
    previousFocus = null,
    loading = false,
    renderedStep = null,
    isWelcome = !!(window.STB_WIZARD && window.STB_WIZARD.welcome);

  /* ---- Helpers ---- */

  function t(text) {
    return cfg && cfg.strings && cfg.strings[text] ? cfg.strings[text] : text;
  }

  function esc(value) {
    return String(value === null || value === undefined ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function isLocked(item) {
    return !!(item && item.pro) && !cfg.isPro;
  }

  function proPill(item) {
    return isLocked(item) ? '<span class="stbw-pro">PRO</span>' : '';
  }

  function keys(object) {
    return Object.keys(object || {});
  }

  function newState() {
    return {
      mode: 'guided',
      step: 'goal',
      goal: null,
      dataset: null,
      empty: false,
      interaction: 'explore',
      tools: {},
      size: 'some',
      palette: 'paper',
      tryPalette: null,
      paletteTone: '',
      paletteCollection: '',
      chart: null,
      source: null,
      pasted: { text: '', hasHeader: true, rows: null },
      blankRows: 5,
      blankCols: 4,
      woo: { source: 'latest', layout: 'catalog', tools: { ordering: true, paging: true, info: true }, categories: [] },
      pricing: { set: 'saas', count: 3, extras: { badge: true, hover: true }, template: 'classic', tryTemplate: null },
      title: '',
      titleEdited: false,
      device: 'desktop',
      tried: {},
      notice: '',
      quick: { type: 'data', title: '', rows: 5, cols: 4 },
      expert: { type: 'data', title: '', rows: 5, cols: 4, source: 'manual', update: 'manual', design: 'default' },
      creating: false,
      progress: 0,
      created: null,
      error: '',
    };
  }

  function currentGoal() {
    return state.goal ? cfg.goals[state.goal] : null;
  }

  function goalKind() {
    var goal = currentGoal();
    return goal ? goal.kind : '';
  }

  function isDataKind() {
    return goalKind() === 'data' || goalKind() === 'own';
  }

  /* ---- The question matrix: every goal has its own path ---- */

  function hasChartStep() {
    var content = getContent();
    return !!(content && content.chart);
  }

  function getSteps() {
    var kind = goalKind(),
      steps = [{ id: 'goal', label: t('Your goal'), icon: 'fa-flag-o' }];

    if (kind === 'data') {
      steps.push({ id: 'starter', label: t('Your data'), icon: 'fa-table' });
    } else if (kind === 'own') {
      steps.push({ id: 'source', label: t('Your data'), icon: 'fa-clipboard' });
    } else if (kind === 'woo') {
      steps.push({ id: 'products', label: t('Products'), icon: 'fa-cubes' });
      steps.push({ id: 'layout', label: t('Layout'), icon: 'fa-columns' });
      steps.push({ id: 'shopping', label: t('Shopping tools'), icon: 'fa-shopping-basket' });
      steps.push({ id: 'design', label: t('Design'), icon: 'fa-paint-brush' });
    } else if (kind === 'pricing') {
      steps.push({ id: 'plans', label: t('Plans'), icon: 'fa-th-large' });
      steps.push({ id: 'extras', label: t('Extras'), icon: 'fa-magic' });
      steps.push({ id: 'theme', label: t('Theme'), icon: 'fa-paint-brush' });
    } else {
      steps.push({ id: 'starter', label: t('Your data'), icon: 'fa-table', idle: true });
      steps.push({ id: 'visitors', label: t('Visitors'), icon: 'fa-users', idle: true });
      steps.push({ id: 'design', label: t('Design'), icon: 'fa-paint-brush', idle: true });
    }
    if (kind === 'data' || kind === 'own') {
      steps.push({ id: 'visitors', label: t('Visitors'), icon: 'fa-users' });
      steps.push({ id: 'design', label: t('Design'), icon: 'fa-paint-brush' });
      if (hasChartStep()) {
        steps.push({ id: 'charts', label: t('Charts'), icon: 'fa-bar-chart' });
      }
    }
    steps.push({ id: 'finish', label: t('Finish'), icon: 'fa-check', idle: !kind });

    return steps;
  }

  function stepIndex() {
    var steps = getSteps();
    for (var i = 0; i < steps.length; i++) {
      if (steps[i].id === state.step) {
        return i;
      }
    }
    return 0;
  }

  function applyInteraction(key) {
    var interaction = cfg.interactions[key];
    if (!interaction) {
      return;
    }
    state.interaction = key;
    state.tools = {};
    keys(interaction.tools).forEach(function (tool) {
      state.tools[tool] = true;
    });
    if (isLocked(interaction)) {
      state.tried.power = interaction.name;
    }
    var size = cfg.sizes[state.size];
    if (size && !size.paging && key !== 'power') {
      delete state.tools.paging;
    }
  }

  function chooseGoal(key) {
    var goal = cfg.goals[key],
      defaults = goal.defaults || {};

    state.goal = key;
    state.tried = {};
    state.chart = null;
    state.notice = '';
    state.titleEdited = false;
    state.empty = false;
    if (goal.kind === 'data' || goal.kind === 'own') {
      state.size = defaults.size || 'some';
      applyInteraction(defaults.interaction || 'explore');
      state.palette = defaults.palette || 'paper';
      state.tryPalette = null;
    }
    if (goal.kind === 'data') {
      state.dataset = goal.datasets[0];
      if (cfg.isPro && defaults.chart && cfg.datasets[state.dataset].chart) {
        state.chart = cfg.datasets[state.dataset].chart.type;
      }
    }
    if (goal.kind === 'own') {
      state.source = null;
    }
    if (goal.kind === 'woo') {
      state.palette = 'paper';
      state.woo = { source: 'latest', layout: 'catalog', tools: { ordering: true, paging: true, info: true }, categories: [] };
    }
    if (goal.kind === 'pricing') {
      state.pricing = { set: 'saas', count: 3, extras: { badge: true, hover: true }, template: 'classic', tryTemplate: null };
    }
    updateTitle();
  }

  function updateTitle() {
    if (state.titleEdited) {
      return;
    }
    var kind = goalKind();
    if (kind === 'data' && state.dataset) {
      state.title = cfg.datasets[state.dataset].title;
    } else if (kind === 'woo') {
      state.title = t('Product table');
    } else if (kind === 'pricing') {
      state.title = cfg.pricing.sets[state.pricing.set].title;
    } else if (kind === 'own') {
      state.title = t('My table');
    }
  }

  /* ---- What the table holds ---- */

  function parsePasted(text) {
    var lines = String(text || '').replace(/\r\n?/g, '\n').split('\n');
    while (lines.length && !lines[lines.length - 1].trim()) {
      lines.pop();
    }
    if (!lines.length) {
      return null;
    }
    var first = lines[0],
      delimiter = first.indexOf('\t') !== -1 ? '\t' : first.indexOf(';') !== -1 ? ';' : ',',
      rows = lines.slice(0, 2001).map(function (line) {
        if (delimiter === '\t') {
          return line.split('\t');
        }
        var cells = [],
          cell = '',
          quoted = false;
        for (var i = 0; i < line.length; i++) {
          var character = line[i];
          if (character === '"') {
            if (quoted && line[i + 1] === '"') {
              cell += '"';
              i++;
            } else {
              quoted = !quoted;
            }
          } else if (character === delimiter && !quoted) {
            cells.push(cell);
            cell = '';
          } else {
            cell += character;
          }
        }
        cells.push(cell);
        return cells;
      });

    return rows.map(function (row) {
      return row.map(function (cell) {
        return cell.trim();
      });
    });
  }

  function isNumeric(value) {
    return value !== '' && !isNaN(parseFloat(value)) && isFinite(value);
  }

  function pastedContent() {
    var rows = state.pasted.rows;
    if (!rows || !rows.length) {
      return null;
    }
    var width = 0;
    rows.forEach(function (row) {
      width = Math.max(width, row.length);
    });
    var body = rows.slice(state.pasted.hasHeader ? 1 : 0),
      titles = [],
      columns = [],
      chartTo = 0;

    for (var i = 0; i < width; i++) {
      var title = state.pasted.hasHeader ? rows[0][i] || '' : t('Column') + ' ' + (i + 1),
        numeric = body.length > 0;
      body.forEach(function (row) {
        if ((row[i] || '') !== '' && !isNumeric(row[i])) {
          numeric = false;
        }
      });
      titles.push(title);
      columns.push({ title: title, format: '', align: numeric ? 'right' : 'left', numeric: numeric });
    }
    for (var c = 1; c < Math.min(4, width); c++) {
      if (!columns[c].numeric) {
        break;
      }
      chartTo = c;
    }

    var content = { columns: columns, rows: body };
    if (chartTo > 0) {
      content.chart = { to: chartTo, title: t('My chart'), type: 'ChartjsColumn' };
    }
    return content;
  }

  function blankContent() {
    var columns = [],
      rows = [],
      columnCount = Math.max(1, Math.min(12, parseInt(state.blankCols, 10) || 1)),
      rowCount = Math.max(1, Math.min(8, parseInt(state.blankRows, 10) || 1)),
      i;
    for (i = 1; i <= columnCount; i++) {
      columns.push({ title: t('Column') + ' ' + i, format: '', align: 'left' });
    }
    for (i = 0; i < rowCount; i++) {
      rows.push(columns.map(function () {
        return '';
      }));
    }
    return { columns: columns, rows: rows };
  }

  function getContent(datasetKey) {
    var kind = goalKind();
    if (datasetKey) {
      return cfg.datasets[datasetKey];
    }
    if (kind === 'data' && state.dataset) {
      var dataset = cfg.datasets[state.dataset];
      if (state.empty) {
        return { columns: dataset.columns, rows: dataset.rows.slice(0, 4).map(function (row) {
          return row.map(function () {
            return '';
          });
        }) };
      }
      return dataset;
    }
    if (kind === 'own') {
      if (state.source === 'paste') {
        return pastedContent() || blankContent();
      }
      return blankContent();
    }
    return null;
  }

  function formatCell(value, column) {
    if (value === '' || value === null || value === undefined) {
      return '';
    }
    if (column.format === 'currency') {
      return '$' + Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    return value;
  }

  /* ---- The preview ---- */

  function paletteVars(palette) {
    return [
      '--p-head:' + palette.headerBg, '--p-head-text:' + palette.headerText, '--p-cell:' + palette.cellBg, '--p-text:' + palette.cellText,
      '--p-border:' + palette.border, '--p-stripe:' + palette.stripeBg, '--p-stripe-text:' + palette.stripeText, '--p-hover:' + palette.hoverBg,
      '--p-surface:' + palette.surface, '--p-radius:' + palette.radius + 'px', '--p-button:' + palette.buttonBg, '--p-button-text:' + palette.buttonText,
      '--p-input:' + palette.inputBg, '--p-input-text:' + palette.inputText, '--p-input-border:' + palette.inputBorder,
      '--p-active:' + palette.pageActiveBg, '--p-active-text:' + palette.pageActiveText, '--p-label:' + palette.labelColor,
    ].join(';');
  }

  function activePalette() {
    return cfg.palettes[hover.palette || state.tryPalette || state.palette] || cfg.palettes.paper;
  }

  // A PRO tool shows in the preview only while it is being tried: from the next step on the preview shows the real result
  function previewTools() {
    var tools = {};
    keys(state.tools).forEach(function (tool) {
      if (state.tools[tool] && (!isLocked(cfg.tools[tool]) || state.step === 'visitors')) {
        tools[tool] = true;
      }
    });
    return tools;
  }

  function renderDataTable(content, palette, tools) {
    var device = state.device,
      columns = content.columns,
      visible = device === 'mobile' ? Math.min(2, columns.length) : device === 'tablet' ? Math.min(4, columns.length) : Math.min(7, columns.length),
      collapsed = visible < columns.length,
      limit = tools.paging ? 5 : 8,
      rows = content.rows.slice(0, limit),
      html = '<div class="stbw-demo" style="' + paletteVars(palette) + '">',
      goal = currentGoal() || {},
      defaults = goal.defaults || {};

    if (tools.export) {
      html += '<div class="stbw-demo-export">' + ['CSV', 'Excel', 'PDF', t('Print')].map(function (label) {
        return '<span>' + esc(label) + '</span>';
      }).join('') + (isLocked(cfg.tools.export) ? '<em class="stbw-pro">PRO</em>' : '') + '</div>';
    }
    if (tools.paging || tools.searching) {
      html += '<div class="stbw-demo-top">';
      html += tools.paging ? '<label>' + esc(t('Show')) + ' <span class="stbw-demo-select">10</span></label>' : '<span></span>';
      html += tools.searching ? '<label>' + esc(t('Search:')) + ' <span class="stbw-demo-input"></span></label>' : '';
      html += '</div>';
    }
    html += '<div class="stbw-demo-scroll"><table class="stbw-demo-table' + (tools.compact ? ' is-compact' : '') + (tools.stripe ? ' is-striped' : '') + (tools.hover ? ' is-hover' : '') + '"><thead><tr>';
    columns.slice(0, visible).forEach(function (column, index) {
      var align = defaults.center && index > 0 ? 'center' : column.align;
      html += '<th class="is-' + esc(align) + '">' + esc(column.title) + (tools.ordering ? ' <i class="fa fa-sort" aria-hidden="true"></i>' : '') + '</th>';
    });
    html += '</tr>';
    if (tools.columnSearch) {
      html += '<tr class="stbw-demo-filters">';
      columns.slice(0, visible).forEach(function () {
        html += '<th><span class="stbw-demo-input"></span></th>';
      });
      html += '</tr>';
    }
    html += '</thead><tbody>';
    rows.forEach(function (row) {
      html += '<tr>';
      columns.slice(0, visible).forEach(function (column, index) {
        var align = defaults.center && index > 0 ? 'center' : column.align,
          value = formatCell(row[index], column),
          marker = collapsed && index === 0 ? '<b class="stbw-demo-plus" aria-hidden="true">+</b>' : '';
        html += '<td class="is-' + esc(align) + (defaults.boldFirst && index === 0 ? ' is-bold' : '') + (tools.editable && isLocked(cfg.tools.editable) ? ' is-editable' : '') + '">' + marker + (value === '' ? '<span class="stbw-demo-empty"></span>' : esc(value)) + '</td>';
      });
      html += '</tr>';
    });
    html += '</tbody></table></div>';
    if (tools.paging || tools.info) {
      html += '<div class="stbw-demo-bottom">';
      html += tools.info ? '<span>' + esc(t('Showing 1 to')) + ' ' + rows.length + ' ' + esc(t('of')) + ' ' + content.rows.length + '</span>' : '<span></span>';
      html += tools.paging ? '<span class="stbw-demo-pages"><b>1</b><i>2</i><i>3</i></span>' : '';
      html += '</div>';
    }
    return html + '</div>';
  }

  function chartColors(palette) {
    var colors = [palette.buttonBg, palette.headerBg, '#4ae8ea', '#377dff', '#f59e0b', '#ec4899', '#10b981', '#8b5cf6'],
      unique = [];
    colors.forEach(function (color) {
      var value = String(color).toLowerCase();
      if (unique.indexOf(value) === -1 && value !== '#ffffff' && value !== '#fff' && value !== '#f1f5f9' && value !== '#f8fafc') {
        unique.push(value);
      }
    });
    return unique;
  }

  function chartSeries(content) {
    var to = content.chart.to,
      labels = [],
      series = [],
      i;
    for (i = 1; i <= to; i++) {
      series.push({ name: content.columns[i].title, values: [] });
    }
    content.rows.slice(0, 8).forEach(function (row) {
      labels.push(String(row[0]));
      for (var j = 1; j <= to; j++) {
        series[j - 1].values.push(parseFloat(row[j]) || 0);
      }
    });
    return { labels: labels, series: series };
  }

  // Draws a small chart from the data of the table. It is only a picture for the wizard, the plugin draws the real one.
  function drawChart(kind, content, palette, small) {
    var data = chartSeries(content),
      colors = chartColors(palette),
      width = small ? 148 : 520,
      height = small ? 72 : 210,
      pad = small ? 4 : 26,
      ink = small ? 'transparent' : palette.labelColor || '#64748b',
      grid = palette.border || '#e2e8f0',
      hole = small ? '#f8fafc' : palette.surface || '#fff',
      svg = '<svg viewBox="0 0 ' + width + ' ' + height + '" role="img" aria-hidden="true" preserveAspectRatio="xMidYMid meet">',
      max = 1,
      count = data.labels.length || 1;

    data.series.forEach(function (serie) {
      serie.values.forEach(function (value) {
        max = Math.max(max, value);
      });
    });

    if (kind === 'pie' || kind === 'doughnut') {
      var values = data.series[0].values,
        total = values.reduce(function (sum, value) {
          return sum + value;
        }, 0) || 1,
        radius = height / 2 - (small ? 4 : 14),
        cx = small ? width / 2 : height / 2 + 30,
        cy = height / 2,
        angle = -Math.PI / 2;
      values.forEach(function (value, index) {
        var slice = (value / total) * Math.PI * 2,
          x1 = cx + radius * Math.cos(angle),
          y1 = cy + radius * Math.sin(angle),
          x2 = cx + radius * Math.cos(angle + slice),
          y2 = cy + radius * Math.sin(angle + slice);
        svg += '<path d="M' + cx + ' ' + cy + 'L' + x1.toFixed(2) + ' ' + y1.toFixed(2) + 'A' + radius + ' ' + radius + ' 0 ' + (slice > Math.PI ? 1 : 0) + ' 1 ' + x2.toFixed(2) + ' ' + y2.toFixed(2) + 'Z" fill="' + colors[index % colors.length] + '" stroke="' + esc(hole) + '" stroke-width="1.5"></path>';
        angle += slice;
      });
      if (kind === 'doughnut') {
        svg += '<circle cx="' + cx + '" cy="' + cy + '" r="' + radius * 0.58 + '" fill="' + esc(hole) + '"></circle>';
      }
      if (!small) {
        data.labels.forEach(function (label, index) {
          var y = 22 + index * 22;
          svg += '<rect x="' + (cx + radius + 34) + '" y="' + (y - 9) + '" width="10" height="10" rx="2" fill="' + colors[index % colors.length] + '"></rect><text x="' + (cx + radius + 50) + '" y="' + y + '" font-size="11" fill="' + esc(ink) + '">' + esc(label) + '</text>';
        });
      }
      return svg + '</svg>';
    }

    var left = small ? pad : 40,
      bottom = small ? pad : 22,
      top = small ? pad : 10,
      plotWidth = width - left - pad,
      plotHeight = height - top - bottom;

    if (!small) {
      for (var g = 0; g <= 3; g++) {
        var gy = top + (plotHeight / 3) * g;
        svg += '<line x1="' + left + '" x2="' + (width - pad) + '" y1="' + gy + '" y2="' + gy + '" stroke="' + grid + '" stroke-width="1"></line>';
      }
    }

    if (kind === 'bar') {
      var rowHeight = plotHeight / count,
        barHeight = (rowHeight * 0.72) / data.series.length;
      data.labels.forEach(function (label, index) {
        data.series.forEach(function (serie, s) {
          var w = (serie.values[index] / max) * plotWidth;
          svg += '<rect x="' + left + '" y="' + (top + index * rowHeight + s * barHeight + rowHeight * 0.14).toFixed(2) + '" width="' + w.toFixed(2) + '" height="' + barHeight.toFixed(2) + '" rx="2" fill="' + colors[s % colors.length] + '"></rect>';
        });
      });
    } else if (kind === 'column') {
      var groupWidth = plotWidth / count,
        barWidth = (groupWidth * 0.68) / data.series.length;
      data.labels.forEach(function (label, index) {
        data.series.forEach(function (serie, s) {
          var h = (serie.values[index] / max) * plotHeight;
          svg += '<rect x="' + (left + index * groupWidth + s * barWidth + groupWidth * 0.16).toFixed(2) + '" y="' + (top + plotHeight - h).toFixed(2) + '" width="' + barWidth.toFixed(2) + '" height="' + h.toFixed(2) + '" rx="2" fill="' + colors[s % colors.length] + '"></rect>';
        });
        if (!small) {
          svg += '<text x="' + (left + index * groupWidth + groupWidth / 2).toFixed(2) + '" y="' + (height - 6) + '" font-size="10" text-anchor="middle" fill="' + esc(ink) + '">' + esc(label.slice(0, 9)) + '</text>';
        }
      });
    } else {
      data.series.forEach(function (serie, s) {
        var points = serie.values.map(function (value, index) {
          var x = left + (count > 1 ? (index / (count - 1)) * plotWidth : plotWidth / 2),
            y = top + plotHeight - (value / max) * plotHeight;
          return x.toFixed(2) + ',' + y.toFixed(2);
        });
        if (kind === 'area') {
          svg += '<polygon points="' + left + ',' + (top + plotHeight) + ' ' + points.join(' ') + ' ' + (left + plotWidth) + ',' + (top + plotHeight) + '" fill="' + colors[s % colors.length] + '" opacity="0.28"></polygon>';
        }
        svg += '<polyline points="' + points.join(' ') + '" fill="none" stroke="' + colors[s % colors.length] + '" stroke-width="' + (small ? 1.5 : 2.2) + '" stroke-linejoin="round" stroke-linecap="round"></polyline>';
      });
      if (!small) {
        data.labels.forEach(function (label, index) {
          svg += '<text x="' + (left + (count > 1 ? (index / (count - 1)) * plotWidth : plotWidth / 2)).toFixed(2) + '" y="' + (height - 6) + '" font-size="10" text-anchor="middle" fill="' + esc(ink) + '">' + esc(label.slice(0, 9)) + '</text>';
        });
      }
    }

    return svg + '</svg>';
  }

  function renderChartBlock(content, palette) {
    var chartKey = hover.chart || state.chart;
    if (!chartKey || !content || !content.chart || !cfg.charts[chartKey]) {
      return '';
    }
    if (!cfg.isPro && state.step !== 'charts') {
      return '';
    }
    var draw = cfg.charts[chartKey].draw,
      // A pie shows one column of values
      title = (draw === 'pie' || draw === 'doughnut') && content.chart.to > 1 ? content.columns[1].title : content.chart.title;
    return '<figure class="stbw-demo-chart" style="' + paletteVars(palette) + '"><figcaption>' + esc(title) + proPill({ pro: true }) + '</figcaption>' + drawChart(draw, content, palette, false) + '</figure>';
  }

  function wooColumnCell(id, product, multi) {
    switch (id) {
      case 4:
        return product.image ? '<img src="' + esc(product.image) + '" alt="" />' : '<span class="stbw-demo-thumb"><i class="fa fa-picture-o" aria-hidden="true"></i></span>';
      case 2:
        return '<b>' + esc(product.name) + '</b>';
      case 3:
        return esc(product.sku || '-');
      case 5:
        return esc(product.category);
      case 6:
        return '<b>' + esc(product.price) + '</b>';
      case 8:
        return esc(product.summary || t('A short product summary'));
      case 10:
        return '<span class="stbw-demo-stars">★★★★★</span>';
      case 9:
        return '<span class="stbw-demo-buy"><span class="stbw-demo-qty">1</span><span class="stbw-demo-button">' + esc(t('Add to cart')) + '</span>' + (multi ? '<span class="stbw-demo-tick"></span>' : '') + '</span>';
    }
    return '';
  }

  function wooDemoProducts() {
    if (cfg.woo.products.length) {
      return cfg.woo.products;
    }
    return [
      { name: 'Trail Backpack', sku: 'BP-2041', price: '$89.00', image: '', category: 'Bags', summary: 'Light, roomy and rain ready' },
      { name: 'Canvas Tote', sku: 'TO-1180', price: '$34.00', image: '', category: 'Bags', summary: 'Everyday carry in heavy canvas' },
      { name: 'Steel Bottle', sku: 'BT-3312', price: '$26.00', image: '', category: 'Drinkware', summary: 'Keeps drinks cold for a day' },
      { name: 'Wool Beanie', sku: 'HT-1407', price: '$29.00', image: '', category: 'Apparel', summary: 'Soft merino for cold mornings' },
    ];
  }

  function renderWooTable(palette) {
    var woo = state.woo,
      layout = cfg.woo.layouts[hover.layout || woo.layout],
      columnNames = { 2: t('Name'), 3: t('SKU'), 4: t('Image'), 5: t('Category'), 6: t('Price'), 8: t('Summary'), 9: t('Buy'), 10: t('Reviews') },
      columns = layout.columns.slice(),
      trying = state.step === 'shopping',
      tools = woo.tools,
      show = function (key) {
        return tools[key] && (!isLocked(cfg.woo.tools[key]) || trying);
      },
      device = state.device,
      visible = device === 'mobile' ? 2 : device === 'tablet' ? 4 : columns.length,
      collapsed = visible < columns.length,
      multi = !!(tools.multi || layout.multi),
      html = '<div class="stbw-demo" style="' + paletteVars(palette) + '">';

    if (show('filterCategory') || show('filterPrice') || show('columns')) {
      html += '<div class="stbw-demo-filterbar">';
      if (show('filterCategory')) {
        html += '<span class="stbw-demo-chip"><i class="fa fa-filter" aria-hidden="true"></i> ' + esc(t('Categories')) + proPill(cfg.woo.tools.filterCategory) + '</span>';
      }
      if (show('filterPrice')) {
        html += '<span class="stbw-demo-range"><i></i></span>' + proPill(cfg.woo.tools.filterPrice);
      }
      if (show('columns')) {
        html += '<span class="stbw-demo-chip"><i class="fa fa-columns" aria-hidden="true"></i> ' + esc(t('Columns')) + proPill(cfg.woo.tools.columns) + '</span>';
      }
      html += '</div>';
    }
    if (show('paging') || show('searching') || multi) {
      html += '<div class="stbw-demo-top">';
      html += show('paging') ? '<label>' + esc(t('Show')) + ' <span class="stbw-demo-select">10</span></label>' : '<span></span>';
      html += multi ? '<span class="stbw-demo-button">' + esc(t('Add selected to cart')) + '</span>' : '';
      html += show('searching') ? '<label>' + esc(t('Search:')) + ' <span class="stbw-demo-input"></span>' + proPill(cfg.woo.tools.searching) + '</label>' : '';
      html += '</div>';
    }
    html += '<div class="stbw-demo-scroll"><table class="stbw-demo-table is-striped is-hover"><thead><tr>';
    columns.slice(0, visible).forEach(function (id) {
      html += '<th>' + esc(columnNames[id]) + (show('ordering') ? ' <i class="fa fa-sort" aria-hidden="true"></i>' : '') + '</th>';
    });
    html += '</tr></thead><tbody>';
    wooDemoProducts().slice(0, 4).forEach(function (product) {
      html += '<tr>';
      columns.slice(0, visible).forEach(function (id, index) {
        html += '<td class="is-left">' + (collapsed && index === 0 ? '<b class="stbw-demo-plus" aria-hidden="true">+</b>' : '') + wooColumnCell(id, product, multi) + '</td>';
      });
      html += '</tr>';
    });
    html += '</tbody></table></div>';
    if (show('paging') || show('info')) {
      html += '<div class="stbw-demo-bottom">' + (show('info') ? '<span>' + esc(t('Showing 1 to')) + ' 4 ' + esc(t('of')) + ' ' + Math.max(cfg.woo.count, 4) + '</span>' : '<span></span>') + (show('paging') ? '<span class="stbw-demo-pages"><b>1</b><i>2</i><i>3</i></span>' : '') + '</div>';
    }
    return html + '</div>';
  }

  function renderPricingCards() {
    var pricing = state.pricing,
      template = cfg.pricing.templates[hover.template || pricing.tryTemplate || pricing.template],
      set = cfg.pricing.sets[hover.set || pricing.set],
      plans = set.plans.slice(0, pricing.count),
      dark = isDarkColor(template.surface),
      billing = pricing.extras.billing && (!isLocked(cfg.pricing.extras.billing) || state.step === 'extras'),
      ajax = pricing.extras.ajax && (!isLocked(cfg.pricing.extras.ajax) || state.step === 'extras'),
      html = '<div class="stbw-plans' + (dark ? ' is-dark' : '') + (pricing.extras.hover ? ' is-animated' : '') + '" style="--a:' + esc(template.accent) + ';--s:' + esc(template.surface) + '">';

    if (ajax) {
      html += '<div class="stbw-plans-open"><span>' + esc(t('Show pricing table')) + ' <i class="fa fa-chevron-down" aria-hidden="true"></i></span>' + proPill(cfg.pricing.extras.ajax) + '</div>';
    }
    if (billing) {
      html += '<div class="stbw-plans-switch"><b>' + esc(t('Monthly')) + '</b><span>' + esc(t('Yearly')) + '</span>' + proPill(cfg.pricing.extras.billing) + '</div>';
    }
    html += '<div class="stbw-plans-grid" data-count="' + plans.length + '">';
    plans.forEach(function (plan, index) {
      var featured = index === 1;
      html += '<div class="stbw-plan' + (featured ? ' is-featured' : '') + '">';
      if (featured && pricing.extras.badge) {
        html += '<em>' + esc(t('Most popular')) + '</em>';
      }
      html += '<h4>' + esc(plan.name) + '</h4><p>' + esc(plan.for) + '</p><div class="stbw-plan-price"><strong>$' + esc(Number(plan.price).toLocaleString('en-US')) + '</strong><small>' + esc(set.unit) + '</small></div><ul>';
      plan.features.forEach(function (feature) {
        html += '<li><i class="fa fa-check" aria-hidden="true"></i>' + esc(feature) + '</li>';
      });
      html += '</ul><span class="stbw-plan-button">' + esc(plan.button) + '</span></div>';
    });
    return html + '</div></div>';
  }

  function isDarkColor(hex) {
    var value = String(hex).replace('#', '');
    if (value.length === 3) {
      value = value[0] + value[0] + value[1] + value[1] + value[2] + value[2];
    }
    var number = parseInt(value, 16);
    return ((number >> 16) & 255) * 0.299 + ((number >> 8) & 255) * 0.587 + (number & 255) * 0.114 < 110;
  }

  function renderSkeleton() {
    var cells = '';
    for (var i = 0; i < 24; i++) {
      cells += '<i></i>';
    }
    return '<div class="stbw-skeleton"><div class="stbw-skeleton-grid">' + cells + '</div><p>' + esc(t('Your table appears here as you answer')) + '</p></div>';
  }

  function renderPreviewInner() {
    var hoveredGoal = hover.goal ? cfg.goals[hover.goal] : null;

    if (state.mode !== 'guided') {
      return renderModePreview();
    }
    if (hoveredGoal && hoveredGoal.kind === 'data') {
      var goalPalette = cfg.palettes[hoveredGoal.defaults.palette],
        goalTools = cfg.interactions[hoveredGoal.defaults.interaction].tools;
      return renderDataTable(cfg.datasets[hoveredGoal.datasets[0]], goalPalette, goalTools);
    }
    if (hoveredGoal && hoveredGoal.kind === 'pricing') {
      return renderPricingCards();
    }
    if (hoveredGoal && hoveredGoal.kind === 'woo') {
      return renderWooTable(cfg.palettes.paper);
    }
    if (!state.goal) {
      return renderSkeleton();
    }
    var kind = goalKind();
    if (kind === 'pricing') {
      return renderPricingCards();
    }
    if (kind === 'woo') {
      var wooPalette = cfg.isPro || state.step === 'design' ? activePalette() : cfg.palettes.paper;
      return renderWooTable(wooPalette);
    }
    var content = hover.dataset ? getContent(hover.dataset) : getContent(),
      palette = activePalette();
    return renderDataTable(content, palette, previewTools()) + renderChartBlock(content, palette);
  }

  function renderModePreview() {
    var form = state[state.mode],
      columns = [],
      rows = [],
      i;
    if (form.type === 'pricing') {
      return renderPricingCards();
    }
    if (form.type === 'woocommerce') {
      return renderWooTable(cfg.palettes.paper);
    }
    var columnCount = Math.max(1, Math.min(8, parseInt(form.cols, 10) || 1)),
      rowCount = Math.max(1, Math.min(8, parseInt(form.rows, 10) || 1));
    for (i = 0; i < columnCount; i++) {
      columns.push({ title: String.fromCharCode(65 + i), format: '', align: 'left' });
    }
    for (i = 0; i < rowCount; i++) {
      rows.push(columns.map(function () {
        return '';
      }));
    }
    return renderDataTable({ columns: columns, rows: rows }, cfg.palettes.paper, {});
  }

  function renderStage() {
    if (!$root) {
      return;
    }
    var devices = [['desktop', 'fa-desktop', t('Desktop')], ['tablet', 'fa-tablet', t('Tablet')], ['mobile', 'fa-mobile', t('Mobile')]],
      switcher = devices.map(function (device) {
        return '<button type="button" class="' + (state.device === device[0] ? 'is-active' : '') + '" data-act="device" data-key="' + device[0] + '" aria-pressed="' + (state.device === device[0] ? 'true' : 'false') + '" title="' + esc(device[2]) + '"><i class="fa ' + device[1] + '" aria-hidden="true"></i><span>' + esc(device[2]) + '</span></button>';
      }).join('');
    $root.find('.stbw-stage-head').html('<span class="stbw-live"><i></i>' + esc(t('Live preview')) + '</span><div class="stbw-devices" role="group" aria-label="' + esc(t('Preview size')) + '">' + switcher + '</div>');
    $root.find('.stbw-frame').attr('data-device', state.device).html(renderPreviewInner());
  }

  /* ---- The steps ---- */

  function card(options) {
    var locked = options.locked,
      classes = 'stbw-card' + (options.selected ? ' is-selected' : '') + (locked ? ' is-locked' : '') + (options.off ? ' is-off' : '') + (options.wide ? ' is-wide' : '');
    return '<button type="button" class="' + classes + '" data-act="' + esc(options.act) + '" data-key="' + esc(options.key) + '" aria-pressed="' + (options.selected ? 'true' : 'false') + '"' + (options.hover ? ' data-hover="' + esc(options.hover) + '"' : '') + '>' +
      (options.icon ? '<span class="stbw-card-icon"><i class="fa ' + esc(options.icon) + '" aria-hidden="true"></i></span>' : '') +
      '<span class="stbw-card-copy"><strong>' + esc(options.title) + (locked ? '<span class="stbw-pro">PRO</span>' : '') + (options.badge ? '<span class="stbw-badge">' + esc(options.badge) + '</span>' : '') + '</strong>' +
      (options.text ? '<small>' + esc(options.text) + '</small>' : '') +
      (options.tags ? '<span class="stbw-tags">' + options.tags.map(function (tag) {
        return '<i>' + esc(tag) + '</i>';
      }).join('') + '</span>' : '') +
      '</span><span class="stbw-tick" aria-hidden="true"><i class="fa fa-check"></i></span></button>';
  }

  function heading(title, text) {
    return '<header class="stbw-heading"><h2 tabindex="-1">' + esc(title) + '</h2>' + (text ? '<p>' + esc(text) + '</p>' : '') + '</header>';
  }

  function proNotice() {
    if (!state.notice) {
      return '';
    }
    return '<div class="stbw-tryon" role="status"><i class="fa fa-magic" aria-hidden="true"></i><div><strong>' + esc(state.notice) + '</strong><span>' + esc(t('You are trying this on your own table. It stays a preview in the free version.')) + '</span></div><a href="' + esc(cfg.proUrl) + '" target="_blank" rel="noopener">' + esc(t('See PRO')) + ' <i class="fa fa-arrow-right" aria-hidden="true"></i></a></div>';
  }

  function stepGoal() {
    var html = isWelcome && !state.goal
      ? '<header class="stbw-heading stbw-welcome"><span class="stbw-kicker"><i class="fa fa-hand-peace-o" aria-hidden="true"></i> ' + esc(t('Welcome aboard!')) + '</span><h2 tabindex="-1">' + esc(t('Let\'s build your first table')) + '</h2><p>' + esc(t('Pick what you need. We prepare the data, the design and the settings, and you can change all of it later.')) + '</p></header>'
      : heading(t('What do you want to build?'), t('Pick what you need. We prepare the data, the design and the settings, and you can change all of it later.'));

    html += '<div class="stbw-grid stbw-goals">';
    keys(cfg.goals).forEach(function (key) {
      var goal = cfg.goals[key],
        off = goal.kind === 'woo' && !cfg.woo.active;
      html += card({ act: 'goal', key: key, icon: goal.icon, title: goal.name, text: off ? t('Needs the WooCommerce plugin') : goal.text, tags: off ? null : goal.tags, selected: state.goal === key, off: off, hover: 'goal' });
    });
    html += '</div>';
    if (state.needWoo) {
      html += '<div class="stbw-tryon" role="status"><i class="fa fa-shopping-cart" aria-hidden="true"></i><div><strong>' + esc(t('This table shows your WooCommerce products')) + '</strong><span>' + esc(t('Install WooCommerce first, or choose another kind of table.')) + '</span></div><a href="' + esc(cfg.woo.installUrl) + '" target="_blank" rel="noopener">' + esc(t('Install')) + ' <i class="fa fa-arrow-right" aria-hidden="true"></i></a></div>';
    }
    return html;
  }

  function stepStarter() {
    var goal = currentGoal(),
      html = heading(t('Start with ready data'), t('Real-looking rows show how the table works. Replace them with yours whenever you like.'));

    html += '<div class="stbw-grid stbw-three">';
    goal.datasets.forEach(function (key) {
      var dataset = cfg.datasets[key],
        titles = dataset.columns.slice(0, 4).map(function (column) {
          return column.title;
        });
      html += card({ act: 'dataset', key: key, title: dataset.name, text: dataset.hint, tags: titles, selected: state.dataset === key && !state.empty, hover: 'dataset' });
    });
    html += '</div>';
    html += '<button type="button" class="stbw-link' + (state.empty ? ' is-on' : '') + '" data-act="empty"><i class="fa ' + (state.empty ? 'fa-check-square-o' : 'fa-square-o') + '" aria-hidden="true"></i> ' + esc(t('Keep the columns, but leave the rows empty for my data')) + '</button>';
    return html;
  }

  function stepSource() {
    var html = heading(t('Where is your data?'), t('Bring it in now, or start empty and fill it in the editor.'));
    html += '<div class="stbw-grid stbw-two">';
    keys(cfg.sources).forEach(function (key) {
      var source = cfg.sources[key];
      html += card({ act: 'source', key: key, icon: source.icon, title: source.name, text: source.text, selected: state.source === key, locked: isLocked(source) });
    });
    html += '</div>';
    html += proNotice();
    if (state.source === 'paste') {
      var rows = state.pasted.rows,
        count = rows ? rows.length - (state.pasted.hasHeader ? 1 : 0) : 0;
      html += '<div class="stbw-paste"><label for="stbw-paste-area">' + esc(t('Paste your cells here')) + '</label>' +
        '<textarea id="stbw-paste-area" rows="6" spellcheck="false" placeholder="' + esc(t('Copy cells in Excel or Google Sheets and paste them here. CSV text works too.')) + '">' + esc(state.pasted.text) + '</textarea>' +
        '<div class="stbw-paste-row"><label class="stbw-check"><input type="checkbox" id="stbw-paste-header"' + (state.pasted.hasHeader ? ' checked' : '') + ' /> ' + esc(t('The first row holds the column names')) + '</label><span class="stbw-paste-count" role="status">' + (rows ? esc(count + ' ' + t('rows found')) : '') + '</span></div></div>';
    }
    if (state.source === 'blank') {
      html += '<div class="stbw-size-fields"><label>' + esc(t('Columns')) + '<input type="number" min="1" max="40" id="stbw-blank-cols" value="' + esc(state.blankCols) + '" /></label><label>' + esc(t('Rows')) + '<input type="number" min="1" max="2000" id="stbw-blank-rows" value="' + esc(state.blankRows) + '" /></label></div>';
    }
    return html;
  }

  function stepVisitors() {
    var html = heading(t('What can visitors do?'), t('Choose a starting point, then fine-tune it. The preview follows every change.'));
    html += '<div class="stbw-grid stbw-four">';
    keys(cfg.interactions).forEach(function (key) {
      var interaction = cfg.interactions[key];
      html += card({ act: 'interaction', key: key, icon: interaction.icon, title: interaction.name, text: interaction.text, selected: state.interaction === key, locked: isLocked(interaction), badge: interaction.recommended ? t('Popular') : '' });
    });
    html += '</div>';

    html += '<h3 class="stbw-sub">' + esc(t('Fine-tune')) + '</h3><div class="stbw-toggles">';
    keys(cfg.tools).forEach(function (key) {
      var tool = cfg.tools[key],
        on = !!state.tools[key];
      html += '<button type="button" class="stbw-toggle' + (on ? ' is-on' : '') + '" data-act="tool" data-key="' + esc(key) + '" aria-pressed="' + (on ? 'true' : 'false') + '"' + (tool.note ? ' title="' + esc(tool.note) + '"' : '') + '><i class="fa ' + esc(tool.icon) + '" aria-hidden="true"></i><span>' + esc(tool.name) + '</span>' + proPill(tool) + '<b aria-hidden="true"></b></button>';
    });
    html += '</div>';
    html += proNotice();

    html += '<h3 class="stbw-sub">' + esc(t('How big will the table get?')) + '</h3><div class="stbw-segments" role="group">';
    keys(cfg.sizes).forEach(function (key) {
      var size = cfg.sizes[key];
      html += '<button type="button" class="' + (state.size === key ? 'is-active' : '') + '" data-act="size" data-key="' + esc(key) + '" aria-pressed="' + (state.size === key ? 'true' : 'false') + '"><strong>' + esc(size.name) + '</strong><small>' + esc(size.text) + '</small></button>';
    });
    return html + '</div>';
  }

  function paletteCard(key) {
    var palette = cfg.palettes[key],
      selected = state.palette === key,
      trying = state.tryPalette === key,
      locked = isLocked(palette) || (goalKind() === 'woo' && !cfg.isPro);
    return '<button type="button" class="stbw-swatch' + (selected ? ' is-selected' : '') + (trying ? ' is-trying' : '') + '" data-act="palette" data-key="' + esc(key) + '" data-hover="palette" aria-pressed="' + (selected ? 'true' : 'false') + '" style="' + paletteVars(palette) + '">' +
      '<span class="stbw-swatch-art"><i class="h"></i><i class="r"></i><i class="r s"></i><i class="r"></i><b></b></span><span class="stbw-swatch-name">' + esc(palette.name) + '</span>' + (locked ? '<span class="stbw-lock" aria-label="PRO"><i class="fa fa-lock" aria-hidden="true"></i></span>' : '') + '<span class="stbw-tick" aria-hidden="true"><i class="fa fa-check"></i></span></button>';
  }

  function stepDesign() {
    var free = 0,
      total = 0,
      collections = [],
      filtered = keys(cfg.palettes).filter(function (key) {
        var palette = cfg.palettes[key];
        total++;
        if (!palette.pro) {
          free++;
        }
        if (collections.indexOf(palette.collection) === -1) {
          collections.push(palette.collection);
        }
        return (!state.paletteTone || palette.tone === state.paletteTone) && (!state.paletteCollection || palette.collection === state.paletteCollection);
      }),
      isWoo = goalKind() === 'woo',
      html = heading(t('Pick the look'), isWoo && !cfg.isPro ? t('Custom designs of product tables are part of PRO. Hover a palette to try it on your products.') : t('Move over a palette to try it on your table. One click applies it.'));

    html += '<div class="stbw-filters"><div class="stbw-chips" role="group">';
    [['', t('All')], ['light', t('Light')], ['dark', t('Dark')]].forEach(function (tone) {
      html += '<button type="button" class="' + (state.paletteTone === tone[0] ? 'is-active' : '') + '" data-act="tone" data-key="' + tone[0] + '">' + esc(tone[1]) + '</button>';
    });
    html += '</div><select id="stbw-collection" aria-label="' + esc(t('Collection')) + '"><option value="">' + esc(t('All collections')) + '</option>';
    collections.forEach(function (collection) {
      html += '<option value="' + esc(collection) + '"' + (state.paletteCollection === collection ? ' selected' : '') + '>' + esc(collection) + '</option>';
    });
    html += '</select>';
    if (!cfg.isPro) {
      html += '<span class="stbw-count">' + esc(free + ' ' + t('free') + ' + ' + (total - free) + ' PRO') + '</span>';
    }
    html += '</div><div class="stbw-gallery">';
    filtered.forEach(function (key) {
      html += paletteCard(key);
    });
    html += '</div>';
    return html + proNotice();
  }

  function stepCharts() {
    var content = getContent(),
      palette = activePalette(),
      html = heading(t('Add a chart?'), cfg.isPro ? t('Built from your table, and it follows your edits. You can change it in the Diagrams tab.') : t('See your numbers as a chart. Charts are drawn from the table and follow every edit.'));

    html += '<div class="stbw-grid stbw-charts">';
    keys(cfg.charts).forEach(function (key) {
      var chart = cfg.charts[key];
      html += '<button type="button" class="stbw-chart-card' + (state.chart === key ? ' is-selected' : '') + '" data-act="chart" data-key="' + esc(key) + '" data-hover="chart" aria-pressed="' + (state.chart === key ? 'true' : 'false') + '"><span class="stbw-chart-art">' + drawChart(chart.draw, content, palette, true) + '</span><strong>' + esc(chart.name) + (isLocked({ pro: true }) ? '<span class="stbw-pro">PRO</span>' : '') + '</strong><small>' + esc(chart.text) + '</small><span class="stbw-tick" aria-hidden="true"><i class="fa fa-check"></i></span></button>';
    });
    html += '</div>';
    html += '<button type="button" class="stbw-link' + (!state.chart ? ' is-on' : '') + '" data-act="chart-none"><i class="fa ' + (!state.chart ? 'fa-check-square-o' : 'fa-square-o') + '" aria-hidden="true"></i> ' + esc(t('No chart for now')) + '</button>';
    return html + proNotice();
  }

  function stepProducts() {
    var woo = state.woo,
      html = heading(t('Which products go into the table?'), cfg.woo.count ? cfg.woo.count + ' ' + t('products found in your store.') : t('Your store has no published products yet. You can add them later.'));

    html += '<div class="stbw-grid stbw-two">';
    keys(cfg.woo.sources).forEach(function (key) {
      var source = cfg.woo.sources[key];
      html += card({ act: 'woo-source', key: key, icon: source.icon, title: source.name, text: source.text, selected: woo.source === key, locked: isLocked(source) });
    });
    html += '</div>';
    html += proNotice();
    if (woo.source === 'category' && cfg.isPro && cfg.woo.categories.length) {
      html += '<h3 class="stbw-sub">' + esc(t('Categories')) + '</h3><div class="stbw-chips is-wrap" role="group">';
      cfg.woo.categories.forEach(function (category) {
        var on = woo.categories.indexOf(category.id) !== -1;
        html += '<button type="button" class="' + (on ? 'is-active' : '') + '" data-act="woo-cat" data-key="' + category.id + '" aria-pressed="' + (on ? 'true' : 'false') + '">' + esc(category.name) + ' <small>' + category.count + '</small></button>';
      });
      html += '</div>';
    }
    return html;
  }

  function stepLayout() {
    var html = heading(t('Choose the layout'), t('Columns and buttons are set for you. You can reorder or rename them in the editor.'));
    html += '<div class="stbw-grid stbw-two">';
    keys(cfg.woo.layouts).forEach(function (key) {
      var layout = cfg.woo.layouts[key];
      html += card({ act: 'woo-layout', key: key, icon: layout.icon, title: layout.name, text: layout.text, selected: state.woo.layout === key, hover: 'layout' });
    });
    return html + '</div>';
  }

  function toggleList(items, values, act) {
    var html = '<div class="stbw-toggles">';
    keys(items).forEach(function (key) {
      var item = items[key],
        on = !!values[key];
      html += '<button type="button" class="stbw-toggle' + (on ? ' is-on' : '') + '" data-act="' + esc(act) + '" data-key="' + esc(key) + '" aria-pressed="' + (on ? 'true' : 'false') + '"' + (item.note ? ' title="' + esc(item.note) + '"' : '') + '><i class="fa ' + esc(item.icon) + '" aria-hidden="true"></i><span>' + esc(item.name) + '</span>' + proPill(item) + '<b aria-hidden="true"></b></button>';
    });
    return html + '</div>';
  }

  function stepShopping() {
    return heading(t('How do people shop in it?'), t('Turn on what fits your store. The preview shows each tool at once.')) + toggleList(cfg.woo.tools, state.woo.tools, 'woo-tool') + proNotice();
  }

  function stepPlans() {
    var pricing = state.pricing,
      html = heading(t('What are you selling?'), t('We fill the plans with fitting names, prices and features. Every word stays editable.'));

    html += '<div class="stbw-grid stbw-three">';
    keys(cfg.pricing.sets).forEach(function (key) {
      var set = cfg.pricing.sets[key];
      html += card({ act: 'plan-set', key: key, title: set.name, tags: set.plans.slice(0, 3).map(function (plan) {
        return plan.name;
      }), selected: pricing.set === key, hover: 'set' });
    });
    html += '</div><h3 class="stbw-sub">' + esc(t('How many plans?')) + '</h3><div class="stbw-chips" role="group">';
    [2, 3, 4].forEach(function (count) {
      html += '<button type="button" class="' + (pricing.count === count ? 'is-active' : '') + '" data-act="plan-count" data-key="' + count + '" aria-pressed="' + (pricing.count === count ? 'true' : 'false') + '">' + count + '</button>';
    });
    return html + '</div>';
  }

  function stepExtras() {
    return heading(t('Add a little extra'), t('Small touches that help people choose.')) + toggleList(cfg.pricing.extras, state.pricing.extras, 'extra') + proNotice();
  }

  function stepTheme() {
    var pricing = state.pricing,
      free = 0,
      total = keys(cfg.pricing.templates).length,
      html = heading(t('Choose a theme'), t('Move over a theme to try it on your plans. One click applies it.'));

    keys(cfg.pricing.templates).forEach(function (key) {
      if (!cfg.pricing.templates[key].pro) {
        free++;
      }
    });
    if (!cfg.isPro) {
      html += '<div class="stbw-filters"><span class="stbw-count">' + esc(free + ' ' + t('free') + ' + ' + (total - free) + ' PRO') + '</span></div>';
    }
    html += '<div class="stbw-gallery is-themes">';
    keys(cfg.pricing.templates).forEach(function (key) {
      var template = cfg.pricing.templates[key],
        selected = pricing.template === key,
        trying = pricing.tryTemplate === key;
      html += '<button type="button" class="stbw-theme' + (selected ? ' is-selected' : '') + (trying ? ' is-trying' : '') + '" data-act="template" data-key="' + esc(key) + '" data-hover="template" aria-pressed="' + (selected ? 'true' : 'false') + '" style="--a:' + esc(template.accent) + ';--s:' + esc(template.surface) + '"><span class="stbw-theme-art"><i></i><i class="m"></i><i></i></span><span class="stbw-swatch-name">' + esc(template.name) + '</span>' + (isLocked(template) ? '<span class="stbw-lock" aria-label="PRO"><i class="fa fa-lock" aria-hidden="true"></i></span>' : '') + '<span class="stbw-tick" aria-hidden="true"><i class="fa fa-check"></i></span></button>';
    });
    return html + '</div>' + proNotice();
  }

  function triedList() {
    var list = [];
    keys(state.tried).forEach(function (key) {
      list.push(state.tried[key]);
    });
    return list;
  }

  function summaryItems() {
    var kind = goalKind(),
      items = [],
      goal = currentGoal();

    items.push({ step: 'goal', label: t('Table'), value: goal.name });
    if (kind === 'data') {
      items.push({ step: 'starter', label: t('Data'), value: cfg.datasets[state.dataset].name + (state.empty ? ' · ' + t('empty rows') : '') });
    }
    if (kind === 'own') {
      var content = getContent();
      items.push({ step: 'source', label: t('Data'), value: state.source && cfg.sources[state.source] ? cfg.sources[state.source].name + ' · ' + content.columns.length + ' × ' + (state.source === 'paste' ? content.rows.length : state.blankRows) : t('Empty grid') });
    }
    if (kind === 'data' || kind === 'own') {
      var names = keys(state.tools).filter(function (tool) {
        return state.tools[tool] && !isLocked(cfg.tools[tool]);
      }).map(function (tool) {
        return cfg.tools[tool].name;
      });
      items.push({ step: 'visitors', label: t('Visitors'), value: names.join(', ') || t('Just the table') });
      items.push({ step: 'design', label: t('Design'), value: cfg.palettes[state.palette].name });
      if (cfg.isPro && hasChartStep()) {
        items.push({ step: 'charts', label: t('Chart'), value: state.chart ? cfg.charts[state.chart].name : t('No chart') });
      }
    }
    if (kind === 'woo') {
      var woo = state.woo,
        wooNames = keys(woo.tools).filter(function (tool) {
          return woo.tools[tool] && !isLocked(cfg.woo.tools[tool]);
        }).map(function (tool) {
          return cfg.woo.tools[tool].name;
        });
      items.push({ step: 'products', label: t('Products'), value: cfg.woo.sources[woo.source].name });
      items.push({ step: 'layout', label: t('Layout'), value: cfg.woo.layouts[woo.layout].name });
      items.push({ step: 'shopping', label: t('Shopping tools'), value: wooNames.join(', ') || t('Just the table') });
      if (cfg.isPro) {
        items.push({ step: 'design', label: t('Design'), value: cfg.palettes[state.palette].name });
      }
    }
    if (kind === 'pricing') {
      var pricing = state.pricing;
      items.push({ step: 'plans', label: t('Plans'), value: cfg.pricing.sets[pricing.set].name + ' · ' + pricing.count });
      items.push({ step: 'theme', label: t('Theme'), value: cfg.pricing.templates[pricing.template].name });
    }
    return items;
  }

  function stepFinish() {
    var html = heading(t('Ready when you are'), t('Give your table a name and we build it with everything you chose.'));
    html += '<div class="stbw-field"><label for="stbw-title">' + esc(t('Table name')) + '</label><input type="text" id="stbw-title" maxlength="255" value="' + esc(state.title) + '" /></div>';
    html += '<ul class="stbw-summary">';
    summaryItems().forEach(function (item) {
      html += '<li><span>' + esc(item.label) + '</span><strong>' + esc(item.value) + '</strong><button type="button" data-act="goto" data-key="' + esc(item.step) + '" aria-label="' + esc(t('Change') + ': ' + item.label) + '"><i class="fa fa-pencil" aria-hidden="true"></i></button></li>';
    });
    html += '</ul>';
    var tried = triedList();
    if (tried.length && !cfg.isPro) {
      html += '<aside class="stbw-keep"><div class="stbw-keep-head"><i class="fa fa-magic" aria-hidden="true"></i><strong>' + esc(t('You tried these on your table')) + '</strong></div><ul>';
      tried.slice(0, 5).forEach(function (name) {
        html += '<li><span class="stbw-pro">PRO</span>' + esc(name) + '</li>';
      });
      html += '</ul><p>' + esc(t('The free version creates the table without them. PRO keeps them, along with charts, exports and 100+ designs.')) + '</p><a href="' + esc(cfg.proUrl) + '" target="_blank" rel="noopener">' + esc(t('Keep them with PRO')) + ' <i class="fa fa-arrow-right" aria-hidden="true"></i></a></aside>';
    }
    if (state.error) {
      html += '<p class="stbw-error" role="alert">' + esc(state.error) + '</p>';
    }
    return html;
  }

  var stepRenderers = {
    goal: stepGoal, starter: stepStarter, source: stepSource, visitors: stepVisitors, design: stepDesign, charts: stepCharts,
    products: stepProducts, layout: stepLayout, shopping: stepShopping, plans: stepPlans, extras: stepExtras, theme: stepTheme, finish: stepFinish,
  };

  /* ---- Pick a type and expert ---- */

  function typeCards(form, act) {
    var types = [
      ['data', 'fa-table', t('Data table'), t('Rows and columns in a spreadsheet-like editor.')],
      ['woocommerce', 'fa-shopping-cart', t('WooCommerce products'), cfg.woo.active ? t('A live product table with add to cart.') : t('Needs the WooCommerce plugin')],
      ['pricing', 'fa-credit-card', t('Pricing table'), t('Plan cards in a visual builder.')],
    ];
    return types.map(function (type) {
      return card({ act: act, key: type[0], icon: type[1], title: type[2], text: type[3], selected: form.type === type[0], off: type[0] === 'woocommerce' && !cfg.woo.active });
    }).join('');
  }

  function renderQuick() {
    var form = state.quick,
      html = heading(t('Just pick a type'), t('No questions. You get a new table with standard settings and open it in the editor.'));
    html += '<div class="stbw-grid stbw-three">' + typeCards(form, 'quick-type') + '</div>';
    html += '<div class="stbw-field"><label for="stbw-quick-title">' + esc(t('Table name')) + '</label><input type="text" id="stbw-quick-title" maxlength="255" value="' + esc(form.title) + '" placeholder="' + esc(t('My table')) + '" /></div>';
    if (form.type === 'data') {
      html += '<div class="stbw-size-fields"><label>' + esc(t('Columns')) + '<input type="number" min="1" max="40" id="stbw-quick-cols" value="' + esc(form.cols) + '" /></label><label>' + esc(t('Rows')) + '<input type="number" min="1" max="2000" id="stbw-quick-rows" value="' + esc(form.rows) + '" /></label></div>';
    }
    if (state.error) {
      html += '<p class="stbw-error" role="alert">' + esc(state.error) + '</p>';
    }
    return html;
  }

  function selectField(id, label, value, options) {
    var html = '<label class="stbw-select" for="' + id + '">' + esc(label) + '<select id="' + id + '">';
    options.forEach(function (option) {
      var locked = option[2] && !cfg.isPro;
      html += '<option value="' + esc(option[0]) + '"' + (value === option[0] ? ' selected' : '') + (locked ? ' disabled' : '') + '>' + esc(option[1] + (locked ? ' (PRO)' : '')) + '</option>';
    });
    return html + '</select></label>';
  }

  function renderExpert() {
    var form = state.expert,
      html = heading(t('Expert mode'), t('You know what you want. Set the basics, and every other setting waits for you in the editor.'));
    html += '<div class="stbw-grid stbw-three">' + typeCards(form, 'expert-type') + '</div>';
    html += '<div class="stbw-field"><label for="stbw-expert-title">' + esc(t('Table name')) + '</label><input type="text" id="stbw-expert-title" maxlength="255" value="' + esc(form.title) + '" placeholder="' + esc(t('My table')) + '" /></div>';
    if (form.type === 'data') {
      html += '<div class="stbw-size-fields"><label>' + esc(t('Columns')) + '<input type="number" min="1" max="40" id="stbw-expert-cols" value="' + esc(form.cols) + '" /></label><label>' + esc(t('Rows')) + '<input type="number" min="1" max="2000" id="stbw-expert-rows" value="' + esc(form.rows) + '" /></label></div>';
      html += '<div class="stbw-size-fields">' +
        selectField('stbw-expert-source', t('Data source'), form.source, [['manual', t('Type in the editor')], ['csv_excel', t('CSV or Excel file'), true], ['google_sheets', t('Google Sheets'), true], ['sql_query', t('SQL query'), true]]) +
        selectField('stbw-expert-update', t('Updates'), form.update, [['manual', t('Manual')], ['auto', t('Automatic'), true]]) +
        selectField('stbw-expert-design', t('Design'), form.design, [['default', t('Default')], ['custom', t('Custom')], ['templates', t('Templates'), true]]) +
        '</div>';
    }
    if (state.error) {
      html += '<p class="stbw-error" role="alert">' + esc(state.error) + '</p>';
    }
    return html;
  }

  /* ---- The frame ---- */

  function renderRail() {
    var steps = getSteps(),
      current = stepIndex(),
      html = '';

    if (state.mode !== 'guided') {
      return '<div class="stbw-rail-note"><i class="fa fa-bolt" aria-hidden="true"></i><strong>' + esc(state.mode === 'quick' ? t('Fast start') : t('Full control')) + '</strong><p>' + esc(state.mode === 'quick' ? t('One screen, then straight to the editor.') : t('An empty table, and all settings in the editor.')) + '</p><button type="button" data-act="mode" data-key="guided"><i class="fa fa-magic" aria-hidden="true"></i> ' + esc(t('Back to the guided wizard')) + '</button></div>';
    }
    steps.forEach(function (step, index) {
      var done = index < current,
        active = index === current,
        reachable = !step.idle && (done || state.goal);
      html += '<li class="' + (active ? 'is-active' : done ? 'is-done' : '') + (step.idle ? ' is-idle' : '') + '"><button type="button"' + (reachable && !active ? ' data-act="goto" data-key="' + esc(step.id) + '"' : ' disabled') + (active ? ' aria-current="step"' : '') + '><span class="stbw-step-mark">' + (done ? '<i class="fa fa-check" aria-hidden="true"></i>' : '<i class="fa ' + esc(step.icon) + '" aria-hidden="true"></i>') + '</span><span>' + esc(step.label) + '</span></button></li>';
    });
    return '<ol class="stbw-steps">' + html + '</ol><div class="stbw-rail-progress" aria-hidden="true"><i style="width:' + Math.round(((current + 1) / steps.length) * 100) + '%"></i></div>';
  }

  function renderFooter() {
    var steps = getSteps(),
      index = stepIndex(),
      html = '';

    if (state.mode !== 'guided') {
      return '<span></span><button type="button" class="stbw-btn stbw-btn-primary" data-act="mode-create"><span>' + esc(t('Create and open the editor')) + '</span><i class="fa fa-arrow-right" aria-hidden="true"></i></button>';
    }
    if (state.step === 'goal') {
      html += isWelcome ? '<button type="button" class="stbw-btn stbw-btn-ghost" data-act="close">' + esc(t('Skip for now')) + '</button>' : '<span></span>';
      html += state.goal ? '<button type="button" class="stbw-btn stbw-btn-primary" data-act="next"><span>' + esc(t('Continue')) + '</span><i class="fa fa-arrow-right" aria-hidden="true"></i></button>' : '<span class="stbw-foot-hint">' + esc(t('It takes about a minute')) + '</span>';
      return html;
    }
    html += '<button type="button" class="stbw-btn stbw-btn-ghost" data-act="back"><i class="fa fa-arrow-left" aria-hidden="true"></i><span>' + esc(t('Back')) + '</span></button>';
    html += '<div class="stbw-foot-end">';
    if (state.step === 'finish') {
      html += '<button type="button" class="stbw-btn stbw-btn-create" data-act="create"><i class="fa fa-magic" aria-hidden="true"></i><span>' + esc(t('Create my table')) + '</span></button>';
    } else {
      if (index < steps.length - 2 && canContinue()) {
        html += '<button type="button" class="stbw-btn stbw-btn-ghost" data-act="finish-now">' + esc(t('Use the rest as suggested')) + '</button>';
      }
      html += '<button type="button" class="stbw-btn stbw-btn-primary" data-act="next"' + (canContinue() ? '' : ' disabled') + '><span>' + esc(t('Continue')) + '</span><i class="fa fa-arrow-right" aria-hidden="true"></i></button>';
    }
    return html + '</div>';
  }

  function canContinue() {
    if (state.step === 'source') {
      if (!state.source) {
        return false;
      }
      if (state.source === 'paste') {
        return !!state.pasted.rows;
      }
      return !isLocked(cfg.sources[state.source]);
    }
    return true;
  }

  function renderProgress() {
    var labels = [t('Creating the table'), t('Applying your design'), t('Adding rows and settings')];
    if (goalKind() === 'woo') {
      labels = [t('Creating the table'), t('Connecting your products'), t('Setting up columns')];
    }
    if (goalKind() === 'pricing') {
      labels = [t('Creating the table'), t('Writing your plans'), t('Applying the theme')];
    }
    if (cfg.isPro && state.chart && isDataKind()) {
      labels.push(t('Drawing your chart'));
    }
    var html = '<div class="stbw-making" role="status"><div class="stbw-making-orb" aria-hidden="true"><i class="fa fa-magic"></i></div><h2>' + esc(t('Building your table')) + '</h2><ul>';
    labels.forEach(function (label, index) {
      html += '<li class="' + (index < state.progress ? 'is-done' : index === state.progress ? 'is-now' : '') + '"><i class="fa ' + (index < state.progress ? 'fa-check' : 'fa-circle-o-notch' + (index === state.progress ? ' fa-spin' : '')) + '" aria-hidden="true"></i>' + esc(label) + '</li>';
    });
    return html + '</ul></div>';
  }

  function renderSuccess() {
    var table = state.created,
      confetti = '',
      tried = triedList(),
      chartLine = table.charts && table.charts.length ? '<label for="stbw-code-chart">' + esc(t('Chart shortcode')) + '</label><div class="stbw-code"><input type="text" id="stbw-code-chart" readonly value="' + esc(table.charts[0].shortcode) + '" /><button type="button" data-act="copy" data-key="stbw-code-chart"><i class="fa fa-clone" aria-hidden="true"></i><span>' + esc(t('Copy')) + '</span></button></div>' : '';

    for (var i = 0; i < 26; i++) {
      confetti += '<i style="left:' + ((i * 37) % 100) + '%;animation-delay:' + ((i % 9) * 0.07).toFixed(2) + 's;background:' + ['#4ae8ea', '#377dff', '#7ff8ef', '#f59e0b', '#ec4899'][i % 5] + '"></i>';
    }
    return '<div class="stbw-success"><div class="stbw-confetti" aria-hidden="true">' + confetti + '</div>' +
      '<div class="stbw-success-mark" aria-hidden="true"><i class="fa fa-check"></i></div><h2 tabindex="-1">' + esc(t('Your table is ready!')) + '</h2><p>' + esc(table.title) + '</p>' +
      '<div class="stbw-success-codes"><label for="stbw-code">' + esc(t('Put it on any page with this shortcode')) + '</label><div class="stbw-code"><input type="text" id="stbw-code" readonly value="' + esc(table.shortcode) + '" /><button type="button" data-act="copy" data-key="stbw-code"><i class="fa fa-clone" aria-hidden="true"></i><span>' + esc(t('Copy')) + '</span></button></div>' + chartLine + '</div>' +
      '<div class="stbw-success-actions"><a class="stbw-btn stbw-btn-create" href="' + esc(table.editUrl) + '"><span>' + esc(t('Open the editor')) + '</span><i class="fa fa-arrow-right" aria-hidden="true"></i></a><button type="button" class="stbw-btn stbw-btn-ghost" data-act="draft"><i class="fa fa-file-text-o" aria-hidden="true"></i><span>' + esc(t('See it on a new draft page')) + '</span></button><button type="button" class="stbw-btn stbw-btn-ghost" data-act="another"><i class="fa fa-plus" aria-hidden="true"></i><span>' + esc(t('Create another')) + '</span></button></div>' +
      (state.draftMessage ? '<p class="stbw-draft-note" role="status">' + state.draftMessage + '</p>' : '') +
      (!cfg.isPro ? '<a class="stbw-success-pro" href="' + esc(cfg.proUrl) + '" target="_blank" rel="noopener"><span class="stbw-pro">PRO</span><span>' + esc(tried.length ? t('Bring back what you tried:') + ' ' + tried.slice(0, 2).join(', ') : t('Add charts, exports and 100+ designs to this table')) + '</span><i class="fa fa-arrow-right" aria-hidden="true"></i></a>' : '') +
      '</div>';
  }

  function renderBody() {
    if (state.creating) {
      return renderProgress();
    }
    if (state.created) {
      return renderSuccess();
    }
    if (state.mode === 'quick') {
      return renderQuick();
    }
    if (state.mode === 'expert') {
      return renderExpert();
    }
    return (stepRenderers[state.step] || stepGoal)();
  }

  function render() {
    if (!$root) {
      return;
    }
    var signature = state.mode + ':' + state.step + ':' + (state.creating ? 'c' : '') + (state.created ? 'd' : ''),
      isNewStep = signature !== renderedStep,
      $body = $root.find('.stbw-body'),
      scrollTop = $body.scrollTop(),
      $gallery = $root.find('.stbw-gallery'),
      galleryTop = $gallery.length ? $gallery.scrollTop() : 0,
      focused = document.activeElement,
      focusAct = focused && $.contains($root[0], focused) ? focused.getAttribute('data-act') : null,
      focusKey = focused && focusAct ? focused.getAttribute('data-key') : null;

    $root.toggleClass('is-done', !!state.created || !!state.creating);
    $root.find('.stbw-modes button').each(function () {
      var active = $(this).data('key') === state.mode;
      $(this).toggleClass('is-active', active).attr('aria-pressed', active ? 'true' : 'false');
    });
    $root.find('.stbw-rail-body').html(renderRail());
    $body.html('<div class="stbw-step' + (isNewStep ? ' is-entering' : '') + '">' + renderBody() + '</div>');
    $root.find('.stbw-foot').html(state.creating || state.created ? '' : renderFooter()).toggle(!state.creating && !state.created);
    renderStage();

    if (isNewStep) {
      $body.scrollTop(0);
      renderedStep = signature;
      $body.find('h2').first().trigger('focus');
    } else {
      $body.scrollTop(scrollTop);
      $root.find('.stbw-gallery').scrollTop(galleryTop);
      if (focusAct) {
        $root.find('[data-act="' + focusAct + '"]' + (focusKey ? '[data-key="' + focusKey + '"]' : '')).first().trigger('focus');
      }
    }
  }

  /* ---- Moving through the steps ---- */

  function leaveStep() {
    state.tryPalette = null;
    state.pricing.tryTemplate = null;
    state.notice = '';
    state.error = '';
    hover = {};
    delete state.needWoo;
    if (!cfg.isPro && state.chart) {
      state.chart = null;
    }
  }

  function goTo(step) {
    leaveStep();
    state.step = step;
    render();
  }

  function next() {
    var steps = getSteps(),
      index = stepIndex();
    if (!canContinue()) {
      return;
    }
    if (index < steps.length - 1) {
      goTo(steps[index + 1].id);
    }
  }

  function back() {
    var steps = getSteps(),
      index = stepIndex();
    if (index > 0) {
      goTo(steps[index - 1].id);
    }
  }

  function getAnswers() {
    var answers = {
      goal: state.goal,
      title: state.title,
      palette: state.palette,
    };
    if (isDataKind()) {
      answers.dataset = state.dataset;
      answers.empty = state.empty;
      answers.tools = state.tools;
      answers.size = state.size;
      answers.chart = state.chart;
      answers.source = state.source;
      answers.blankRows = state.blankRows;
      answers.blankCols = state.blankCols;
      if (state.source === 'paste' && state.pasted.rows) {
        answers.pasted = { rows: state.pasted.rows, hasHeader: state.pasted.hasHeader };
      }
    }
    if (goalKind() === 'woo') {
      answers.woo = state.woo;
    }
    if (goalKind() === 'pricing') {
      answers.pricing = state.pricing;
    }
    return answers;
  }

  function create() {
    if (state.creating) {
      return;
    }
    state.error = '';
    state.creating = true;
    state.progress = 0;
    render();

    var finished = false,
      response = null,
      failure = null,
      timer = window.setInterval(function () {
        if (state.progress < 2) {
          state.progress++;
          render();
        } else if (finished) {
          window.clearInterval(timer);
          state.creating = false;
          if (failure !== null) {
            state.error = failure;
          } else {
            state.created = response.table;
            state.created.editUrl = response.table.url + (response.table.openTab ? '&stbtab=' + encodeURIComponent(response.table.openTab) : '');
          }
          render();
        }
      }, 420);

    window.supsystic.Tables
      .request({ module: 'wizard', action: 'create', nonce: window.DTGS_NONCE }, { answers: JSON.stringify(getAnswers()) })
      .done(function (result) {
        response = result;
      })
      .fail(function (message) {
        failure = message || t('The table could not be created. Please try again.');
      })
      .always(function () {
        finished = true;
      });
  }

  function createFromForm() {
    var form = state[state.mode],
      type = form.type;
    if (type === 'woocommerce' && !cfg.woo.active) {
      state.error = t('WooCommerce is not active on this site.');
      render();
      return;
    }
    var cols = type === 'data' ? Math.max(1, Math.min(40, parseInt(form.cols, 10) || 1)) : type === 'pricing' ? 3 : 1,
      rows = type === 'data' ? Math.max(1, Math.min(2000, parseInt(form.rows, 10) || 1)) : type === 'pricing' ? 3 : 1,
      $button = $root.find('[data-act="mode-create"]');

    state.error = '';
    $button.prop('disabled', true).find('span').text(t('Creating…'));
    window.supsystic.Tables
      .request({ module: 'tables', action: 'create', nonce: window.DTGS_NONCE }, {
        title: $.trim(form.title) || t('My table'),
        rows: rows,
        cols: cols,
        table_type: type,
        source_type: type === 'woocommerce' ? 'woocommerce' : form.source || 'manual',
        update_mode: form.update || 'manual',
        design_mode: form.design || 'default',
      })
      .done(function (response) {
        var openTab = form.source === 'google_sheets' || form.source === 'sql_query' ? '&stbtab=source' : form.source === 'csv_excel' && cfg.isPro ? '&stbtab=import' : '';
        window.onbeforeunload = null;
        window.location.href = response.url + '&new=1&cols=' + cols + '&rows=' + rows + openTab;
      })
      .fail(function (message) {
        state.error = message || t('The table could not be created. Please try again.');
        render();
      });
  }

  function copyCode(id) {
    var input = document.getElementById(id);
    if (!input) {
      return;
    }
    input.select();
    var $button = $(input).siblings('button'),
      done = function () {
        $button.addClass('is-copied').find('span').text(t('Copied'));
        $button.find('i').attr('class', 'fa fa-check');
      };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(input.value).then(done, function () {
        document.execCommand('copy');
        done();
      });
    } else {
      document.execCommand('copy');
      done();
    }
  }

  function createDraftPage($button) {
    $button.prop('disabled', true).find('span').text(t('Creating…'));
    window.supsystic.Tables
      .request({ module: 'wizard', action: 'draftPage', nonce: window.DTGS_NONCE }, { id: state.created.id })
      .done(function (response) {
        window.open(response.preview, '_blank', 'noopener');
        state.draftMessage = esc(t('A draft page was created and opened in a new tab.')) + ' <a href="' + esc(response.edit) + '" target="_blank" rel="noopener">' + esc(t('Edit the page')) + '</a>';
        render();
      })
      .fail(function (message) {
        state.draftMessage = esc(message || t('The page could not be created.'));
        render();
      });
  }

  /* ---- Answers ---- */

  var actions = {
    close: close,
    back: back,
    next: next,
    create: create,
    another: function () {
      var keepMode = 'guided';
      state = newState();
      state.mode = keepMode;
      renderedStep = null;
      render();
    },
    'finish-now': function () {
      goTo('finish');
    },
    'mode-create': createFromForm,
    goto: function (key) {
      goTo(key);
    },
    mode: function (key) {
      leaveStep();
      state.mode = key;
      render();
    },
    device: function (key) {
      state.device = key;
      renderStage();
      $root.find('.stbw-devices button[data-key="' + key + '"]').trigger('focus');
    },
    goal: function (key) {
      if (cfg.goals[key].kind === 'woo' && !cfg.woo.active) {
        state.needWoo = true;
        render();
        return;
      }
      chooseGoal(key);
      next();
    },
    dataset: function (key) {
      state.dataset = key;
      state.empty = false;
      state.chart = cfg.isPro && (currentGoal().defaults || {}).chart && cfg.datasets[key].chart ? cfg.datasets[key].chart.type : null;
      updateTitle();
      next();
    },
    empty: function () {
      state.empty = !state.empty;
      if (state.empty) {
        state.chart = null;
      }
      render();
    },
    source: function (key) {
      var source = cfg.sources[key];
      state.source = key;
      state.notice = isLocked(source) ? source.name + ' ' + t('is part of PRO') : '';
      render();
      if (key === 'paste') {
        $('#stbw-paste-area').trigger('focus');
      }
    },
    interaction: function (key) {
      applyInteraction(key);
      state.notice = isLocked(cfg.interactions[key]) ? cfg.interactions[key].name + ' ' + t('is part of PRO') : '';
      render();
    },
    tool: function (key) {
      var tool = cfg.tools[key];
      state.tools[key] = !state.tools[key];
      state.interaction = '';
      state.notice = '';
      if (isLocked(tool)) {
        if (state.tools[key]) {
          state.notice = tool.name + ' ' + t('is part of PRO');
          state.tried['tool-' + key] = tool.name;
        } else {
          delete state.tried['tool-' + key];
        }
      }
      render();
    },
    size: function (key) {
      var size = cfg.sizes[key];
      state.size = key;
      if (size.paging) {
        state.tools.paging = true;
      } else {
        delete state.tools.paging;
      }
      render();
    },
    tone: function (key) {
      state.paletteTone = key;
      render();
    },
    palette: function (key) {
      var palette = cfg.palettes[key],
        locked = isLocked(palette) || (goalKind() === 'woo' && !cfg.isPro);
      if (locked) {
        state.tryPalette = key;
        state.notice = palette.name + ' ' + t('is part of PRO');
        state.tried.palette = palette.name + ' ' + t('design');
      } else {
        state.palette = key;
        state.tryPalette = null;
        state.notice = '';
      }
      render();
    },
    chart: function (key) {
      state.chart = key;
      if (!cfg.isPro) {
        state.notice = cfg.charts[key].name + ' ' + t('chart is part of PRO');
        state.tried.chart = cfg.charts[key].name + ' ' + t('chart');
      }
      render();
    },
    'chart-none': function () {
      state.chart = null;
      state.notice = '';
      render();
    },
    'woo-source': function (key) {
      var source = cfg.woo.sources[key];
      state.notice = '';
      if (isLocked(source)) {
        state.notice = source.name + ' ' + t('is part of PRO');
        state.tried['woo-' + key] = source.name;
        render();
        return;
      }
      state.woo.source = key;
      render();
    },
    'woo-cat': function (key) {
      var id = parseInt(key, 10),
        index = state.woo.categories.indexOf(id);
      if (index === -1) {
        state.woo.categories.push(id);
      } else {
        state.woo.categories.splice(index, 1);
      }
      render();
    },
    'woo-layout': function (key) {
      state.woo.layout = key;
      state.woo.tools.multi = !!cfg.woo.layouts[key].multi;
      next();
    },
    'woo-tool': function (key) {
      var tool = cfg.woo.tools[key];
      state.woo.tools[key] = !state.woo.tools[key];
      state.notice = '';
      if (isLocked(tool)) {
        if (state.woo.tools[key]) {
          state.notice = tool.name + ' ' + t('is part of PRO');
          state.tried['woo-tool-' + key] = tool.name;
        } else {
          delete state.tried['woo-tool-' + key];
        }
      }
      render();
    },
    'plan-set': function (key) {
      state.pricing.set = key;
      updateTitle();
      render();
    },
    'plan-count': function (key) {
      state.pricing.count = parseInt(key, 10);
      render();
    },
    extra: function (key) {
      var extra = cfg.pricing.extras[key];
      state.pricing.extras[key] = !state.pricing.extras[key];
      state.notice = '';
      if (isLocked(extra)) {
        if (state.pricing.extras[key]) {
          state.notice = extra.name + ' ' + t('is part of PRO');
          state.tried['extra-' + key] = extra.name;
        } else {
          delete state.tried['extra-' + key];
        }
      }
      render();
    },
    template: function (key) {
      var template = cfg.pricing.templates[key];
      if (isLocked(template)) {
        state.pricing.tryTemplate = key;
        state.notice = template.name + ' ' + t('is part of PRO');
        state.tried.template = template.name + ' ' + t('theme');
      } else {
        state.pricing.template = key;
        state.pricing.tryTemplate = null;
        state.notice = '';
      }
      render();
    },
    'quick-type': function (key) {
      state.quick.type = key;
      render();
    },
    'expert-type': function (key) {
      state.expert.type = key;
      render();
    },
    copy: copyCode,
  };

  function bindRoot() {
    $root.on('click', '[data-act]', function (event) {
      var act = this.getAttribute('data-act');
      if (this.disabled) {
        return;
      }
      if (act === 'draft') {
        createDraftPage($(this));
        return;
      }
      if (actions[act]) {
        event.preventDefault();
        actions[act](this.getAttribute('data-key'));
      }
    });

    // Moving over a card shows it on the table: it is the fastest way to compare
    var hoverTimer = null;
    $root.on('mouseenter focusin', '[data-hover]', function () {
      var kind = this.getAttribute('data-hover'),
        key = this.getAttribute('data-key');
      window.clearTimeout(hoverTimer);
      hoverTimer = window.setTimeout(function () {
        hover = {};
        hover[kind] = key;
        renderStage();
      }, 60);
    });
    $root.on('mouseleave focusout', '[data-hover]', function () {
      window.clearTimeout(hoverTimer);
      hoverTimer = window.setTimeout(function () {
        hover = {};
        renderStage();
      }, 90);
    });

    $root.on('input', 'input, textarea', function () {
      switch (this.id) {
        case 'stbw-title':
          state.title = this.value;
          state.titleEdited = true;
          return;
        case 'stbw-paste-area':
          state.pasted.text = this.value;
          state.pasted.rows = parsePasted(this.value);
          var rows = state.pasted.rows,
            count = rows ? rows.length - (state.pasted.hasHeader ? 1 : 0) : 0;
          $root.find('.stbw-paste-count').text(rows ? count + ' ' + t('rows found') : '');
          $root.find('.stbw-foot').html(renderFooter());
          renderStage();
          $root.find('.stbw-rail-body').html(renderRail());
          return;
        case 'stbw-blank-cols':
          state.blankCols = this.value;
          renderStage();
          return;
        case 'stbw-blank-rows':
          state.blankRows = this.value;
          renderStage();
          return;
        case 'stbw-quick-title':
          state.quick.title = this.value;
          return;
        case 'stbw-quick-cols':
          state.quick.cols = this.value;
          renderStage();
          return;
        case 'stbw-quick-rows':
          state.quick.rows = this.value;
          renderStage();
          return;
        case 'stbw-expert-title':
          state.expert.title = this.value;
          return;
        case 'stbw-expert-cols':
          state.expert.cols = this.value;
          renderStage();
          return;
        case 'stbw-expert-rows':
          state.expert.rows = this.value;
          renderStage();
          return;
      }
    });

    $root.on('change', 'select, input[type="checkbox"]', function () {
      switch (this.id) {
        case 'stbw-collection':
          state.paletteCollection = this.value;
          render();
          return;
        case 'stbw-paste-header':
          state.pasted.hasHeader = this.checked;
          render();
          return;
        case 'stbw-expert-source':
          state.expert.source = this.value;
          return;
        case 'stbw-expert-update':
          state.expert.update = this.value;
          return;
        case 'stbw-expert-design':
          state.expert.design = this.value;
          return;
      }
    });

    $root.on('keydown', function (event) {
      if (event.key === 'Escape') {
        close();
        return;
      }
      if (event.key === 'Tab') {
        var $controls = $root.find('a[href], button:not([disabled]), input, select, textarea').filter(':visible'),
          first = $controls.get(0),
          last = $controls.get($controls.length - 1);
        if (event.shiftKey && document.activeElement === first) {
          event.preventDefault();
          last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
          event.preventDefault();
          first.focus();
        }
        return;
      }
      if (event.key === 'Enter' && event.target.tagName === 'INPUT' && event.target.type !== 'checkbox') {
        event.preventDefault();
        if (state.mode !== 'guided') {
          createFromForm();
        } else if (state.step === 'finish') {
          create();
        } else {
          next();
        }
      }
    });
  }

  function buildRoot() {
    $root = $(
      '<div class="stbw" id="stb-wizard" role="dialog" aria-modal="true" aria-label="' + esc(t('Add new table')) + '">' +
        '<div class="stbw-shell">' +
          '<aside class="stbw-rail"><div class="stbw-brand"><span class="stbw-logo"><i class="fa fa-table" aria-hidden="true"></i></span><div><strong>' + esc(t('Table Wizard')) + '</strong><small>Data Tables by Supsystic</small></div></div><div class="stbw-rail-body"></div></aside>' +
          '<main class="stbw-main"><header class="stbw-top"><div class="stbw-modes" role="group" aria-label="' + esc(t('How do you want to start?')) + '">' +
            '<button type="button" data-act="mode" data-key="guided"><i class="fa fa-magic" aria-hidden="true"></i><span>' + esc(t('Guided')) + '</span></button>' +
            '<button type="button" data-act="mode" data-key="quick"><i class="fa fa-bolt" aria-hidden="true"></i><span>' + esc(t('Pick a type')) + '</span></button>' +
            '<button type="button" data-act="mode" data-key="expert"><i class="fa fa-sliders" aria-hidden="true"></i><span>' + esc(t('Expert')) + '</span></button>' +
          '</div><button type="button" class="stbw-close" data-act="close" aria-label="' + esc(t('Close')) + '"><i class="fa fa-times" aria-hidden="true"></i></button></header>' +
          '<div class="stbw-body"></div><footer class="stbw-foot"></footer></main>' +
          '<section class="stbw-stage" aria-label="' + esc(t('Live preview')) + '"><div class="stbw-stage-head"></div><div class="stbw-stage-view"><div class="stbw-frame"></div></div></section>' +
        '</div></div>'
    );
    $root.appendTo(document.body);
    bindRoot();
  }

  /* ---- Opening and closing ---- */

  function close() {
    if (!$root) {
      return;
    }
    $root.removeClass('is-open');
    $('html').removeClass('stbw-lock-page');
    if (window.location.hash === '#add' && window.history && window.history.replaceState) {
      window.history.replaceState(null, '', window.location.pathname + window.location.search);
    }
    // After a created table the next opening starts fresh
    if (state && state.created) {
      state = newState();
      renderedStep = null;
    }
    if (previousFocus && previousFocus.focus) {
      previousFocus.focus();
    }
  }

  function open() {
    if (typeof window.editor !== 'undefined' && window.editor && window.editor.deselectCell) {
      window.editor.deselectCell();
    }
    previousFocus = document.activeElement;
    $('html').addClass('stbw-lock-page');
    if ($root) {
      $root.addClass('is-open');
      render();
      return;
    }
    if (loading) {
      return;
    }
    loading = true;
    var $loader = $('<div class="stbw-loader" role="status"><i class="fa fa-circle-o-notch fa-spin" aria-hidden="true"></i></div>').appendTo(document.body);
    window.supsystic.Tables
      .request({ module: 'wizard', action: 'config', nonce: window.DTGS_NONCE }, {})
      .done(function (response) {
        cfg = response.config;
        state = newState();
        buildRoot();
        $root.addClass('is-open');
        render();
      })
      .fail(function (message) {
        $('html').removeClass('stbw-lock-page');
        window.alert(message || 'The wizard could not be opened. Please reload the page.');
      })
      .always(function () {
        loading = false;
        $loader.remove();
      });
  }

  // A table opened from the wizard goes straight to the place where its source is set up
  function openRequestedTab() {
    var match = /[?&]stbtab=(source|import)\b/.exec(window.location.search);
    if (!match) {
      return;
    }
    window.setTimeout(function () {
      if (match[1] === 'source') {
        $('[href="#row-tab-source"]').first().trigger('click');
      } else {
        $('#import').first().trigger('click');
      }
    }, 700);
  }

  $(function () {
    $('a[href="admin.php?page=supsystic-tables#add"]').attr('href', '#add');
    $(document).on('click', '.create-table', function (event) {
      event.preventDefault();
      open();
    });
    $(window)
      .on('hashchange', function () {
        if (window.location.hash === '#add') {
          open();
        }
      })
      .trigger('hashchange');

    // The first visit to the welcome page opens the wizard by itself
    if (isWelcome && window.location.hash !== '#add') {
      open();
    }
    openRequestedTab();
  });
})(window.jQuery, window, document);
