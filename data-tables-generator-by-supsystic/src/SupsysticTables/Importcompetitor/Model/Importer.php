<?php

/**
 * Turns a table read by a source into our table. There is no hard binding to the options of the source:
 * what we can do is moved, what we do differently gets our closest option, and our tables look like ours.
 * PRO options are applied only in PRO, whatever the browser sends.
 */
class SupsysticTables_Importcompetitor_Model_Importer extends SupsysticTables_Core_BaseModel
{
  const ROWS_PER_REQUEST = 400;
  // From this size a table of the source loads page by page here, whatever engine it used there
  const SERVER_SIDE_FROM = 2000;

  private function getFactory()
  {
    return $this->environment->getModule('core')->getModelsFactory();
  }

  private function getTables()
  {
    return $this->getFactory()->get('tables');
  }

  private function isPro()
  {
    return (bool) $this->environment->isPro();
  }

  private function translate($text)
  {
    return $this->environment->translate($text);
  }

  /**
   * What will happen with each option of a table, for the window before the import.
   * @return array Items of four kinds: moved, replaced, pro (needs our PRO) and skipped.
   */
  public function getReport(array $table, $rowsCount)
  {
    $items = [];
    $add = function ($kind, $label, $note = '') use (&$items) {
      $items[] = ['kind' => $kind, 'label' => $label, 'note' => $note];
    };
    $pro = $this->isPro();
    $settings = $table['settings'];

    $add('moved', $this->translate('Rows, columns and their order'));
    if ($this->usesServerSide($table, $rowsCount)) {
      $add('moved', $this->translate('Server-side processing'), $this->translate('Large tables load page by page.'));
    }
    $add('moved', $this->translate('Sorting, search and pagination'));
    if ($this->hasColumnOption($table, 'width') || $this->hasColumnOption($table, 'align')) {
      $add('moved', $this->translate('Column widths and alignment'));
    }
    if ($this->hasColumnOption($table, 'hidden')) {
      $add('moved', $this->translate('Hidden columns'));
    }
    if ($this->hasColumnOption($table, 'hiddenOn')) {
      $add('moved', $this->translate('Columns hidden on phones and tablets'));
    }
    if ($table['merged']) {
      $add('moved', $this->translate('Merged cells'));
    }

    $add('replaced', $this->translate('Table design'), $this->translate('Your table gets the design you have picked, with its colors and fonts.'));
    if (!empty($settings['stackable']) && $settings['stackable'] === 'yes') {
      $add('replaced', $this->translate('Stacked rows on phones'), $this->translate('Our standard responsive mode is used instead.'));
    }
    if ($this->hasColumnOption($table, 'transform')) {
      $add('replaced', $this->translate('Value transformation'), $this->translate('The result is written into the cells, so you can edit it as normal content.'));
    }
    if ($table['provider'] === 'drag_and_drop') {
      $add('replaced', $this->translate('Drag and drop layout'), $this->translate('It becomes a data table with the same cells. Icons become simple signs.'));
    }
    if ($table['customCss'] !== '') {
      $add('replaced', $this->translate('Custom CSS'), $this->translate('It is copied to the CSS of the table. Selectors of the old table may need changes.'));
    }

    if ($table['provider'] === 'csv' || $table['provider'] === 'google-csv') {
      $note = $this->translate('The data is copied once from the file.');
      if ($pro) {
        $add('replaced', $this->translate('Remote file'), $note . ' ' . $this->translate('To keep it updated, set up the import on the Source tab.'));
      } else {
        $add('replaced', $this->translate('Remote file'), $note);
        $add('pro', $this->translate('Automatic updates from a file or Google Sheets'));
      }
    }

    $filters = $this->getUsableFilters($table);
    if ($filters) {
      if ($pro) {
        $add('moved', $this->translate('Custom filters'), $this->translate('They become our column filters in a panel above the table.'));
        if ($this->hasOwnOptionLabels($filters)) {
          $add('replaced', $this->translate('Own names of filter options'), $this->translate('Filter options show the values of the column.'));
        }
      } else {
        $add('pro', $this->translate('Custom filters'), $this->translate('Dropdowns, buttons, ranges and date pickers. A simple search field under each column is used instead.'));
      }
    }
    $rules = $this->countConditions($table);
    if ($rules['usable'] > 0) {
      if ($pro) {
        $add('moved', $this->translate('Conditional formatting'));
      } else {
        $add('pro', $this->translate('Conditional formatting'));
      }
    }
    if ($rules['skipped'] > 0) {
      $add('skipped', $this->translate('Some conditional formatting rules'), $this->translate('Rules that change the content or CSS classes of cells have no match here.'));
    }
    if ($this->getExportFormats($table)) {
      if ($pro) {
        $add('moved', $this->translate('Export buttons'));
      } else {
        $add('pro', $this->translate('Export buttons'));
      }
    }
    if ($this->hasFrontEdit($table)) {
      if ($pro) {
        $add('moved', $this->translate('Editing on the site'));
      } else {
        $add('pro', $this->translate('Editing on the site'));
      }
    }
    if ($table['customJs'] !== '') {
      $add('skipped', $this->translate('Custom JavaScript'), $this->translate('It was written for the old table, so it is not run here.'));
    }
    if (!empty($settings['hide_header_row']) && $settings['hide_header_row'] === '1') {
      $add('skipped', $this->translate('Hidden header row'), $this->translate('The header stays visible: sorting and filters need it.'));
    }

    return $items;
  }

