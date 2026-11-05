(function ($) {
  'use strict';
  $(function () {
    var demo = $('.dtgs-classic-demo');
    if (!demo.length) return;
    var original = [
      ['Atlas CRM', 'Product', 'Active', 24800, 24.6, '2026-11-03'],
      ['Brand system', 'Design', 'Review', 18250, 18.2, '2026-11-02'],
      ['Launch campaign', 'Growth', 'Active', 15400, 32.8, '2026-11-03'],
      ['Customer portal', 'Product', 'Active', 12750, 12.4, '2026-11-01'],
      ['Studio website', 'Design', 'Review', 9600, -3.2, '2026-10-29'],
      ['Market research', 'Growth', 'Planned', 7250, 8.5, '2026-10-28'],
      ['Mobile workspace', 'Product', 'Planned', 5800, 16.1, '2026-10-27'],
      ['Autumn collection', 'Design', 'Active', 4200, -1.8, '2026-10-26']
    ];
    var keys = ['name', 'team', 'status', 'revenue', 'growth', 'date'];
    var rows, page = 0, sort = 'revenue', direction = -1, noticeTimer;
    var money = function (value) { return '$' + Number(value).toLocaleString('en-US', { maximumFractionDigits: 0 }); };
    var escape = function (value) { return String(value).replace(/[&<>"']/g, function (char) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]; }); };
    function seed() { rows = original.map(function (row, i) { var item = { id: i }; keys.forEach(function (key, n) { item[key] = row[n]; }); return item; }); }
    function notice(message) {
      clearTimeout(noticeTimer);
      demo.find('.dtgs-demo-notice').text(message).prop('hidden', false);
      noticeTimer = setTimeout(function () { demo.find('.dtgs-demo-notice').prop('hidden', true); }, 4500);
    }
    function filteredRows() {
      var filters = {};
      demo.find('[data-demo-filter]').each(function () { filters[$(this).data('demo-filter')] = String($(this).val() || '').toLowerCase(); });
      return rows.filter(function (row) {
        var text = keys.map(function (key) { return row[key]; }).join(' ').toLowerCase();
        return (!filters.page || text.indexOf(filters.page) !== -1) && (!filters.table || text.indexOf(filters.table) !== -1) &&
          (!filters.name || row.name.toLowerCase().indexOf(filters.name) !== -1) && (!filters.team || row.team.toLowerCase() === filters.team) &&
          (!filters.status || row.status.toLowerCase() === filters.status) && (!filters.revenue || row.revenue >= Number(filters.revenue)) &&
          (!filters.growth || row.growth >= Number(filters.growth)) && (!filters.date || row.date >= filters.date);
      }).sort(function (a, b) { return (typeof a[sort] === 'number' ? a[sort] - b[sort] : a[sort].localeCompare(b[sort])) * direction; });
    }
    function charts(items) {
      var totals = {}, statuses = { Active: 0, Review: 0, Planned: 0 };
      items.forEach(function (row) { totals[row.team] = (totals[row.team] || 0) + row.revenue; statuses[row.status]++; });
      var max = Math.max.apply(null, Object.keys(totals).map(function (key) { return totals[key]; }).concat([1]));
      var bars = demo.find('.dtgs-demo-bars').empty();
      ['Design', 'Product', 'Growth'].forEach(function (team) {
        var total = totals[team] || 0;
        $('<div><b></b><span><i></i></span><small></small></div>').find('b').text(team).end().find('i').css('width', Math.max(0, total / max * 100) + '%').end().find('small').text(money(total)).end().appendTo(bars);
      });
      var count = items.length, active = count ? statuses.Active / count * 100 : 0, review = count ? statuses.Review / count * 100 : 0;
      demo.find('.dtgs-demo-donut').css('background', count ? 'conic-gradient(var(--demo-accent) 0 ' + active + '%, #a78bfa ' + active + '% ' + (active + review) + '%, #cbd5e1 ' + (active + review) + '% 100%)' : 'var(--demo-line)').find('strong').text(count);
      var legend = demo.find('.dtgs-demo-legend').empty();
      Object.keys(statuses).forEach(function (status, i) { $('<span><i></i><b></b><small></small></span>').find('i').css('background', ['var(--demo-accent)', '#a78bfa', '#cbd5e1'][i]).end().find('b').text(status).end().find('small').text(statuses[status]).end().appendTo(legend); });
      demo.find('.dtgs-demo-bars').attr('aria-label', 'Revenue: ' + Object.keys(totals).map(function (team) { return team + ' ' + money(totals[team]); }).join(', '));
      demo.find('.dtgs-demo-donut').attr('aria-label', Object.keys(statuses).map(function (status) { return status + ': ' + statuses[status]; }).join(', '));
    }
    function render() {
      var items = filteredRows(), length = Number(demo.find('[data-demo-length]').val()), pages = length < 0 ? 1 : Math.max(1, Math.ceil(items.length / length));
      page = Math.min(page, pages - 1);
      var start = length < 0 ? 0 : page * length, visible = length < 0 ? items : items.slice(start, start + length), body = demo.find('tbody').empty();
      visible.forEach(function (row) {
        var html = '<tr><td><label class="dtgs-demo-name"><input type="text" data-demo-edit="name" data-row-id="' + row.id + '" value="' + escape(row.name) + '" aria-label="Edit project ' + escape(row.name) + '" /><i class="fa fa-pencil" aria-hidden="true"></i></label></td><td>' + escape(row.team) + '</td><td><select class="dtgs-demo-status" data-demo-edit="status" data-row-id="' + row.id + '" aria-label="Edit status of ' + escape(row.name) + '">';
        ['Active', 'Review', 'Planned'].forEach(function (status) { html += '<option' + (row.status === status ? ' selected' : '') + '>' + status + '</option>'; });
        html += '</select></td><td class="dtgs-demo-revenue">' + money(row.revenue) + '</td><td><span class="dtgs-demo-growth' + (row.growth < 0 ? ' is-down' : '') + '">' + (row.growth > 0 ? '+' : '') + row.growth.toFixed(1) + '%</span></td><td><time datetime="' + row.date + '">' + new Date(row.date + 'T12:00:00').toLocaleDateString('en-US', { month: 'short', day: '2-digit' }) + '</time><i class="fa fa-calendar-o dtgs-demo-date-icon" aria-hidden="true"></i></td></tr>';
        body.append(html);
      });
      if (!items.length) body.append('<tr><td colspan="6" class="dtgs-demo-empty">No matching projects. Try another search.</td></tr>');
      demo.find('[data-demo-column]').attr('aria-sort', 'none').filter('[data-demo-column="' + sort + '"]').attr('aria-sort', direction === 1 ? 'ascending' : 'descending');
      demo.find('[data-demo-total]').text(money(items.reduce(function (sum, row) { return sum + row.revenue; }, 0)));
      demo.find('[data-demo-info]').text(items.length ? (start + 1) + '–' + (start + visible.length) + ' of ' + items.length + (items.length === 1 ? ' project' : ' projects') : '0 projects');
      var nav = demo.find('[data-demo-pages]').empty();
      $('<button type="button" data-demo-page="prev" aria-label="Previous demo page">‹</button>').prop('disabled', page === 0).appendTo(nav);
      for (var i = 0; i < pages; i++) $('<button type="button">').attr({ 'data-demo-page': i, 'aria-label': 'Demo page ' + (i + 1), 'aria-current': i === page ? 'page' : null }).text(i + 1).appendTo(nav);
      $('<button type="button" data-demo-page="next" aria-label="Next demo page">›</button>').prop('disabled', page === pages - 1).appendTo(nav);
      charts(items);
    }
    demo.on('input change', '[data-demo-filter]', function () { page = 0; render(); });
    demo.on('change', '[data-demo-length]', function () { page = 0; render(); });
    demo.on('click', '[data-demo-sort]', function () { var key = $(this).data('demo-sort'); direction = sort === key ? -direction : 1; sort = key; render(); });
    demo.on('click', '[data-demo-page]', function () { var target = $(this).data('demo-page'); page = target === 'next' ? page + 1 : target === 'prev' ? page - 1 : Number(target); render(); });
    demo.on('change', '[data-demo-edit]', function () {
      var field = $(this), row = rows.filter(function (item) { return item.id === Number(field.data('row-id')); })[0];
      if (row) { row[field.data('demo-edit')] = $.trim(field.val()) || row[field.data('demo-edit')]; render(); notice('Sample updated. Filters, totals and charts use the same data.'); }
    });
    demo.on('keydown', '[data-demo-edit="name"]', function (event) { if (event.key === 'Enter') { event.preventDefault(); $(this).trigger('change'); } });
    demo.on('click', '[data-demo-palette]', function () { demo.attr('data-demo-theme', $(this).data('demo-palette')); demo.find('[data-demo-palette]').attr('aria-pressed', 'false'); $(this).attr('aria-pressed', 'true'); });
    demo.on('click', '[data-demo-action]', function () {
      var action = $(this).data('demo-action');
      if (action === 'reset') { seed(); page = 0; sort = 'revenue'; direction = -1; demo.find('[data-demo-filter]').val(''); demo.find('[data-demo-length]').val('5'); render(); notice('Preview reset.'); }
      else if (action === 'import') { seed(); page = 0; demo.find('[data-demo-filter]').val(''); render(); notice('Sample imported: 8 projects. PRO supports CSV, Excel, JSON, Google Sheets and SQL sources.'); }
      else if (action === 'add') { rows.push({ id: Date.now(), name: 'New project', team: 'Product', status: 'Planned', revenue: 3000, growth: 0, date: '2026-11-03' }); page = 0; sort = 'date'; direction = -1; demo.find('[data-demo-filter]').val(''); render(); notice('Sample row added. Edit its name or status directly in the table.'); }
    });
    demo.on('click', '[data-demo-export]', function () {
      var format = $(this).data('demo-export');
      demo.find('.dtgs-demo-export').prop('open', false);
      if (format !== 'CSV') { notice(format + ' export is available in the full table. Try CSV here to download this sample.'); return; }
      var csv = [keys].concat(filteredRows().map(function (row) { return keys.map(function (key) { return row[key]; }); })).map(function (row) { return row.map(function (value) { if (typeof value === 'string' && /^[=+@\-\t\r]/.test(value)) value = "'" + value; return '"' + String(value).replace(/"/g, '""') + '"'; }).join(','); }).join('\r\n');
      var url = URL.createObjectURL(new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' })), link = document.createElement('a');
      link.href = url; link.download = 'classic-table-preview.csv'; link.click(); setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
      notice('CSV sample exported with the current filters and sorting.');
    });
    seed(); render();
  });
})(jQuery);
