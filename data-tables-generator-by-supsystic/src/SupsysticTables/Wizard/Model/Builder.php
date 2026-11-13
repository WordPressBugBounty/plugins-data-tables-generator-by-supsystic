<?php

/**
 * Turns the answers of the table wizard into a real table: the same settings, rows and meta the editor saves.
 * Every answer is checked here. PRO options are applied only in PRO, whatever the browser sends.
 */
class SupsysticTables_Wizard_Model_Builder extends SupsysticTables_Core_BaseModel
{
  const MAX_ROWS = 2000;
  const MAX_COLS = 40;
  const CURRENCY_FORMAT = '$1,000.00';

  /**
   * @param array $answers The answers of the wizard.
   * @return array The created table: id, title, links and shortcodes.
   */
  public function create(array $answers)
  {
    $goals = $this->getCatalog()->getGoals();
    $goalKey = isset($answers['goal']) ? (string) $answers['goal'] : '';
    if (!isset($goals[$goalKey])) {
      throw new InvalidArgumentException($this->environment->translate('Choose what you want to build.'));
    }
    $goal = $goals[$goalKey];

    $this->environment->getModule('tables')->setIniLimits();

    if ($goal['kind'] === 'pricing') {
      return $this->createPricing($answers);
    }
    if ($goal['kind'] === 'woo') {
      return $this->createWoo($answers);
    }

    return $this->createData($answers, $goal);
  }

  private function getCatalog()
  {
    return $this->getFactory()->get('catalog', 'wizard');
  }

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

  private function getTitle(array $answers, $fallback)
  {
    $title = isset($answers['title']) ? trim(sanitize_text_field((string) $answers['title'])) : '';
    if ($title === '') {
      $title = $fallback;
    }

    return function_exists('mb_substr') ? mb_substr($title, 0, 255) : substr($title, 0, 255);
  }

  private function getResult($id, $title, $type, array $extra = [])
  {
    return array_merge([
      'id' => (int) $id,
      'title' => $title,
      'type' => $type,
      'url' => $this->environment->generateUrl('tables', 'view', ['id' => (int) $id, 'nonce' => wp_create_nonce('dtgs_nonce')]),
      'shortcode' => '[' . $this->environment->getConfig()->get('shortcode_name') . ' id=' . (int) $id . ']',
      'charts' => [],
    ], $extra);
  }

  /* ---- Classic tables ---- */

  private function createData(array $answers, array $goal)
  {
    $defaults = isset($goal['defaults']) ? $goal['defaults'] : [];
    $table = $this->getDataContent($answers, $goal);
    $title = $this->getTitle($answers, $table['title']);
    $tools = $this->getEnabledTools($answers);
    $settings = $this->getClassicSettings($answers, $defaults, $tools, $table);

    $model = $this->getTables();
    $id = $model->add(['title' => $title, 'table_type' => 'default', 'settings' => serialize(['tableType' => 'default'])]);
    if (!$id) {
      throw new RuntimeException($this->environment->translate('The table could not be created.'));
    }
    $model->set($id, ['settings' => htmlspecialchars(serialize($settings), ENT_QUOTES)]);
    $model->setRows($id, $this->getRows($table, $defaults, $tools));
    $model->setMeta($id, $this->getMeta($id, $table));

    $result = $this->getResult($id, $title, 'data');
    $chart = $this->addChart($id, $answers, $table);
    if ($chart) {
      $result['charts'][] = $chart;
    }
    // A PRO source opens the right place in the editor, the rest is done there
    $sources = $this->getCatalog()->getSources();
    $source = isset($answers['source']) ? (string) $answers['source'] : '';
    if ($this->isPro() && !empty($sources[$source]['tab'])) {
      $result['openTab'] = $sources[$source]['tab'];
    }

    return $result;
  }

  /**
   * @return array Title, columns and rows: from a ready data set, the pasted cells or an empty grid.
   */
  private function getDataContent(array $answers, array $goal)
  {
    if ($goal['kind'] === 'own') {
      $source = isset($answers['source']) ? (string) $answers['source'] : 'blank';
      if ($source === 'paste' && !empty($answers['pasted']['rows']) && is_array($answers['pasted']['rows'])) {
        return $this->getPastedContent($answers['pasted']);
      }

      return $this->getBlankContent($answers);
    }

    $datasets = $this->getCatalog()->getDatasets();
    $key = isset($answers['dataset']) ? (string) $answers['dataset'] : '';
    if (!isset($datasets[$key]) || !in_array($key, $goal['datasets'], true)) {
      $key = $goal['datasets'][0];
    }
    $dataset = $datasets[$key];
    if (!empty($answers['empty'])) {
      $dataset['rows'] = array_fill(0, 5, array_fill(0, count($dataset['columns']), ''));
      unset($dataset['chart']);
    }

    return $dataset;
  }

