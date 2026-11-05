<?php

/**
 * Reads the tables of Ninja Tables straight from the database, so it works also when that plugin
 * is switched off or removed. Nothing here writes to its data.
 *
 * Everything is read defensively: the PRO options of Ninja Tables are stored by a closed plugin,
 * so an unknown or broken value means "this option is not there", never an error.
 */
class SupsysticTables_Importcompetitor_Model_NinjaTables extends SupsysticTables_Core_BaseModel
{
  const POST_TYPE = 'ninja-table';
  const KEY = 'ninja-tables';

  /** @var array Rows of remote sources and drag and drop tables, for the time of one request. */
  private $cache = [];

  public function getKey()
  {
    return self::KEY;
  }

  public function getName()
  {
    return 'Ninja Tables';
  }

  public function getIds()
  {
    $ids = $this->db->get_col($this->db->prepare(
      "SELECT ID FROM {$this->db->posts} WHERE post_type = %s AND post_status NOT IN ('trash', 'auto-draft') ORDER BY ID ASC",
      self::POST_TYPE
    ));

    return array_map('intval', (array) $ids);
  }

  /**
   * @return int[] The tables worth offering: a table without rows to copy would be offered forever.
   */
  public function getImportableIds()
  {
    $ids = [];
    foreach ($this->getIds() as $id) {
      $provider = (string) get_post_meta($id, '_ninja_tables_data_provider', true);
      if ($this->getUnsupportedReason($provider === '' ? 'default' : $provider) === '') {
        $ids[] = $id;
      }
    }

    return $ids;
  }

  public function exists($id)
  {
    $post = get_post((int) $id);

    return $post && $post->post_type === self::POST_TYPE && $post->post_status !== 'trash';
  }

  /**
   * @return array All that is known about a table, in a form that does not depend on Ninja Tables.
   */
  public function load($id)
  {
    $id = (int) $id;
    if (!$this->exists($id)) {
      throw new InvalidArgumentException($this->environment->translate('This table no longer exists.'));
    }
    $post = get_post($id);
    $settings = $this->getArrayMeta($id, '_ninja_table_settings');
    $provider = (string) get_post_meta($id, '_ninja_tables_data_provider', true);
    $provider = $provider === '' ? 'default' : $provider;

    $table = [
      'id' => $id,
      'source' => self::KEY,
      'title' => (string) $post->post_title,
      'description' => (string) $post->post_content,
      'provider' => $provider,
      'engine' => isset($settings['library']) && $settings['library'] === 'datatables' ? 'datatables' : 'footable',
      'settings' => $settings,
      'columns' => [],
      'merged' => [],
      'supported' => true,
      'reason' => '',
      'filters' => [],
      'filterLayout' => $this->getArrayMeta($id, '_ninja_custom_filter_styling'),
      'buttons' => $this->getArrayMeta($id, '_ninja_custom_table_buttons'),
      'frontEdit' => $this->getArrayMeta($id, '_ninja_table_frontedit_settings'),
      'customCss' => trim((string) get_post_meta($id, '_ninja_tables_custom_css', true)),
      'customJs' => trim((string) get_post_meta($id, '_ninja_tables_custom_js', true)),
      'remoteUrl' => '',
    ];

    $reason = $this->getUnsupportedReason($provider);
    if ($reason !== '') {
      $table['supported'] = false;
      $table['reason'] = $reason;
    }
    if ($provider === 'drag_and_drop') {
      $grid = $this->getGrid($id);
      $table['columns'] = $grid['columns'];
      $table['merged'] = $grid['merged'];
    } else {
      $table['columns'] = $this->getColumns($id);
    }
    if ($provider === 'csv' || $provider === 'google-csv') {
      $table['remoteUrl'] = esc_url_raw((string) get_post_meta($id, '_ninja_tables_data_provider_url', true));
    }
    $table['filters'] = $this->getFilters($id, $table['columns']);

    return $table;
  }