  private function hasOwnOptionLabels(array $filters)
  {
    foreach ($filters as $filter) {
      foreach ($filter['options'] as $option) {
        if ($option['label'] !== $option['value']) {
          return true;
        }
      }
    }

    return false;
  }

  private function hasColumnOption(array $table, $key)
  {
    foreach ($table['columns'] as $column) {
      if (!empty($column[$key])) {
        return true;
      }
    }

    return false;
  }

  private function usesServerSide(array $table, $rowsCount)
  {
    if ($table['merged'] || $table['provider'] === 'drag_and_drop') {
      return false;
    }

    return $table['engine'] === 'datatables' || (int) $rowsCount >= self::SERVER_SIDE_FROM;
  }

  /**
   * Creates the table with its settings and the row of titles. The rows follow in the next requests.
   * @return array The new table and how many rows are to come.
   */
  public function start($source, array $table, array $options)
  {
    if (empty($table['supported'])) {
      throw new InvalidArgumentException($table['reason']);
    }
    if (!$table['columns']) {
      throw new InvalidArgumentException($this->translate('This table has no columns to import.'));
    }
    $this->environment->getModule('tables')->setIniLimits();

    $rowsCount = $source->countRows($table);
    if ($rowsCount === 0 && $table['remoteUrl'] !== '') {
      throw new RuntimeException($this->translate('The remote file of this table could not be read.'));
    }
    $settings = $this->getSettings($table, $rowsCount, $options);
    $title = $this->getTitle($table);

    $model = $this->getTables();
    $id = (int) $model->add(['title' => $title, 'table_type' => 'default', 'settings' => serialize(['tableType' => 'default'])]);
    if (!$id) {
      throw new RuntimeException($this->translate('The table could not be created.'));
    }
    $model->set($id, ['settings' => htmlspecialchars(serialize($settings), ENT_QUOTES)]);
    $model->setRows($id, [$this->getHeaderCells($table, $source)]);

    return ['id' => $id, 'title' => $title, 'total' => $rowsCount];
  }

  private function getTitle(array $table)
  {
    $title = trim(sanitize_text_field($table['title']));
    if ($title === '') {
      $title = sprintf($this->translate('Imported table %d'), (int) $table['id']);
    }

    return function_exists('mb_substr') ? mb_substr($title, 0, 255) : substr($title, 0, 255);
  }

  /**
   * @return int The number of the next row, and the table is complete when it reaches the total.
   */
  public function addRows($source, array $table, $newId, $offset)
  {
    $this->environment->getModule('tables')->setIniLimits();
    $rows = $source->getRows($table, $offset, self::ROWS_PER_REQUEST);
    if (!$rows) {
      return $offset;
    }
    $prepared = [];
    $number = $offset + 2;
    $topMerged = $this->getMergedBody($table);
    foreach ($rows as $index => $values) {
      $prepared[] = $this->getBodyRow($table, $values, $number, $offset + $index + 1, $topMerged);
      $number++;
    }
    $this->getTables()->setRowsImport($newId, $prepared);

    return $offset + count($rows);
  }