  private function getPastedContent(array $pasted)
  {
    $rows = [];
    foreach (array_slice($pasted['rows'], 0, self::MAX_ROWS + 1) as $row) {
      if (!is_array($row)) {
        continue;
      }
      $cells = [];
      foreach (array_slice($row, 0, self::MAX_COLS) as $cell) {
        $cells[] = wp_kses_post(is_scalar($cell) ? (string) $cell : '');
      }
      $rows[] = $cells;
    }
    if (!$rows) {
      throw new InvalidArgumentException($this->environment->translate('There is nothing to import in the pasted text.'));
    }

    $width = max(array_map('count', $rows));
    foreach ($rows as $index => $row) {
      $rows[$index] = array_pad($row, $width, '');
    }

    $titles = [];
    if (!empty($pasted['hasHeader'])) {
      $titles = array_shift($rows);
    }
    if (!$titles) {
      for ($i = 1; $i <= $width; $i++) {
        $titles[] = sprintf($this->environment->translate('Column %d'), $i);
      }
    }
    if (!$rows) {
      $rows = [array_fill(0, $width, '')];
    }

    $columns = [];
    foreach ($titles as $index => $columnTitle) {
      $numeric = true;
      foreach ($rows as $row) {
        if ($row[$index] !== '' && !is_numeric($row[$index])) {
          $numeric = false;
          break;
        }
      }
      $columns[] = ['title' => $columnTitle, 'format' => '', 'align' => $numeric ? 'right' : 'left', 'numeric' => $numeric];
    }

    $chartTo = 0;
    for ($i = 1; $i < min(4, $width); $i++) {
      if (empty($columns[$i]['numeric'])) {
        break;
      }
      $chartTo = $i;
    }

    $content = ['title' => $this->environment->translate('My table'), 'columns' => $columns, 'rows' => $rows];
    if ($chartTo > 0) {
      $content['chart'] = ['to' => $chartTo, 'title' => $this->environment->translate('My chart'), 'type' => 'ChartjsColumn'];
    }

    return $content;
  }

  private function getBlankContent(array $answers)
  {
    $rowsCount = isset($answers['blankRows']) ? (int) $answers['blankRows'] : 5;
    $colsCount = isset($answers['blankCols']) ? (int) $answers['blankCols'] : 4;
    $rowsCount = max(1, min(self::MAX_ROWS, $rowsCount));
    $colsCount = max(1, min(self::MAX_COLS, $colsCount));
    $columns = [];
    for ($i = 1; $i <= $colsCount; $i++) {
      $columns[] = ['title' => sprintf($this->environment->translate('Column %d'), $i), 'format' => '', 'align' => 'left'];
    }

    return [
      'title' => $this->environment->translate('My table'),
      'columns' => $columns,
      'rows' => array_fill(0, $rowsCount, array_fill(0, $colsCount, '')),
    ];
  }

  /**
   * @return string[] The tools that are switched on and allowed in this version.
   */
  private function getEnabledTools(array $answers)
  {
    $known = $this->getCatalog()->getTools();
    $enabled = [];
    foreach (isset($answers['tools']) && is_array($answers['tools']) ? $answers['tools'] : [] as $key => $value) {
      if (empty($value) || !isset($known[$key])) {
        continue;
      }
      if (!empty($known[$key]['pro']) && !$this->isPro()) {
        continue;
      }
      $enabled[$key] = true;
    }

    return $enabled;
  }

  private function getPalette(array $answers, $fallback)
  {
    $library = $this->environment->getModule('tables')->getClassicDesignPaletteLibrary();
    $key = isset($answers['palette']) ? (string) $answers['palette'] : $fallback;
    if (!isset($library[$key]) || (!empty($library[$key]['pro']) && !$this->isPro())) {
      $key = isset($library[$fallback]) ? $fallback : 'paper';
    }

    return [$key, $library[$key]];
  }