  /**
   * Sources that build their rows on the fly have nothing stored that could be copied.
   */
  private function getUnsupportedReason($provider)
  {
    $translate = [$this->environment, 'translate'];
    switch ($provider) {
      case 'default':
      case 'drag_and_drop':
      case 'csv':
      case 'google-csv':
        return '';
      case 'wp_woo':
        return call_user_func($translate, 'Built from your WooCommerce products. Create a WooCommerce table in the wizard instead.');
      case 'wp-posts':
        return call_user_func($translate, 'Built from your posts on the fly, so there are no rows to copy yet.');
      case 'fluent-form':
        return call_user_func($translate, 'Built from form entries on the fly, so there are no rows to copy yet.');
      case 'wp_fct':
        return call_user_func($translate, 'Built from your FluentCart products on the fly, so there are no rows to copy yet.');
      case 'raw-sql':
        return call_user_func($translate, 'Built from an SQL query on the fly. Create a Database table on the Source tab in PRO instead.');
      default:
        return call_user_func($translate, 'This kind of table is not supported yet.');
    }
  }

  private function getArrayMeta($id, $key)
  {
    $value = get_post_meta($id, $key, true);

    return is_array($value) ? $value : [];
  }

  /**
   * Hidden columns go to the end: a visitor sees the same order, and settings kept by the number
   * of a printed column, such as the first sorting, keep pointing at the right column.
   */
  private function getColumns($id)
  {
    $shown = [];
    $hidden = [];
    foreach ($this->getArrayMeta($id, '_ninja_table_columns') as $raw) {
      if (!is_array($raw) || !isset($raw['key']) || $raw['key'] === '') {
        continue;
      }
      $column = $this->normalizeColumn($raw);
      if ($column['hidden']) {
        $hidden[] = $column;
      } else {
        $shown[] = $column;
      }
    }

    return array_merge($shown, $hidden);
  }

  private function normalizeColumn(array $raw)
  {
    $title = isset($raw['name']) ? (string) $raw['name'] : (string) $raw['key'];
    if (isset($raw['enable_html_content']) && ($raw['enable_html_content'] === true || $raw['enable_html_content'] === 'true') && !empty($raw['header_html_content'])) {
      $title = (string) $raw['header_html_content'];
    }
    $breakpoints = isset($raw['breakpoints']) && is_string($raw['breakpoints']) ? trim($raw['breakpoints']) : '';
    $hiddenOn = [];
    if ($breakpoints !== '' && $breakpoints !== 'hidden') {
      foreach (preg_split('/\s+/', $breakpoints) as $point) {
        if ($point === 'xs') {
          $hiddenOn['mobile'] = true;
        } elseif ($point === 'sm' || $point === 'md') {
          $hiddenOn['tablet'] = true;
        } elseif (in_array($point, ['lg', 'xl', 'xxl'], true)) {
          $hiddenOn['desktop'] = true;
        }
      }
    }
    $dateFormat = isset($raw['dateFormat']) && is_string($raw['dateFormat']) && $raw['dateFormat'] !== '' ? $raw['dateFormat'] : 'MM/DD/YYYY';
    if (!empty($raw['showTime']) && $raw['showTime'] === 'yes' && !empty($raw['timeFormat']) && is_string($raw['timeFormat'])) {
      $dateFormat .= ' ' . $raw['timeFormat'];
    }
    $align = '';
    foreach (['contentAlign', 'textAlign'] as $key) {
      if ($align === '' && isset($raw[$key]) && in_array($raw[$key], ['left', 'center', 'right'], true)) {
        $align = $raw[$key];
      }
    }

    return [
      'key' => (string) $raw['key'],
      'title' => $title,
      'type' => isset($raw['data_type']) && is_string($raw['data_type']) ? $raw['data_type'] : 'text',
      'align' => $align,
      'width' => isset($raw['width']) && is_numeric($raw['width']) && (!isset($raw['maxWidthUnit']) || $raw['maxWidthUnit'] === 'px') ? (int) $raw['width'] : 0,
      'hidden' => $breakpoints === 'hidden',
      'hiddenOn' => array_keys($hiddenOn),
      'sortable' => !(isset($raw['unsortable']) && $raw['unsortable'] === 'yes'),
      'classes' => isset($raw['classes']) && is_string($raw['classes']) ? $raw['classes'] : '',
      'dateFormat' => $dateFormat,
      'thousand' => isset($raw['thousandSeparator']) && is_string($raw['thousandSeparator']) ? $raw['thousandSeparator'] : ',',
      'decimal' => isset($raw['decimalSeparator']) && is_string($raw['decimalSeparator']) ? $raw['decimalSeparator'] : '.',
      'transform' => isset($raw['transformed_value']) && is_string($raw['transformed_value']) ? trim($raw['transformed_value']) : '',
      'conditions' => isset($raw['conditions']) && is_array($raw['conditions']) ? $raw['conditions'] : [],
      'buttonText' => isset($raw['button_text']) && is_string($raw['button_text']) ? $raw['button_text'] : '',
      'linkTarget' => isset($raw['link_target']) && is_string($raw['link_target']) ? $raw['link_target'] : '',
    ];
  }