  /**
   * The rest that needs the whole table: widths, merged cells and rules of formatting.
   */
  public function finish(array $table, $newId, $rowsCount)
  {
    $model = $this->getTables();
    $model->setMeta($newId, $this->getMeta($table, $newId));
    if ($this->isPro()) {
      $rules = $this->getConditions($table, $rowsCount);
      if ($rules) {
        $this->getFactory()->get('conditions', 'tables')->setConditions($newId, $rules);
      }
    }
    $core = $this->environment->getConfig();

    return [
      'id' => (int) $newId,
      'url' => $this->environment->generateUrl('tables', 'view', ['id' => (int) $newId, 'nonce' => wp_create_nonce('dtgs_nonce')]),
      'shortcode' => '[' . $core->get('shortcode_name') . ' id=' . (int) $newId . ']',
    ];
  }

  public function abort($newId)
  {
    if ((int) $newId > 0) {
      $this->getTables()->removeById((int) $newId);
    }
  }

  private function getSettings(array $table, $rowsCount, array $options)
  {
    $source = $table['settings'];
    $classes = isset($source['css_classes']) && is_array($source['css_classes']) ? $source['css_classes'] : ['striped', 'selectable'];
    $filters = $this->getUsableFilters($table);
    $isOff = function ($key) use ($source) {
      return isset($source[$key]) && in_array($source[$key], ['0', 0, 'no', false], true);
    };
    $paging = !(isset($source['show_all']) && $source['show_all'] === '1') && !$isOff('show_pager');
    $searching = !$isOff('enable_search') && !(isset($source['hide_default_filter']) && $source['hide_default_filter'] === 'yes');

    $tools = ['info' => true];
    // Filters work through the search of the table, also when the source hid its search field
    if ($searching || $filters) {
      $tools['searching'] = true;
    }
    if ($paging) {
      $tools['paging'] = true;
    }
    if (!$isOff('column_sorting') && $table['provider'] !== 'drag_and_drop') {
      $tools['ordering'] = true;
    }
    if (in_array('striped', $classes, true)) {
      $tools['stripe'] = true;
    }
    if (in_array('selectable', $classes, true) || in_array('hover', $classes, true)) {
      $tools['hover'] = true;
    }
    if (in_array('compact', $classes, true)) {
      $tools['compact'] = true;
    }
    if ($filters) {
      $tools['columnSearch'] = true;
    }

    $answers = ['palette' => isset($options['palette']) ? (string) $options['palette'] : 'ocean'];
    $settings = $this->getFactory()->get('builder', 'wizard')->getClassicSettings($answers, [], $tools, []);

    $settings['paginationMenuLength'] = $this->getPageSizes($source);
    $settings['elements'] = array_merge($settings['elements'], ['head' => 'on']);
    if (isset($source['show_title']) && $source['show_title'] === '1') {
      $settings['elements']['caption'] = 'on';
    }
    if (isset($source['show_description']) && $source['show_description'] === '1' && trim($table['description']) !== '') {
      $settings['elements']['description'] = 'on';
      $settings['elements']['descriptionText'] = wp_kses_post($table['description']);
    }
    $sort = $this->getSorting($table);
    if ($sort) {
      $settings['sortingOrderColumn'] = (string) $sort['column'];
      $settings['sortingOrder'] = $sort['order'];
    }
    $settings['responsiveMode'] = $this->getResponsiveMode($table);
    if ($settings['responsiveMode'] === '4') {
      $settings['responsiveColumnSettings'] = $this->getResponsiveColumns($table, $settings['responsiveColumnSettings']);
    }
    $dateFormat = $this->getDateFormat($table);
    if ($dateFormat !== '') {
      $settings['dateFormat'] = $dateFormat;
    }
    if ($this->usesServerSide($table, $rowsCount) && $paging) {
      $settings['serverSideProcessing'] = 'on';
    }

    if ($this->isPro()) {
      if ($filters) {
        $settings['searching']['columnFilters'] = wp_json_encode($this->getColumnFilters($table, $filters));
        $settings['searching']['columnSearchPosition'] = 'panel';
      }
      $formats = $this->getExportFormats($table);
      if ($formats) {
        $settings['features']['export'] = $formats;
        $settings['exportLinksPosition'] = 'before_table';
        $settings['exportOnlyVisible'] = 'on';
        $settings['exportLogo'] = ['src' => '', 'position' => 'top', 'alignment' => 'left'];
        $settings['pdfPaperSize'] = 'auto';
        $settings['pdfOrientation'] = 'portrait';
      }
      if ($this->hasFrontEdit($table)) {
        $settings['saveEditableFields'] = 'on';
        $settings['useEditableFieldsForLoggedInOnly'] = 'on';
      }
    }

    return $settings;
  }