  /**
   * The same settings the editor form sends for a new table, with the answers applied.
   */
  public function getClassicSettings(array $answers, array $defaults, array $tools, array $table)
  {
    list($paletteKey, $palette) = $this->getPalette($answers, isset($defaults['palette']) ? $defaults['palette'] : 'paper');
    $styles = $palette['styles'];
    // The lists of fonts are filled only in PRO: a free table keeps the font of the theme
    if (!$this->isPro()) {
      unset($styles['headerFontFamily'], $styles['cellFontFamily']);
    }
    $styles = array_merge($styles, ['useCustomStyles' => 'on', 'verticalAlignment' => '', 'horizontalAlignment' => '', 'paginationPosition' => '']);
    $styles['customCss'] = $this->getCustomCss($styles);

    $features = ['after_table_loaded_script' => '', 'auto_width' => 'on'];
    foreach (['searching', 'ordering', 'paging', 'info'] as $feature) {
      if (!empty($tools[$feature])) {
        $features[$feature] = 'on';
      }
    }

    $styling = ['cellMaxHeight' => '', 'cellMaxWidth' => '', 'border' => 'cell-border', 'cellMaxWidthRules' => ''];
    foreach (['stripe', 'hover', 'compact'] as $style) {
      if (!empty($tools[$style])) {
        $styling[$style] = 'on';
      }
    }

    $searching = ['columnSearchPosition' => 'top', 'minChars' => '0'];
    if (!empty($tools['columnSearch'])) {
      // Under the column titles, as the preview of the wizard shows them
      $searching = array_merge($searching, ['columnSearch' => 'on', 'columnSearchShowLabel' => 'on']);
    }

    $settings = [
      'styles' => $styles,
      'classicDesign' => array_merge($palette['values'], ['enabled' => 'on', 'preset' => $paletteKey]),
      'elements' => ['head' => 'on', 'descriptionText' => '', 'signatureText' => ''],
      'headerRowsCount' => '1',
      'footerRowsCount' => '1',
      'fixedHeight' => '400',
      'fixedLeftColumnsCount' => '1',
      'fixedRightColumnsCount' => '0',
      'autoIndex' => 'off',
      // With a number format the site reformats every number of the table, also 4.9 or 2024
      'numberFormat' => '',
      'currencyFormat' => self::CURRENCY_FORMAT,
      'percentFormat' => '10.00%',
      'dateFormat' => 'DD.MM.YYYY',
      'timeDurationFormat' => 'HH:mm',
      'responsiveMode' => !empty($defaults['scroll']) ? '2' : '1',
      'sortingOrder' => 'asc',
      // No first sorting: the rows stay in the order of the data
      'sortingOrderColumn' => '0',
      'paginationMenuLength' => '10,25,50,All',
      'paginationSize' => 'pagination-large',
      'searching' => $searching,
      'features' => $features,
      'tableWidth' => '100',
      'tableWidthType' => '%',
      'tableWidthMobile' => '100',
      'tableWidthMobileType' => '%',
      'styling' => $styling,
      'tableLoader' => ['iconName' => 'default', 'iconItems' => '0', 'color' => '#000000'],
      'language' => ['emptyTable' => '', 'info' => '', 'infoEmpty' => '', 'infoFiltered' => '', 'lengthMenu' => '', 'search' => '', 'previous' => '', 'next' => '', 'zeroRecords' => '', 'exportLabel' => '', 'file' => 'default'],
      'responsiveColumnSettings' => [
        'desktop' => ['minWidth' => '1025', 'maxWidth' => '', 'visible' => '*', 'toggle' => ''],
        'tablet' => ['minWidth' => '768', 'maxWidth' => '1024', 'visible' => '1-5', 'toggle' => '*'],
        'mobile' => ['minWidth' => '0', 'maxWidth' => '767', 'visible' => '1-2', 'toggle' => '*'],
      ],
      'tableType' => 'default',
    ];

    $sizes = $this->getCatalog()->getSizes();
    $size = isset($answers['size']) && isset($sizes[$answers['size']]) ? $sizes[$answers['size']] : [];
    if (!empty($size['serverSide']) && !empty($tools['paging'])) {
      $settings['serverSideProcessing'] = 'on';
    }

    if (!empty($tools['export'])) {
      $settings['features']['export'] = ['csv', 'xlsx', 'pdf', 'print'];
      $settings['exportLinksPosition'] = 'before_table';
      $settings['exportOnlyVisible'] = 'on';
      $settings['exportLogo'] = ['src' => '', 'position' => 'top', 'alignment' => 'left'];
      $settings['pdfPaperSize'] = 'auto';
      $settings['pdfOrientation'] = 'portrait';
    }
    if (!empty($tools['editable'])) {
      $settings['saveEditableFields'] = 'on';
      $settings['useEditableFieldsForLoggedInOnly'] = 'on';
    }

    return $settings;
  }