  /**
   * @return array Filters in the order of the source: each with its type and the columns it searches, by number.
   */
  private function getFilters($id, array $columns)
  {
    $numbers = [];
    foreach ($columns as $index => $column) {
      $numbers[$column['key']] = $index;
    }
    $filters = [];
    foreach ($this->getArrayMeta($id, '_ninja_table_custom_filters') as $raw) {
      if (!is_array($raw) || empty($raw['type']) || !is_string($raw['type'])) {
        continue;
      }
      $targets = [];
      $names = isset($raw['columns']) && is_array($raw['columns']) ? $raw['columns'] : [];
      if (isset($raw['select_value_type']) && $raw['select_value_type'] === 'dynamic_data' && !empty($raw['dynamic_select_column'])) {
        $names = [$raw['dynamic_select_column']];
      }
      foreach ($names as $name) {
        if (is_string($name) && isset($numbers[$name])) {
          $targets[] = $numbers[$name];
        }
      }
      $options = [];
      foreach (isset($raw['options']) && is_array($raw['options']) ? $raw['options'] : [] as $option) {
        if (is_array($option) && isset($option['value']) && is_scalar($option['value']) && (string) $option['value'] !== '') {
          $options[] = ['value' => (string) $option['value'], 'label' => isset($option['label']) && is_scalar($option['label']) ? (string) $option['label'] : (string) $option['value']];
        }
      }
      $filters[] = [
        'type' => $raw['type'],
        'title' => isset($raw['title']) && is_scalar($raw['title']) ? (string) $raw['title'] : '',
        'targets' => $targets,
        'placeholder' => isset($raw['placeholder']) && is_scalar($raw['placeholder']) ? (string) $raw['placeholder'] : '',
        'dynamic' => isset($raw['select_value_type']) && $raw['select_value_type'] === 'dynamic_data',
        'multiple' => isset($raw['is_multi_select']) && $raw['is_multi_select'] === 'yes',
        'strict' => isset($raw['strict']) && $raw['strict'] === 'yes',
        'separator' => !empty($raw['parse_comma_separated']) && $raw['parse_comma_separated'] !== 'no' ? ',' : '',
        'sort' => isset($raw['sorting_type']) && $raw['sorting_type'] === 'desc' ? 'desc' : 'asc',
        'options' => $options,
      ];
    }

    return $filters;
  }