  private function getPageSizes(array $source)
  {
    $sizes = [];
    if (isset($source['perPage']) && (int) $source['perPage'] > 0) {
      $sizes[] = (int) $source['perPage'];
    }
    $list = isset($source['paze_sizes']) && is_string($source['paze_sizes']) ? $source['paze_sizes'] : '';
    foreach ($list === '' ? [10, 25, 50] : explode(',', $list) as $size) {
      if ((int) $size > 0) {
        $sizes[] = (int) $size;
      }
    }
    $sizes = array_values(array_unique($sizes));
    if (!$sizes) {
      $sizes = [10, 25, 50];
    }
    $sizes[] = 'All';

    return implode(',', $sizes);
  }

  private function getSorting(array $table)
  {
    $source = $table['settings'];
    if (!isset($source['sorting_type']) || $source['sorting_type'] !== 'by_column' || empty($source['sorting_column'])) {
      return null;
    }
    foreach ($table['columns'] as $index => $column) {
      if ($column['key'] === $source['sorting_column']) {
        return ['column' => $index + 1, 'order' => isset($source['sorting_column_by']) && strtolower((string) $source['sorting_column_by']) === 'desc' ? 'desc' : 'asc'];
      }
    }

    return null;
  }

  private function getResponsiveMode(array $table)
  {
    if ($this->hasColumnOption($table, 'hiddenOn')) {
      return '4';
    }
    if (!empty($table['settings']['stackable']) && $table['settings']['stackable'] === 'yes') {
      return '0';
    }

    return '1';
  }

  /**
   * Columns that the source hides on a kind of device are shown there under a toggle.
   */
  private function getResponsiveColumns(array $table, array $defaults)
  {
    foreach (['desktop', 'tablet', 'mobile'] as $device) {
      $visible = [];
      foreach ($table['columns'] as $index => $column) {
        if (!in_array($device, $column['hiddenOn'], true)) {
          $visible[] = $index + 1;
        }
      }
      $defaults[$device]['visible'] = count($visible) === count($table['columns']) ? '*' : implode(',', $visible);
      $defaults[$device]['toggle'] = '*';
    }

    return $defaults;
  }

  private function getDateFormat(array $table)
  {
    foreach ($table['columns'] as $column) {
      if ($column['type'] === 'date') {
        return $column['dateFormat'];
      }
    }

    return '';
  }

  /**
   * @return array Filters that can work here: a reset button is always part of our active filters.
   */
  private function getUsableFilters(array $table)
  {
    $filters = [];
    foreach ($table['filters'] as $filter) {
      if ($filter['type'] === 'reset_filter') {
        continue;
      }
      // A hidden column is not printed, so a filter of it has nothing to look at
      $targets = [];
      foreach ($filter['targets'] as $target) {
        if (empty($table['columns'][$target]['hidden'])) {
          $targets[] = $target;
        }
      }
      if ($targets) {
        $filter['targets'] = $targets;
        $filters[] = $filter;
      }
    }

    return $filters;
  }

  private function getColumnFilters(array $table, array $filters)
  {
    $columns = [];
    foreach ($filters as $filter) {
      $col = $filter['targets'][0];
      // One filter for a column here: the first one of the source stays
      if (!isset($columns[$col])) {
        $columns[$col] = $this->getColumnFilter($filter, $table['columns'][$col]);
      }
    }
    // The other columns stay without a filter, also in the setup window
    foreach ($table['columns'] as $col => $column) {
      if (!isset($columns[$col])) {
        $columns[$col] = ['type' => 'none'];
      }
    }
    ksort($columns);
    $styling = $table['filterLayout'];

    return [
      'version' => 1,
      'showActive' => true,
      'narrow' => isset($styling['progressive']) && $styling['progressive'] === 'yes',
      'columns' => $columns,
    ];
  }