  /**
   * The rules the editor writes for these styles (see the "Table Styling" part of tables.view.js).
   */
  private function getCustomCss(array $styles)
  {
    $table = '#supsystic-table-{id}';
    $wrapper = $table . '_wrapper';
    $value = function ($key) use ($styles) {
      return isset($styles[$key]) ? (string) $styles[$key] : '';
    };
    $outer = $value('externalBorderWidth') . 'px solid ' . $value('externalBorderColor');
    $row = $value('rowBorderWidth') . 'px solid ' . $value('rowBorderColor');
    $column = $value('columnBorderWidth') . 'px solid ' . $value('columnBorderColor');
    $cell = $value('cellBackgroundColor');
    $search = $table . '_filter input, ' . $wrapper . ' .stbColumnsSearchWrapper input';
    $thFont = $value('headerFontFamily') && $value('headerFontFamily') !== 'default' ? 'font-family: "' . $value('headerFontFamily') . '"; ' : '';
    $tdFont = $value('cellFontFamily') && $value('cellFontFamily') !== 'default' ? 'font-family: "' . $value('cellFontFamily') . '"; ' : '';

    $rules = [
      $wrapper . ' table' => 'border-collapse: collapse;',
      $wrapper . ' tbody tr' => 'background-color: ' . $cell . ';',
      $wrapper . ' table.stripe tbody tr.even' => 'background-color: ' . $this->shade($cell, -20) . ';',
      $wrapper . ' table.stripe.order-column tbody tr > .sorting_1' => 'background-color: ' . $this->shade($cell, -20) . ';',
      $wrapper . ' table.hover tbody tr:hover' => 'background-color: ' . $this->shade($cell, -40) . ';',
      $wrapper . ' table.stripe.order-column tbody tr.even > .sorting_1' => 'background-color: ' . $this->shade($cell, -40) . ';',
      $wrapper . ' table.order-column tbody tr > .sorting_1' => 'background-color: ' . $this->shade($cell, -20) . ';',
      $wrapper . ' table.hover.order-column tbody tr:hover > .sorting_1' => 'background-color: ' . $this->shade($cell, -60) . ';',
      $wrapper . ' tbody td' => 'background-color: inherit;',
      $wrapper . ' .dataTables_scroll' => 'border: ' . $outer . ' !important;',
      $wrapper . ' .DTFC_ScrollWrapper' => 'border: ' . $outer . ' !important;',
      $wrapper . ' .DTFC_ScrollWrapper .dataTables_scroll' => 'border: none !important;',
      $wrapper . ' .dataTables_scrollBody table' => 'border: none !important;',
      $wrapper . ' .dataTables_scrollBody th' => 'border-top: none !important; border-bottom: none !important;',
      $wrapper . ' .DTFC_LeftBodyWrapper th' => 'border-top: none !important; border-bottom: none !important;',
      $wrapper . ' .DTFC_RightBodyWrapper th' => 'border-top: none !important; border-bottom: none !important;',
      $wrapper . ' th' => 'font-size: ' . $value('headerFontSize') . 'px; color: ' . $value('headerFontColor') . '; border: ' . $value('headerBorderWidth') . 'px solid ' . $value('headerBorderColor') . ' !important; background-color: ' . $value('headerBackgroundColor') . ' !important; ' . $thFont,
      $wrapper . ' tbody tr:last-child td' => 'border-bottom: ' . $row . ';',
      $wrapper . ' tbody tr:first-child td' => 'border-top: none;',
      $wrapper . ' td' => 'border-right: ' . $column . '; border-left: ' . $column . '; border-top: ' . $row . '; font-size: ' . $value('cellFontSize') . 'px; color: var(--stb-rowText, ' . $value('cellFontColor') . '); ' . $tdFont,
      $wrapper . ' .child table' => 'border-collapse: collapse;',
      $table => 'border: ' . $outer . ' !important;',
      $search => 'border: 1px solid ' . $value('searchBorderColor') . ' !important; color: ' . $value('searchFontColor') . ' !important; background-color: ' . $value('searchBackgroundColor') . ' !important;',
    ];

    $css = '';
    foreach ($rules as $selector => $declarations) {
      $css .= $selector . ' { ' . trim($declarations) . ' }';
    }

    return $css;
  }