  /**
   * @return int The rows of the body, without the row of titles.
   */
  public function countRows(array $table)
  {
    switch ($table['provider']) {
      case 'drag_and_drop':
        return max(0, count($this->getGrid($table['id'])['rows']) - 1);
      case 'csv':
      case 'google-csv':
        return count($this->getRemoteRows($table));
    }
    $dynamic = $this->getDynamicTable($table);
    if ($dynamic !== '') {
      return (int) $this->db->get_var("SELECT COUNT(*) FROM `{$dynamic}`"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
    $items = $this->getItemsTable();

    return $items === '' ? 0 : (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM `{$items}` WHERE table_id = %d", $table['id'])); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  }

  /**
   * @return array Rows of the body. Each row holds its values in the order of the columns.
   */
  public function getRows(array $table, $offset, $limit)
  {
    $offset = max(0, (int) $offset);
    $limit = max(1, (int) $limit);
    switch ($table['provider']) {
      case 'drag_and_drop':
        return array_slice($this->getGrid($table['id'])['rows'], $offset + 1, $limit);
      case 'csv':
      case 'google-csv':
        return array_slice($this->getRemoteRows($table), $offset, $limit);
    }
    $dynamic = $this->getDynamicTable($table);
    $rows = $dynamic !== '' ? $this->getDynamicRows($dynamic, $offset, $limit) : $this->getItemRows($table, $offset, $limit);
    $result = [];
    foreach ($rows as $values) {
      $row = [];
      foreach ($table['columns'] as $column) {
        $row[] = $this->getCellHtml($column, $values, $table['columns']);
      }
      $result[] = $row;
    }

    return $result;
  }

  /**
   * @return string[] What the titles of a drag and drop table say. Its first row is its header.
   */
  public function getHeaderRow(array $table)
  {
    if ($table['provider'] === 'drag_and_drop') {
      $rows = $this->getGrid($table['id'])['rows'];

      return isset($rows[0]) ? $rows[0] : [];
    }
    $titles = [];
    foreach ($table['columns'] as $column) {
      $titles[] = $column['title'];
    }

    return $titles;
  }

  private function getItemsTable()
  {
    $name = $this->db->prefix . 'ninja_table_items';

    return $this->tableExists($name) ? $name : '';
  }

  private function tableExists($name)
  {
    return $this->db->get_var($this->db->prepare('SHOW TABLES LIKE %s', $this->db->esc_like($name))) === $name;
  }

  /**
   * The DataTables engine keeps the rows of each table in a database table of its own.
   */
  private function getDynamicTable(array $table)
  {
    if ($table['engine'] !== 'datatables') {
      return '';
    }
    $name = $this->db->prefix . 'ninja_tables_dt_' . (int) $table['id'];

    return $this->tableExists($name) ? $name : '';
  }

  private function getDynamicRows($name, $offset, $limit)
  {
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $rows = $this->db->get_results($this->db->prepare("SELECT * FROM `{$name}` ORDER BY `__position` ASC, `__id` ASC LIMIT %d, %d", $offset, $limit), ARRAY_A);
    $system = ['id' => '__id', 'position' => '__position', 'owner_id' => '__owner_id', 'settings' => '__settings', 'created_at' => '__created_at', 'updated_at' => '__updated_at'];
    $result = [];
    foreach ((array) $rows as $row) {
      foreach ($system as $key => $column) {
        if (isset($row[$column])) {
          $row[$key] = $row[$column];
        }
      }
      $result[] = $row;
    }

    return $result;
  }

  private function getItemRows(array $table, $offset, $limit)
  {
    $items = $this->getItemsTable();
    if ($items === '') {
      return [];
    }
    $settings = $table['settings'];
    $order = 'created_at DESC, id DESC';
    $sorting = isset($settings['sorting_type']) ? $settings['sorting_type'] : '';
    if ($sorting === 'manual_sort') {
      $order = 'position ASC, id ASC';
    } elseif ($sorting === 'by_created_at' && (!isset($settings['default_sorting']) || $settings['default_sorting'] !== 'new_first')) {
      $order = 'created_at ASC, id ASC';
    }
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $values = $this->db->get_col($this->db->prepare("SELECT `value` FROM `{$items}` WHERE table_id = %d ORDER BY {$order} LIMIT %d, %d", $table['id'], $offset, $limit));
    $result = [];
    foreach ((array) $values as $value) {
      $decoded = json_decode((string) $value, true);
      $result[] = is_array($decoded) ? $decoded : [];
    }

    return $result;
  }

  /**
   * The text of a cell as a visitor sees it. A "transform" template of the column becomes plain content.
   */
  private function getCellHtml(array $column, array $values, array $columns)
  {
    $value = isset($values[$column['key']]) ? $values[$column['key']] : '';
    $text = $this->valueToHtml($column, $value);
    if ($column['transform'] !== '') {
      $text = $this->applyTransform($column['transform'], $values, $columns);
    }

    return (string) $text;
  }

  private function valueToHtml(array $column, $value)
  {
    if (is_array($value)) {
      if (!empty($value['image_full']) || !empty($value['image_thumb'])) {
        $full = !empty($value['image_full']) ? (string) $value['image_full'] : (string) $value['image_thumb'];
        $thumb = !empty($value['image_thumb']) ? (string) $value['image_thumb'] : $full;
        $alt = isset($value['alt_text']) && is_scalar($value['alt_text']) ? (string) $value['alt_text'] : '';
        $image = '<img src="' . esc_url($thumb) . '" alt="' . esc_attr($alt) . '" style="max-width:100%;height:auto;" />';

        return $full !== $thumb ? '<a href="' . esc_url($full) . '">' . $image . '</a>' : $image;
      }
      $parts = [];
      foreach ($value as $part) {
        if (is_scalar($part)) {
          $parts[] = (string) $part;
        }
      }

      return implode(', ', $parts);
    }
    $value = is_scalar($value) ? (string) $value : '';
    if ($value === '') {
      return '';
    }
    if ($column['type'] === 'button') {
      $target = $column['linkTarget'] === '_blank' ? ' target="_blank" rel="noopener"' : '';

      return '<a class="button" href="' . esc_url($value) . '"' . $target . '>' . esc_html($column['buttonText'] !== '' ? $column['buttonText'] : $value) . '</a>';
    }
    if ($column['type'] === 'image' && filter_var($value, FILTER_VALIDATE_URL)) {
      return '<img src="' . esc_url($value) . '" alt="" style="max-width:100%;height:auto;" />';
    }

    return $value;
  }

  private function applyTransform($template, array $values, array $columns)
  {
    $byKey = [];
    foreach ($columns as $column) {
      $value = isset($values[$column['key']]) ? $values[$column['key']] : '';
      $byKey[$column['key']] = $this->valueToHtml($column, $value);
    }

    return preg_replace_callback('/\{\{\s*row\.([^\s}]+)\s*\}\}|\{row\.([^}]+)\}/', function ($match) use ($byKey) {
      $key = isset($match[2]) && $match[2] !== '' ? $match[2] : $match[1];

      return isset($byKey[$key]) ? $byKey[$key] : '';
    }, $template);
  }

  /**
   * A remote file is read once, at the time of the import. Only what was saved as an address of a site is opened.
   */
  private function getRemoteRows(array $table)
  {
    $key = 'remote' . $table['id'];
    if (isset($this->cache[$key])) {
      return $this->cache[$key];
    }
    $transient = 'stb_import_csv_' . (int) $table['id'];
    $rows = get_transient($transient);
    if (!is_array($rows)) {
      $rows = $this->fetchRemoteRows($table);
      // A failure is not kept: the file may be back in a minute
      if ($rows) {
        set_transient($transient, $rows, 10 * MINUTE_IN_SECONDS);
      }
    }
    $this->cache[$key] = $rows;

    return $rows;
  }

  private function fetchRemoteRows(array $table)
  {
    if ($table['remoteUrl'] === '') {
      return [];
    }
    $response = wp_safe_remote_get($table['remoteUrl'], ['timeout' => 20, 'redirection' => 3]);
    if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
      return [];
    }
    $lines = fopen('php://temp', 'r+');
    fwrite($lines, (string) wp_remote_retrieve_body($response));
    rewind($lines);
    $header = fgetcsv($lines, 0, ',', '"', '\\');
    if (!is_array($header)) {
      fclose($lines);

      return [];
    }
    $header = array_map('trim', $header);
    // Columns of the source are matched by name, and by position when the names differ
    $positions = [];
    foreach ($table['columns'] as $index => $column) {
      $at = array_search($column['title'], $header, true);
      $positions[] = $at === false ? $index : $at;
    }
    $rows = [];
    while (($line = fgetcsv($lines, 0, ',', '"', '\\')) !== false) {
      if ($line === [null]) {
        continue;
      }
      $row = [];
      foreach ($positions as $position) {
        $row[] = isset($line[$position]) ? (string) $line[$position] : '';
      }
      $rows[] = $row;
    }
    fclose($lines);

    return $rows;
  }

  /**
   * A drag and drop table is a free grid of cells with elements. Here it becomes rows of HTML with merged cells.
   */
  private function getGrid($id)
  {
    $key = 'grid' . $id;
    if (isset($this->cache[$key])) {
      return $this->cache[$key];
    }
    $data = $this->getArrayMeta($id, '_ninja_table_builder_table_data');
    $rawRows = isset($data['data']) && is_array($data['data']) ? array_values($data['data']) : [];
    $headers = isset($data['headers']) && is_array($data['headers']) ? array_values($data['headers']) : [];
    $width = count($headers);
    foreach ($rawRows as $row) {
      $width = max($width, isset($row['rows']) && is_array($row['rows']) ? count($row['rows']) : 0);
    }
    $grid = [];
    $merged = [];
    $covered = [];
    foreach ($rawRows as $rowIndex => $row) {
      $cells = isset($row['rows']) && is_array($row['rows']) ? array_values($row['rows']) : [];
      $line = array_fill(0, max(1, $width), '');
      $col = 0;
      foreach ($cells as $cell) {
        while (isset($covered[$rowIndex][$col])) {
          $col++;
        }
        if ($col >= $width) {
          break;
        }
        $style = isset($cell['style']) && is_array($cell['style']) ? $cell['style'] : [];
        $colspan = isset($style['colspan']) ? max(1, (int) $style['colspan']) : 1;
        $rowspan = isset($style['rowspan']) ? max(1, (int) $style['rowspan']) : 1;
        $line[$col] = $this->getGridCellHtml($cell);
        if ($colspan > 1 || $rowspan > 1) {
          $colspan = min($colspan, $width - $col);
          $rowspan = min($rowspan, count($rawRows) - $rowIndex);
          $merged[] = ['row' => $rowIndex, 'col' => $col, 'rowspan' => $rowspan, 'colspan' => $colspan];
          for ($r = 0; $r < $rowspan; $r++) {
            for ($c = 0; $c < $colspan; $c++) {
              if ($r > 0 || $c > 0) {
                $covered[$rowIndex + $r][$col + $c] = true;
              }
            }
          }
        }
        $col += $colspan;
      }
      $grid[] = $line;
    }
    $columns = [];
    for ($i = 0; $i < $width; $i++) {
      $columns[] = ['key' => 'column_' . $i, 'title' => isset($grid[0][$i]) ? $grid[0][$i] : '', 'type' => 'html', 'align' => '', 'width' => 0, 'hidden' => false, 'hiddenOn' => [], 'sortable' => false, 'classes' => '', 'dateFormat' => 'MM/DD/YYYY', 'thousand' => ',', 'decimal' => '.', 'transform' => '', 'conditions' => [], 'buttonText' => '', 'linkTarget' => ''];
    }
    $this->cache[$key] = ['rows' => $grid, 'merged' => $merged, 'columns' => $columns];

    return $this->cache[$key];
  }

  private function getGridCellHtml(array $cell)
  {
    $html = '';
    foreach (isset($cell['columns']) && is_array($cell['columns']) ? $cell['columns'] : [] as $piece) {
      if (is_array($piece) && isset($piece['data']) && is_array($piece['data'])) {
        $html .= $this->getElementHtml($piece['data']);
      }
    }

    return $html;
  }

  private function getElementHtml(array $element)
  {
    $type = isset($element['type']) ? (string) $element['type'] : 'text';
    $value = isset($element['value']) ? $element['value'] : '';
    $style = isset($element['style']) && is_array($element['style']) ? $element['style'] : [];
    switch ($type) {
      case 'text':
      case 'custom_html':
      case 'shortcode':
        return is_scalar($value) ? '<div>' . $value . '</div>' : '';
      case 'text_icon':
        return is_scalar($value) ? '<div>&#10003; ' . $value . '</div>' : '';
      case 'icon':
        return is_scalar($value) ? '<div>' . $this->getIcon((string) $value, $style) . '</div>' : '';
      case 'button':
        $url = isset($style['url']) && is_string($style['url']) ? $style['url'] : '#';

        return is_scalar($value) ? '<a class="button" href="' . esc_url($url) . '">' . esc_html((string) $value) . '</a>' : '';
      case 'image':
        $url = is_array($value) && isset($value['url']) ? (string) $value['url'] : (is_scalar($value) ? (string) $value : '');

        return $url !== '' ? '<img src="' . esc_url($url) . '" alt="" style="max-width:100%;height:auto;" />' : '';
      case 'star_rating':
        return is_numeric($value) ? '<div aria-label="' . esc_attr((string) $value) . '">' . str_repeat('&#9733;', max(0, min(5, (int) round((float) $value)))) . '</div>' : '';
      case 'ribbon':
        return is_scalar($value) ? '<strong>' . esc_html((string) $value) . '</strong>' : '';
      case 'progress':
        return is_numeric($value) ? '<div>' . esc_html((string) $value) . '%</div>' : '';
      case 'list':
      case 'stylist_list':
        return $this->getListHtml($value);
    }

    return is_scalar($value) ? '<div>' . esc_html((string) $value) . '</div>' : '';
  }

  private function getListHtml($value)
  {
    if (!is_array($value)) {
      return is_scalar($value) ? '<div>' . $value . '</div>' : '';
    }
    $items = '';
    foreach ($value as $item) {
      $text = is_array($item) ? (isset($item['value']) ? $item['value'] : (isset($item['text']) ? $item['text'] : '')) : $item;
      if (is_scalar($text) && (string) $text !== '') {
        $items .= '<li>' . $text . '</li>';
      }
    }

    return $items === '' ? '' : '<ul>' . $items . '</ul>';
  }

  /**
   * Its icon font is not ours: the common marks stay as text signs, in the color they had.
   */
  private function getIcon($name, array $style)
  {
    $sign = '&#8226;';
    if (preg_match('/check|tick|success|finish/i', $name)) {
      $sign = '&#10003;';
    } elseif (preg_match('/close|cross|times|cancel|wrong|delete/i', $name)) {
      $sign = '&#10005;';
    } elseif (preg_match('/minus|dash/i', $name)) {
      $sign = '&#8211;';
    } elseif (preg_match('/star/i', $name)) {
      $sign = '&#9733;';
    }
    $color = isset($style['color']) && is_string($style['color']) && preg_match('/^#[0-9a-f]{3,8}$/i', $style['color']) ? ' style="color:' . $style['color'] . ';"' : '';

    return '<span' . $color . ' aria-hidden="true">' . $sign . '</span>';
  }

  /**
   * @return array Where the shortcodes and blocks of its tables stand on the site: table id => pages.
   */
  public function getUsage()
  {
    $posts = $this->db->get_results(
      "SELECT ID, post_title, post_content FROM {$this->db->posts} WHERE post_status IN ('publish', 'draft', 'private', 'future') AND post_type NOT IN ('revision', 'nav_menu_item', 'ninja-table') AND (post_content LIKE '%[ninja\_tables%' OR post_content LIKE '%wp:ninja-tables/%') LIMIT 500"
    );
    $usage = [];
    foreach ((array) $posts as $post) {
      preg_match_all('/\[ninja_tables[^\]]*\bid=["\']?(\d+)|wp:ninja-tables\/[a-z-]+\s+\{[^}]*"tableId":"?(\d+)/', (string) $post->post_content, $matches, PREG_SET_ORDER);
      foreach ($matches as $match) {
        $table = !empty($match[1]) ? (int) $match[1] : (int) $match[2];
        $usage[$table][(int) $post->ID] = ['id' => (int) $post->ID, 'title' => (string) $post->post_title, 'url' => (string) get_edit_post_link((int) $post->ID, 'raw')];
      }
    }

    return array_map('array_values', $usage);
  }
}