  private function getColumnFilter(array $filter, array $column)
  {
    $config = ['label' => $filter['title'] !== '' ? sanitize_text_field($filter['title']) : ''];
    $placeholder = sanitize_text_field($filter['placeholder']);
    switch ($filter['type']) {
      case 'number_range':
        return array_merge($config, ['type' => 'range', 'slider' => true]);
      case 'date_picker':
      case 'date_range':
        return array_merge($config, ['type' => 'date', 'mode' => strpos($column['dateFormat'], 'H') !== false || strpos($column['dateFormat'], 'h') !== false ? 'datetime' : 'date', 'format' => $column['dateFormat'], 'cellFormat' => $column['dateFormat'], 'presets' => true]);
      case 'radio':
        return array_merge($config, $this->getChoices($filter), ['type' => 'pills', 'allLabel' => $placeholder]);
      case 'checkbox':
        return array_merge($config, $this->getChoices($filter), ['type' => 'pills', 'multiple' => true, 'allLabel' => $placeholder]);
      case 'select':
        return array_merge($config, $this->getChoices($filter), ['type' => $filter['multiple'] ? 'multiselect' : 'select', 'allLabel' => $placeholder]);
      default:
        return array_merge($config, ['type' => 'text', 'match' => $filter['strict'] ? 'exact' : 'contains', 'placeholder' => $placeholder]);
    }
  }

  private function getChoices(array $filter)
  {
    $choices = ['counts' => true, 'sort' => $filter['sort']];
    if ($filter['separator'] !== '') {
      $choices['separator'] = $filter['separator'];
    }
    if (!$filter['dynamic'] && $filter['options']) {
      $values = [];
      foreach ($filter['options'] as $option) {
        $values[] = sanitize_text_field($option['value']);
      }
      $choices['source'] = 'list';
      $choices['list'] = implode("\n", $values);
    } else {
      $choices['source'] = 'auto';
    }

    return $choices;
  }

  private function getExportFormats(array $table)
  {
    $formats = [];
    foreach (['csv' => 'csv', 'xlsx' => 'xlsx', 'print' => 'print'] as $key => $format) {
      if (isset($table['buttons'][$key]['status']) && in_array($table['buttons'][$key]['status'], ['yes', 'true', true, '1', 1], true)) {
        $formats[] = $format;
      }
    }

    return $formats;
  }

  private function hasFrontEdit(array $table)
  {
    return isset($table['frontEdit']['allow_frontend']) && $table['frontEdit']['allow_frontend'] === 'yes';
  }

  private function countConditions(array $table)
  {
    $usable = 0;
    $skipped = 0;
    foreach ($table['columns'] as $column) {
      foreach ($column['conditions'] as $rule) {
        if ($this->getCondition($rule, $column, 'A2:A2')) {
          $usable++;
        } else {
          $skipped++;
        }
      }
    }

    return ['usable' => $usable, 'skipped' => $skipped];
  }

  private function getConditions(array $table, $rowsCount)
  {
    $rules = [];
    foreach ($table['columns'] as $index => $column) {
      $letter = $this->getColumnLetter($index);
      $range = $letter . '2:' . $letter . ((int) $rowsCount + 1);
      foreach ($column['conditions'] as $rule) {
        $condition = $this->getCondition($rule, $column, $range);
        if ($condition) {
          $rules[] = $condition;
        }
      }
    }

    return $rules;
  }