  /**
   * getLightenDarkenColor() of tables.model.js: the stripes and the hover of the editor come from it.
   */
  private function shade($color, $amount)
  {
    $hex = ltrim((string) $color, '#');
    if (strlen($hex) === 3) {
      $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
      return (string) $color;
    }
    $channel = function ($start) use ($hex, $amount) {
      return max(0, min(255, hexdec(substr($hex, $start, 2)) + $amount));
    };

    return sprintf('#%02x%02x%02x', $channel(0), $channel(2), $channel(4));
  }

  private function getRows(array $table, array $defaults, array $tools)
  {
    $rows = [];
    $number = 1;
    $titles = [];
    foreach ($table['columns'] as $column) {
      $titles[] = $column['title'];
    }
    $rows[] = $this->getRow($titles, $table, $defaults, $number++, true, []);
    foreach ($table['rows'] as $values) {
      $rows[] = $this->getRow($values, $table, $defaults, $number++, false, $tools);
    }

    return $rows;
  }

  private function getRow(array $values, array $table, array $defaults, $number, $isHeader, array $tools)
  {
    $cells = [];
    foreach ($values as $index => $value) {
      $column = isset($table['columns'][$index]) ? $table['columns'][$index] : ['format' => '', 'align' => 'left'];
      $align = !empty($defaults['center']) && $index > 0 ? 'center' : $column['align'];
      $classes = ['ht' . ucfirst($align), 'htMiddle'];
      if (!empty($defaults['boldFirst']) && $index === 0 && !$isHeader) {
        $classes[] = 'bold';
      }
      if (!empty($tools['editable']) && !$isHeader) {
        $classes[] = 'editable';
      }
      $format = $isHeader || $value === '' || $column['format'] !== 'currency' ? '' : 'currency';
      $cells[] = [
        'y' => $number,
        'data' => (string) $value,
        'calculatedValue' => null,
        'hidden' => false,
        'hiddenCell' => false,
        'invisibleCell' => false,
        'formattedValue' => $format === 'currency' ? '$' . number_format((float) $value, 2, '.', ',') : (string) $value,
        'type' => 'text',
        'baseType' => 'text',
        'formatType' => $format,
        'format' => $format === 'currency' ? self::CURRENCY_FORMAT : '',
        'meta' => $classes,
      ];
    }

    return ['cells' => $cells];
  }

  private function getMeta($id, array $table)
  {
    $widths = [];
    foreach ($table['columns'] as $index => $column) {
      $longest = strlen((string) $column['title']);
      foreach (array_slice($table['rows'], 0, 20) as $row) {
        $value = isset($row[$index]) ? (string) $row[$index] : '';
        if ($value !== '' && $column['format'] === 'currency') {
          $value = '$' . number_format((float) $value, 2, '.', ',');
        }
        $longest = max($longest, strlen($value));
      }
      $widths[] = max(70, min(280, 30 + $longest * 8));
    }

    return [
      'requiredAssets' => [],
      'mergedCells' => [],
      'columnsWidth' => $widths,
      'columnsFixedWidth' => [],
      'columnsSortOrder' => [],
      'columnsDisableSorting' => [],
      'css' => "/* Here you can add custom CSS for the current table */\n/* Lean more about CSS: https://en.wikipedia.org/wiki/Cascading_Style_Sheets */\n/*\nTo prevent the use of styles to other tables use \"#supsystic-table-{$id}\" as a base selector\nfor example:\n#supsystic-table-{$id} { ... }\n#supsystic-table-{$id} tbody { ... }\n#supsystic-table-{$id} tbody tr { ... }\n*/",
    ];
  }