  /**
   * @return array|null A rule of our conditional formatting. It colors the cell, also for a rule of the source about its row.
   */
  private function getCondition($rule, array $column, $range)
  {
    if (!is_array($rule) || empty($rule['conditionalOperator']) || empty($rule['targetAction'])) {
      return null;
    }
    $operators = ['equal' => 'equal', 'not-equal' => 'notEqual', 'less-than' => 'lessThan', 'greater-than' => 'greaterThan', 'less-than-or-equal-to' => 'lessThanOrEqual', 'greater-than-or-equal-to' => 'greaterThanOrEqual', 'between' => 'between', 'contains' => 'containsText', 'does-not-contain' => 'notContains'];
    $operator = (string) $rule['conditionalOperator'];
    if (!isset($operators[$operator])) {
      return null;
    }
    $style = [];
    $color = $this->getHexColor(isset($rule['targetValueColor']) ? $rule['targetValueColor'] : '');
    switch ((string) $rule['targetAction']) {
      case 'set-cell-color':
      case 'set-row-color':
      case 'set-column-color':
        $style['color'] = $color;
        break;
      case 'set-cell-bg-color':
      case 'set-row-bg-color':
      case 'set-column-bg-color':
        $style['bg_color'] = $color;
        break;
      default:
        return null;
    }
    if ($color === '') {
      return null;
    }
    $value = isset($rule['conditionalValue']) && is_scalar($rule['conditionalValue']) ? (string) $rule['conditionalValue'] : '';
    $value2 = isset($rule['conditionalValue2']) && is_scalar($rule['conditionalValue2']) ? (string) $rule['conditionalValue2'] : '';
    // Our rules compare numbers as numbers: a date or a text cannot stand in a comparison of size
    if (in_array($operator, ['less-than', 'greater-than', 'less-than-or-equal-to', 'greater-than-or-equal-to', 'between'], true)) {
      if (!is_numeric($value) || ($operator === 'between' && !is_numeric($value2))) {
        return null;
      }
    }
    $type = in_array($operator, ['contains', 'does-not-contain'], true) ? 'containsText' : 'cellIs';
    $value = sanitize_text_field($value);

    return [
      'range' => $range,
      'condition' => [
        'conditionType' => $type,
        'operatorType' => $operators[$operator],
        'text' => $type === 'containsText' ? $value : null,
        'condition' => $operator === 'between' ? [$value, sanitize_text_field($value2)] : [$value],
        'style' => $style,
      ],
    ];
  }