  /**
   * In PRO the chart is saved the way the Diagrams tab saves it.
   */
  private function addChart($id, array $answers, array $table)
  {
    $charts = $this->getCatalog()->getCharts();
    $type = isset($answers['chart']) ? (string) $answers['chart'] : '';
    if (!$this->isPro() || empty($table['chart']) || !isset($charts[$type])) {
      return null;
    }

    $to = (int) $table['chart']['to'];
    $title = $table['chart']['title'];
    // A pie shows one column of values
    if ($to > 1 && in_array($type, ['ChartjsPie', 'ChartjsDoughnut'], true)) {
      $to = 1;
      $title = $table['columns'][1]['title'];
    }
    $range = [[]];
    for ($col = 0; $col <= $to; $col++) {
      $range[0][] = (string) $table['columns'][$col]['title'];
    }
    foreach ($table['rows'] as $row) {
      $values = [];
      for ($col = 0; $col <= $to; $col++) {
        $values[] = (string) $row[$col];
      }
      $range[] = $values;
    }

    $data = [
      'table_id' => (string) $id,
      'type' => $type,
      'options' => [
        'chart_width' => '',
        'chart_height' => '',
        'title' => $title,
        'legend' => ['position' => 'top', 'toggling' => 'disabled'],
        'pieSliceText' => 'percentage',
        'chartArea' => ['top' => '', 'bottom' => '', 'left' => '', 'right' => ''],
        'range' => '',
      ],
      'params' => ['useFirstRow' => 'on', 'useFirstColumn' => 'on', 'switchRowsColumns' => false, 'showLabelIntoDiagram' => false],
      'selection' => [
        'highlight' => ['row' => 0, 'col' => 0],
        'from' => ['row' => 0, 'col' => 0],
        'to' => ['row' => count($table['rows']), 'col' => $to],
      ],
      'unselected' => new stdClass(),
      'rangeData' => $range,
      'linesCount' => $range,
    ];

    $chartId = $this->getFactory()->get('diagrams', 'diagram')->add(['table_id' => (int) $id, 'data' => wp_json_encode($data)]);
    if (!$chartId) {
      return null;
    }

    return ['id' => (int) $chartId, 'shortcode' => '[supsystic-table-diagram id=' . (int) $chartId . ']'];
  }

  /* ---- WooCommerce tables ---- */