  private function getHexColor($color)
  {
    $color = trim(is_scalar($color) ? (string) $color : '');
    if (preg_match('/^#?([0-9a-f]{6})$/i', $color, $match)) {
      return strtolower($match[1]);
    }
    if (preg_match('/^#?([0-9a-f])([0-9a-f])([0-9a-f])$/i', $color, $match)) {
      return strtolower($match[1] . $match[1] . $match[2] . $match[2] . $match[3] . $match[3]);
    }
    if (preg_match('/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $color, $match)) {
      return sprintf('%02x%02x%02x', min(255, (int) $match[1]), min(255, (int) $match[2]), min(255, (int) $match[3]));
    }

    return '';
  }

  private function getColumnLetter($index)
  {
    $letter = '';
    for ($number = $index + 1; $number > 0; $number = (int) (($number - 1) / 26)) {
      $letter = chr(65 + (($number - 1) % 26)) . $letter;
    }

    return $letter;
  }

  private function getHeaderCells(array $table, $source)
  {
    $cells = [];
    $merged = $this->getMergedAt($table, 0);
    foreach ($source->getHeaderRow($table) as $index => $title) {
      $cells[] = $this->getCell($table['columns'][$index], wp_kses_post((string) $title), 1, true, isset($merged[$index]));
    }

    return ['cells' => $cells];
  }

  /**
   * @return array Cells covered by a merged cell, for the rows of the body.
   */
  private function getMergedBody(array $table)
  {
    $covered = [];
    foreach ($table['merged'] as $area) {
      for ($r = 0; $r < $area['rowspan']; $r++) {
        for ($c = 0; $c < $area['colspan']; $c++) {
          if ($r > 0 || $c > 0) {
            $covered[$area['row'] + $r][$area['col'] + $c] = true;
          }
        }
      }
    }

    return $covered;
  }

  private function getMergedAt(array $table, $row)
  {
    $covered = $this->getMergedBody($table);

    return isset($covered[$row]) ? $covered[$row] : [];
  }

  private function getBodyRow(array $table, array $values, $number, $rowIndex, array $covered)
  {
    $cells = [];
    foreach ($table['columns'] as $index => $column) {
      $value = isset($values[$index]) ? (string) $values[$index] : '';
      $cells[] = $this->getCell($column, wp_kses_post($value), $number, false, isset($covered[$rowIndex][$index]), $table);
    }

    return ['cells' => $cells];
  }

  private function getCell(array $column, $value, $number, $isHeader, $isCovered = false, array $table = [])
  {
    $classes = ['ht' . ucfirst($column['align'] !== '' ? $column['align'] : ($column['type'] === 'number' ? 'right' : 'left'))];
    $source = isset($table['settings']) ? $table['settings'] : [];
    $classes[] = isset($source['css_classes']) && is_array($source['css_classes']) && !in_array('vertical_centered', $source['css_classes'], true) ? 'htTop' : 'htMiddle';
    foreach (preg_split('/\s+/', $column['classes']) as $class) {
      $class = sanitize_html_class($class);
      if ($class !== '') {
        $classes[] = $class;
      }
    }
    if ($column['hidden']) {
      $classes[] = 'hiddenCell';
    }
    if (!$isHeader && $this->hasFrontEdit($table) && $this->isPro() && $table['provider'] !== 'drag_and_drop') {
      $classes[] = 'editable';
    }

    $data = $value;
    $format = '';
    $formatType = '';
    if (!$isHeader && $value !== '') {
      if ($column['type'] === 'date') {
        $format = $column['dateFormat'];
        $formatType = 'date';
      } elseif ($column['type'] === 'number') {
        $parsed = $this->parseNumber($value, $column);
        if ($parsed !== null) {
          // The value of a number stays a number, so sorting, ranges and rules work on it
          $data = $parsed;
          $format = $this->getNumberFormat($value, $column);
          // Only this type of a cell keeps its own format: without a symbol in it, no symbol is shown
          $formatType = $format !== '' ? 'currency' : '';
          if ($format === '' && $parsed !== trim(wp_strip_all_tags($value))) {
            $data = $value;
          }
        }
      }
    }

    return [
      'y' => $number,
      'data' => (string) $data,
      'calculatedValue' => null,
      'hidden' => (bool) $isCovered,
      'hiddenCell' => (bool) $column['hidden'],
      'invisibleCell' => false,
      'formattedValue' => (string) $value,
      'type' => 'text',
      'baseType' => 'text',
      'formatType' => $formatType,
      'format' => $format,
      'meta' => $classes,
    ];
  }

  private function parseNumber($value, array $column)
  {
    $clean = str_replace([$column['thousand'], ' ', "\xc2\xa0"], '', trim(wp_strip_all_tags($value)));
    if ($column['decimal'] !== '.') {
      $clean = str_replace($column['decimal'], '.', $clean);
    }

    return is_numeric($clean) ? $clean : null;
  }

  private function getNumberFormat($value, array $column)
  {
    $text = trim(wp_strip_all_tags($value));
    $decimals = 0;
    $at = strrpos($text, $column['decimal']);
    if ($at !== false) {
      $decimals = strlen($text) - $at - 1;
    }
    if ($column['thousand'] === '' || strpos($text, $column['thousand']) === false) {
      return '';
    }

    // Our formats read both signs from the format itself, so the decimal sign stays also without decimals
    return '1' . $column['thousand'] . '000' . $column['decimal'] . str_repeat('0', $decimals);
  }

  private function getMeta(array $table, $id)
  {
    $widths = [];
    foreach ($table['columns'] as $column) {
      $widths[] = $column['width'] > 0 ? $column['width'] : 120;
    }
    $disabled = [];
    foreach ($table['columns'] as $index => $column) {
      if (!$column['sortable']) {
        $disabled[] = $index;
      }
    }
    $css = "/* Here you can add custom CSS for the current table */\n/* Lean more about CSS: https://en.wikipedia.org/wiki/Cascading_Style_Sheets */\n/*\nTo prevent the use of styles to other tables use \"#supsystic-table-{$id}\" as a base selector\nfor example:\n#supsystic-table-{$id} { ... }\n#supsystic-table-{$id} tbody { ... }\n#supsystic-table-{$id} tbody tr { ... }\n*/";
    if ($table['customCss'] !== '') {
      $css .= "\n\n/* Custom CSS of the imported table */\n" . wp_strip_all_tags($table['customCss']);
    }

    return [
      'requiredAssets' => [],
      'mergedCells' => array_map(function ($area) {
        return ['row' => (int) $area['row'], 'col' => (int) $area['col'], 'rowspan' => (int) $area['rowspan'], 'colspan' => (int) $area['colspan'], 'removed' => false];
      }, $table['merged']),
      'columnsWidth' => $widths,
      'columnsFixedWidth' => [],
      'columnsSortOrder' => [],
      'columnsDisableSorting' => $disabled,
      'css' => $css,
    ];
  }
}