  private function createWoo(array $answers)
  {
    if (!class_exists('WooCommerce') || !function_exists('wc_get_products')) {
      throw new InvalidArgumentException($this->environment->translate('WooCommerce is not active on this site.'));
    }
    $module = $this->environment->getModule('woocommerce');
    if (!$module) {
      throw new InvalidArgumentException($this->environment->translate('WooCommerce is not active on this site.'));
    }
    $woo = isset($answers['woo']) && is_array($answers['woo']) ? $answers['woo'] : [];
    $isPro = $this->isPro();
    $tools = isset($woo['tools']) && is_array($woo['tools']) ? $woo['tools'] : [];
    $layouts = $this->getCatalog()->getWooLayouts();
    $layoutKey = isset($woo['layout']) && isset($layouts[$woo['layout']]) ? $woo['layout'] : 'catalog';
    $layout = $layouts[$layoutKey];

    $settings = [
      'enable' => 'on',
      'thumbnail_size' => 'thumbnail',
      'multiple_add_cart' => !empty($tools['multi']) || !empty($layout['multi']) ? 'on' : '',
      '_view_checkout_show_present' => '1',
      'view_checkout_show' => 'on',
      'productids' => '',
      'order' => $this->getWooOrder($layout['columns']),
    ];

    $source = isset($woo['source']) ? (string) $woo['source'] : 'latest';
    if (($source === 'all' || $source === 'category') && !$isPro) {
      $source = 'latest';
    }
    if ($source === 'latest') {
      $settings['productids'] = implode(',', wc_get_products(['limit' => 20, 'orderby' => 'date', 'order' => 'DESC', 'status' => 'publish', 'return' => 'ids']));
    } elseif ($source === 'all') {
      $settings['auto_categories_enable'] = 'on';
      $settings['auto_categories_list'] = 'all';
    } elseif ($source === 'category') {
      $categories = isset($woo['categories']) ? array_filter(array_map('absint', (array) $woo['categories'])) : [];
      $settings['auto_categories_enable'] = 'on';
      $settings['auto_categories_list'] = $categories ? implode(',', $categories) : 'all';
    }

    if ($isPro) {
      $settings['filter_category_list'] = !empty($tools['filterCategory']) ? 'on' : '';
      $settings['_filter_price_show_inputs_present'] = '1';
      $settings['_filter_price_show_slider_present'] = '1';
      $settings['filter_price_show_inputs'] = !empty($tools['filterPrice']) ? 'on' : '';
      $settings['filter_price_show_slider'] = !empty($tools['filterPrice']) ? 'on' : '';
      $palettes = $module->getWooClassicPalettes();
      $paletteKey = isset($answers['palette']) ? (string) $answers['palette'] : '';
      if (isset($palettes[$paletteKey])) {
        $settings['design'] = array_merge($palettes[$paletteKey]['design'], ['enabled' => 'on']);
      }
    }
    $wooSettings = $module->getController()->sanitizeWizardSettings($settings);

    $tableSettings = [
      'tableType' => 'woo_product_table',
      'sourceType' => 'woocommerce',
      'updateMode' => 'manual',
      'designMode' => 'default',
      'builderPlaceholder' => false,
      'elements' => ['descriptionText' => '', 'signatureText' => '', 'head' => 'on'],
      'features' => [],
      'searching' => [],
      'styling' => ['border' => 'cell-border', 'hover' => 'on', 'stripe' => 'on'],
      'tableLoader' => ['iconName' => 'default', 'iconItems' => 0, 'color' => '#000000'],
      'responsiveMode' => '1',
      'sortingOrder' => 'asc',
      'sortingOrderColumn' => '0',
      'paginationMenuLength' => '10,25,50,All',
      'paginationSize' => 'pagination-large',
      'tableWidth' => '100',
      'tableWidthType' => '%',
      'tableWidthMobile' => '100',
      'tableWidthMobileType' => '%',
      'headerRowsCount' => 1,
    ];
    foreach (['ordering', 'paging', 'info'] as $feature) {
      if (!empty($tools[$feature])) {
        $tableSettings['features'][$feature] = 'on';
      }
    }
    if ($isPro && !empty($tools['searching'])) {
      $tableSettings['features']['searching'] = 'on';
    }
    if ($isPro && !empty($tools['columns'])) {
      $tableSettings['features']['showHideColumnsList'] = 'on';
    }

    $title = $this->getTitle($answers, $this->environment->translate('Product table'));
    $model = $this->getTables();
    $id = $model->add([
      'title' => $title,
      'table_type' => 'woo_product_table',
      'settings' => serialize(['tableType' => 'woo_product_table']),
      'woo_settings' => serialize($wooSettings),
    ]);
    if (!$id) {
      throw new RuntimeException($this->environment->translate('The table could not be created.'));
    }
    $model->set($id, ['settings' => htmlspecialchars(serialize($tableSettings), ENT_QUOTES)]);
    $model->setRows($id, [['cells' => [['data' => '', 'calculatedValue' => '', 'hidden' => '', 'type' => 'text', 'formatType' => '', 'meta' => []]]]]);

    return $this->getResult($id, $title, 'woo');
  }

  /**
   * The columns in the order of the layout, each with its title in the table header.
   */
  private function getWooOrder(array $columnIds)
  {
    $known = [];
    foreach ($this->getFactory()->get('Woocolumns', 'woocommerce')->getAllWooColumns() as $column) {
      $known[(int) $column['id']] = $column;
    }
    $order = [];
    foreach ($columnIds as $columnId) {
      if (!isset($known[$columnId])) {
        continue;
      }
      $column = $known[$columnId];
      $order[] = [
        'id' => (string) $columnId,
        'slug' => $column['slug'],
        'name' => $column['name'],
        'original_name' => $column['name'],
        'show_display_name' => 1,
        'display_name' => $column['name'],
      ];
    }

    return wp_json_encode($order);
  }

  /* ---- Pricing tables ---- */

  private function createPricing(array $answers)
  {
    $pricing = isset($answers['pricing']) && is_array($answers['pricing']) ? $answers['pricing'] : [];
    $sets = $this->getCatalog()->getPlanSets();
    $setKey = isset($pricing['set']) && isset($sets[$pricing['set']]) ? $pricing['set'] : 'saas';
    $set = $sets[$setKey];
    $count = isset($pricing['count']) ? max(2, min(4, (int) $pricing['count'])) : 3;
    $plans = array_slice($set['plans'], 0, $count);
    $extras = isset($pricing['extras']) && is_array($pricing['extras']) ? $pricing['extras'] : [];
    $featured = 1;
    $title = $this->getTitle($answers, $set['title']);

    $model = $this->getTables();
    $id = $model->add([
      'title' => $title,
      'table_type' => 'pricing_table',
      'settings' => serialize(['tableType' => 'pricing_table', 'sourceType' => 'manual', 'updateMode' => 'manual', 'designMode' => 'default', 'builderPlaceholder' => true]),
    ]);
    if (!$id) {
      throw new RuntimeException($this->environment->translate('The table could not be created.'));
    }
    $emptyCell = ['data' => '', 'calculatedValue' => '', 'hidden' => '', 'type' => 'text', 'formatType' => '', 'meta' => []];
    $model->setRows($id, array_fill(0, 3, ['cells' => array_fill(0, 3, $emptyCell)]));

    $module = $this->environment->getModule('pricing');
    $templates = $module->getTemplates();
    $template = isset($pricing['template']) && isset($templates[$pricing['template']]) ? (string) $pricing['template'] : 'classic';
    $monthly = $this->getPlansHtml($plans, $featured, !empty($extras['badge']), $set['unit'], 'price', $template);
    $billing = !empty($extras['billing']);
    $data = [
      'template' => $template,
      'html' => $monthly,
      'css' => '',
      'maxWidth' => 100,
      'maxWidthUnit' => '%',
      'toggle' => $billing,
      'toggleText' => '',
      'toggleStyle' => 'rounded',
      'toggleDefault' => 'monthly',
      'toggleOptions' => $billing ? [['key' => 'monthly', 'label' => $this->environment->translate('Monthly')], ['key' => 'yearly', 'label' => $this->environment->translate('Yearly')]] : [],
      'toggleVariants' => $billing ? ['monthly' => $monthly, 'yearly' => $this->getPlansHtml($plans, $featured, !empty($extras['badge']), $set['unit'], 'yearly', $template)] : [],
      'toggleVariantStyles' => [],
      'ajaxToggle' => !empty($extras['ajax']),
      'ajaxToggleText' => $this->environment->translate('Show pricing table'),
      'descriptionColumn' => false,
      'showHeader' => true,
      'showDescription' => true,
      'showFooter' => true,
      'hoverAnimation' => !empty($extras['hover']),
    ];

    $table = $model->getById($id);
    $this->getFactory()->get('builder', 'pricing')->saveBuilder($table, $data, $module->getCapabilities(), $templates);

    return $this->getResult($id, $title, 'pricing');
  }

  private function getPlansHtml(array $plans, $featured, $badge, $unit, $priceKey, $template)
  {
    // The theme class goes on the grid too, as the Pricing editor puts it
    $html = '<section class="st-pricing-grid st-pricing-' . esc_attr($template) . '">';
    foreach ($plans as $index => $plan) {
      $isFeatured = $index === $featured;
      $features = '';
      foreach ($plan['features'] as $feature) {
        $features .= '<li class="st-pricing-feature"><span class="st-pricing-feature-content"><span class="st-pricing-icon fa fa-check" data-icon-scale="1" aria-hidden="true"></span><span class="st-pricing-feature-text">' . esc_html($feature) . '</span></span></li>';
      }
      $html .= '<article class="st-pricing-card' . ($isFeatured ? ' st-pricing-featured' : '') . '">';
      if ($isFeatured && $badge) {
        $html .= '<span class="st-pricing-badge" data-horizontal-align="left" data-vertical-align="top" style="top:0;right:0;left:0;padding:5px 11px;border-radius:0;line-height:1.3;">' . esc_html($this->environment->translate('Most popular')) . '</span>';
      }
      $html .= '<header class="st-pricing-header"><h3>' . esc_html($plan['name']) . '</h3><p>' . esc_html($plan['for']) . '</p></header>';
      $html .= '<div class="st-pricing-price"><strong>$' . esc_html(number_format((float) $plan[$priceKey])) . '</strong><small>' . esc_html($unit) . '</small></div>';
      $html .= '<ul class="st-pricing-features">' . $features . '</ul>';
      $html .= '<footer class="st-pricing-footer"><a class="st-pricing-button" href="#">' . esc_html($plan['button']) . '</a></footer>';
      $html .= '</article>';
    }

    return $html . '</section>';
  }
}
