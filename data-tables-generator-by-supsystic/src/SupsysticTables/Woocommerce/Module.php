<?php

/**
 * WooCommerce catalog support.
 */
class SupsysticTables_Woocommerce_Module extends SupsysticTables_Core_BaseModule
{
  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    $this->renderWoocommerceSection();
    $this->filterTableAddonData();
    $this->filterWoocommerceContentTemplate();
    $this->registerWooFrontendColumnStyles();
    $this->registerWooFrontendSortingConfig();
    $this->registerWooFrontendDesignStyles();
    $this->registerWooFrontendFeatureGuards();

    if (!$this->isWooCommercePluginActivated()) {
      return;
    }

    $this->loadAssets();

  }

  /**
   * Runs the callbacks after the table editor tabs rendered.
   */
  private function renderWoocommerceSection()
  {
    $dispatcher = $this->getEnvironment()->getDispatcher();

    $dispatcher->on('tabs_rendered', [$this, 'afterTabsRendered']);
    $dispatcher->on('tabs_content_rendered', [$this, 'afterTabsContentRendered']);
  }

  private function filterTableAddonData()
  {
    $this->getEnvironment()
      ->getDispatcher()
      ->on('table-addon-data', [$this, 'renderTableAddonData']);
  }

  private function filterWoocommerceContentTemplate()
  {
    $dispatcher = $this->getEnvironment()->getDispatcher();
    $dispatcher->on('woocommerce_tabs_content_template', [$this, 'onWoocommerceTabsContentTemplate']);
    $dispatcher->on('woocommerce_tabs_content_data', [$this, 'onWoocommerceTabsContentData']);
  }

  private function registerWooFrontendColumnStyles()
  {
    $this->getEnvironment()
      ->getDispatcher()
      ->on('before_table_render', [$this, 'applyWooColumnMaxWidths']);
  }

  private function registerWooFrontendSortingConfig()
  {
    $this->getEnvironment()
      ->getDispatcher()
      ->on('before_table_render', [$this, 'applyWooDisabledSorting']);
  }

  private function registerWooFrontendDesignStyles()
  {
    $this->getEnvironment()
      ->getDispatcher()
      ->on('before_table_render', [$this, 'applyWooDesignStyles']);
  }

  private function registerWooFrontendFeatureGuards()
  {
    $this->getEnvironment()
      ->getDispatcher()
      ->on('before_table_render', [$this, 'disableUnavailableWooFeatures']);
  }

  /**
   * Renders the WooCommerce tab.
   */
  public function afterTabsRendered($table = null)
  {
    if (!$this->isWooProductTable($table)) {
      return;
    }

    $this->getEnvironment()->getTwig()->display('@woocommerce/partials/tab.twig', ['table' => $table]);
  }

  /**
   * Renders the WooCommerce tab content.
   * @param \stdClass $table Current table
   */
  public function afterTabsContentRendered($table)
  {
    if (!$this->isWooProductTable($table)) {
      return;
    }

    $dispatcher = $this->getEnvironment()->getDispatcher();
    $twig = $this->getEnvironment()->getTwig();

    $twig->display($dispatcher->apply('woocommerce_tabs_content_template', ['@woocommerce/partials/tabContent.twig']), $dispatcher->apply('woocommerce_tabs_content_data', [['table' => $table]]));
  }

  public function renderTableAddonData($table)
  {
    if (!$this->isWooCommercePluginActivated() || !$this->isWooProductTable($table)) {
      return;
    }
    $this->normalizeWooSettings($table);

    $filters = [];
    $model = $this->getModelsFactory()->get('Wootables', 'woocommerce');

    if ($this->getEnvironment()->isPro() && !empty($table->woo_settings['woocommerce']['filter_attribute']) && $table->woo_settings['woocommerce']['filter_attribute'] === 'on') {
      $attributeFilters = $model->getAttributeFilterData($table->id, $table->woo_settings);
      if ($attributeFilters !== false) {
        $filters['attributes'] = $attributeFilters;
      }
    }

    if (
      $this->getEnvironment()->isPro()
      && !empty($table->woo_settings['woocommerce']['filter_category_list'])
      && $table->woo_settings['woocommerce']['filter_category_list'] === 'on'
    ) {
      $categoryFilter = $model->getCategoryFilterData($table->woo_settings);
      if ($categoryFilter !== false) {
        $filters['categories'] = $categoryFilter;
      }
    }

    if (
      $this->getEnvironment()->isPro()
      && empty($table->woo_settings['woocommerce']['hide_out_of_stock'])
      && !empty($table->woo_settings['woocommerce']['filter_stock_status'])
      && $table->woo_settings['woocommerce']['filter_stock_status'] === 'on'
    ) {
      $stockFilter = $model->getStockStatusFilterData($table->woo_settings);
      if ($stockFilter !== false) {
        $filters['stock_status'] = $stockFilter;
      }
    }

    if ($this->getEnvironment()->isPro()) {
      $priceFilter = $model->getPriceFilterData($table->woo_settings);
      if ($priceFilter !== false) {
        $filters['price'] = $priceFilter;
      }
    }

    $this->getEnvironment()
      ->getTwig()
      ->display('@woocommerce/partials/shortcode.twig', ['table' => $table, 'filters' => $filters, 'checkoutUrl' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '']);
  }

  public function applyWooColumnMaxWidths($table)
  {
    if (!$this->isWooProductTable($table) || !$this->getEnvironment()->isPro()) {
      return $table;
    }

    $this->normalizeWooSettings($table);

    $orders = $this->getWooColumnOrder($table);
    if (empty($orders)) {
      return $table;
    }

    $wrapperId = $this->getWooFrontendViewId($table);
    $tableId = !empty($table->id) ? absint($table->id) : 0;
    if ($wrapperId === '' || $tableId < 1) {
      return $table;
    }

    $styles = [];
    $alignStyles = [];
    $verticalAlignStyles = [];
    $hiddenStyles = [];
    foreach ($orders as $index => $column) {
      if (!is_array($column)) {
        continue;
      }

      $position = $index + 1;
      if (!empty($column['hide_column'])) {
        $hiddenStyles[] = sprintf(
          '#supsystic-table-%1$s table[data-id="%2$d"] tr > th:nth-child(%3$d), #supsystic-table-%1$s table[data-id="%2$d"] tr > td:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d tr > th:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d tr > td:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] tr > th:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] tr > td:nth-child(%3$d) { display: none !important; visibility: hidden !important; }',
          $wrapperId,
          $tableId,
          $position
        );
      }

      $maxWidth = !empty($column['max_width']) ? absint($column['max_width']) : 0;
      if ($maxWidth > 0) {
        $styles[] = sprintf(
          '#supsystic-table-%1$s table[data-id="%2$d"] tr > th:nth-child(%3$d), #supsystic-table-%1$s table[data-id="%2$d"] tr > td:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d tr > th:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d tr > td:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] tr > th:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] tr > td:nth-child(%3$d) { width: %4$dpx; max-width: %4$dpx; overflow: hidden; white-space: normal !important; overflow-wrap: anywhere; word-break: break-word; }',
          $wrapperId,
          $tableId,
          $position,
          $maxWidth
        );
      }

      $textAlign = !empty($column['text_align']) && in_array($column['text_align'], ['left', 'center', 'right'], true) ? $column['text_align'] : '';
      if ($textAlign !== '') {
        $alignStyles[] = sprintf(
          '#supsystic-table-%1$s table[data-id="%2$d"] thead tr > th:nth-child(%3$d), #supsystic-table-%1$s table[data-id="%2$d"] thead tr > td:nth-child(%3$d), #supsystic-table-%1$s table[data-id="%2$d"] tbody tr > th:nth-child(%3$d), #supsystic-table-%1$s table[data-id="%2$d"] tbody tr > td:nth-child(%3$d), #supsystic-table-%1$s table[data-id="%2$d"] tfoot tr > th:nth-child(%3$d), #supsystic-table-%1$s table[data-id="%2$d"] tfoot tr > td:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d thead tr > th:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d thead tr > td:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d tbody tr > th:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d tbody tr > td:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d tfoot tr > th:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d tfoot tr > td:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] thead tr > th:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] thead tr > td:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] tbody tr > th:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] tbody tr > td:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] tfoot tr > th:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] tfoot tr > td:nth-child(%3$d) { text-align: %4$s !important; }',
          $wrapperId,
          $tableId,
          $position,
          $textAlign
        );
      }

      $verticalAlign = !empty($column['vertical_align']) && in_array($column['vertical_align'], ['top', 'middle', 'bottom'], true) ? $column['vertical_align'] : '';
      if ($verticalAlign !== '') {
        $verticalAlignStyles[] = sprintf(
          '#supsystic-table-%1$s table[data-id="%2$d"] tbody tr > td:nth-child(%3$d), #supsystic-table-%1$s table#supsystic-table-%2$d tbody tr > td:nth-child(%3$d), #supsystic-table-%1$s table[data-view-id="%1$s"] tbody tr > td:nth-child(%3$d) { vertical-align: %4$s !important; }',
          $wrapperId,
          $tableId,
          $position,
          $verticalAlign
        );
      }
    }

    if (empty($styles) && empty($alignStyles) && empty($verticalAlignStyles) && empty($hiddenStyles)) {
      return $table;
    }

    if (empty($table->meta) || !is_array($table->meta)) {
      $table->meta = [];
    }

    $markerStart = '/* woo-column-max-width:start */';
    $markerEnd = '/* woo-column-max-width:end */';
    $alignMarkerStart = '/* woo-column-text-align:start */';
    $alignMarkerEnd = '/* woo-column-text-align:end */';
    $verticalAlignMarkerStart = '/* woo-column-vertical-align:start */';
    $verticalAlignMarkerEnd = '/* woo-column-vertical-align:end */';
    $hiddenMarkerStart = '/* woo-hidden-columns:start */';
    $hiddenMarkerEnd = '/* woo-hidden-columns:end */';
    $existingCss = isset($table->meta['css']) ? (string) $table->meta['css'] : '';
    $existingCss = preg_replace('/\/\* woo-column-max-width:start \*\/.*?\/\* woo-column-max-width:end \*\/\s*/s', '', $existingCss);
    $existingCss = preg_replace('/\/\* woo-column-text-align:start \*\/.*?\/\* woo-column-text-align:end \*\/\s*/s', '', $existingCss);
    $existingCss = preg_replace('/\/\* woo-column-vertical-align:start \*\/.*?\/\* woo-column-vertical-align:end \*\/\s*/s', '', $existingCss);
    $existingCss = preg_replace('/\/\* woo-hidden-columns:start \*\/.*?\/\* woo-hidden-columns:end \*\/\s*/s', '', $existingCss);

    $cssParts = [$existingCss];
    if (!empty($styles)) {
      $cssParts[] = $markerStart . "\n" . implode("\n", $styles) . "\n" . $markerEnd;
    }
    if (!empty($alignStyles)) {
      $cssParts[] = $alignMarkerStart . "\n" . implode("\n", $alignStyles) . "\n" . $alignMarkerEnd;
    }
    if (!empty($verticalAlignStyles)) {
      $cssParts[] = $verticalAlignMarkerStart . "\n" . implode("\n", $verticalAlignStyles) . "\n" . $verticalAlignMarkerEnd;
    }
    if (!empty($hiddenStyles)) {
      $cssParts[] = $hiddenMarkerStart . "\n" . implode("\n", $hiddenStyles) . "\n" . $hiddenMarkerEnd;
    }

    $table->meta['css'] = trim(implode("\n", array_filter($cssParts)));
    return $table;
  }

  public function applyWooDisabledSorting($table)
  {
    if (!$this->isWooProductTable($table) || !$this->getEnvironment()->isPro()) {
      return $table;
    }

    $this->normalizeWooSettings($table);

    $orders = $this->getWooColumnOrder($table);
    if (empty($orders)) {
      return $table;
    }

    $disabledColumns = [];
    foreach ($orders as $index => $column) {
      if (!is_array($column) || empty($column['disable_sorting'])) {
        continue;
      }

      $disabledColumns[] = (int) $index;
    }

    if (empty($disabledColumns)) {
      return $table;
    }

    if (empty($table->meta) || !is_array($table->meta)) {
      $table->meta = [];
    }

    $existingDisabled = [];
    if (!empty($table->meta['columnsDisableSorting']) && is_array($table->meta['columnsDisableSorting'])) {
      $existingDisabled = $table->meta['columnsDisableSorting'];
    }

    $table->meta['columnsDisableSorting'] = array_values(array_unique(array_map('intval', array_merge($existingDisabled, $disabledColumns))));

    return $table;
  }

  public function applyWooDesignStyles($table)
  {
    if (!$this->isWooProductTable($table) || !$this->getEnvironment()->isPro()) {
      return $table;
    }

    $this->normalizeWooSettings($table);

    $design = !empty($table->woo_settings['woocommerce']['design']) && is_array($table->woo_settings['woocommerce']['design'])
      ? $table->woo_settings['woocommerce']['design']
      : [];

    if (empty($design['enabled'])) {
      return $table;
    }

    $wrapperId = $this->getWooFrontendViewId($table);
    $tableId = !empty($table->id) ? absint($table->id) : 0;
    if ($wrapperId === '' || $tableId < 1) {
      return $table;
    }

    $tableSelectors = [
      sprintf('#supsystic-table-%1$s table[data-id="%2$d"]', $wrapperId, $tableId),
      sprintf('#supsystic-table-%1$s table#supsystic-table-%2$d', $wrapperId, $tableId),
      sprintf('#supsystic-table-%1$s table[data-view-id="%1$s"]', $wrapperId),
    ];
    $wrapSelector = sprintf('#supsystic-table-%1$s', $wrapperId);
    $styles = [];
    $borderColor = !empty($design['border_color']) ? $design['border_color'] : '';
    $paginationBorder = $borderColor !== '' ? '1px solid ' . $borderColor : '';
    $headerSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead th'),
      $this->buildWooSelectorList($tableSelectors, ' thead td'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td'),
    ];
    $sortingBaseSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_asc'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_desc'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_asc_disabled'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_desc_disabled'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_asc'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_desc'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_asc_disabled'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_desc_disabled'),
    ];
    $sortingBeforeSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting::before'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_asc::before'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_desc::before'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_asc_disabled::before'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_desc_disabled::before'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting::before'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_asc::before'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_desc::before'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_asc_disabled::before'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_desc_disabled::before'),
    ];
    $sortingAfterSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting::after'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_asc::after'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_desc::after'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_asc_disabled::after'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_desc_disabled::after'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting::after'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_asc::after'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_desc::after'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_asc_disabled::after'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_desc_disabled::after'),
    ];
    $sortingAscBeforeSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_asc::before'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_asc::before'),
    ];
    $sortingAscAfterSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_asc::after'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_asc::after'),
    ];
    $sortingDescBeforeSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_desc::before'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_desc::before'),
    ];
    $sortingDescAfterSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_desc::after'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_desc::after'),
    ];
    $sortingDisabledBeforeSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_asc_disabled::before'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_desc_disabled::before'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_asc_disabled::before'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_desc_disabled::before'),
    ];
    $sortingDisabledAfterSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_asc_disabled::after'),
      $this->buildWooSelectorList($tableSelectors, ' thead .sorting_desc_disabled::after'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_asc_disabled::after'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot .sorting_desc_disabled::after'),
    ];
    $headerInputSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead th input'),
      $this->buildWooSelectorList($tableSelectors, ' thead td input'),
      $this->buildWooSelectorList($tableSelectors, ' thead th textarea'),
      $this->buildWooSelectorList($tableSelectors, ' thead td textarea'),
      $this->buildWooSelectorList($tableSelectors, ' thead th .stbColumnSearchField input.search-column'),
      $this->buildWooSelectorList($tableSelectors, ' thead td .stbColumnSearchField input.search-column'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th input'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td input'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th textarea'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td textarea'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th .stbColumnSearchField input.search-column'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td .stbColumnSearchField input.search-column'),
    ];
    $headerInputPlaceholderSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead th input::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td input::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead th textarea::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td textarea::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead th .stbColumnSearchField input.search-column::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td .stbColumnSearchField input.search-column::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead th input::-webkit-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td input::-webkit-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead th textarea::-webkit-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td textarea::-webkit-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead th input::-moz-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td input::-moz-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead th textarea::-moz-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td textarea::-moz-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead th input:-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td input:-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead th textarea:-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td textarea:-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead th input::-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td input::-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead th textarea::-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' thead td textarea::-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th input::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td input::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th textarea::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td textarea::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th .stbColumnSearchField input.search-column::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td .stbColumnSearchField input.search-column::placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th input::-webkit-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td input::-webkit-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th textarea::-webkit-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td textarea::-webkit-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th input::-moz-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td input::-moz-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th textarea::-moz-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td textarea::-moz-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th input:-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td input:-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th textarea:-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td textarea:-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th input::-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td input::-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th textarea::-ms-input-placeholder'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td textarea::-ms-input-placeholder'),
    ];
    $headerSelectSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' thead th select'),
      $this->buildWooSelectorList($tableSelectors, ' thead td select'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot th select'),
      $this->buildWooSelectorList($tableSelectors, ' tfoot td select'),
    ];
    $bodySelectors = [
      $this->buildWooSelectorList($tableSelectors, ' tbody td'),
      $this->buildWooSelectorList($tableSelectors, ' tbody th'),
    ];
    $bodyTextSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' tbody td a'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td span'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td ins'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td del'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td bdi'),
    ];
    $stripeSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) > td'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) > th'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > th'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td table td'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td table th'),
    ];
    $stripeTextSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) > td a'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) > td span'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) > td ins'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) > td del'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) > td bdi'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td a'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td span'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td ins'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td del'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td bdi'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td table td a'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td table td span'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td table td ins'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td table td del'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr.odd:not(.child) + tr.child > td table td bdi'),
    ];
    $hoverSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' tbody tr:hover > td'),
      $this->buildWooSelectorList($tableSelectors, ' tbody tr:hover > th'),
    ];
    $borderSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' td'),
      $this->buildWooSelectorList($tableSelectors, ' th'),
    ];
    $priceTextSelectors = [
      $this->buildWooSelectorList($tableSelectors, ' tbody td .price'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td .price *'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td .woocommerce-Price-amount'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td .woocommerce-Price-amount *'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td .woocommerce-Price-currencySymbol'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td .woocommerce-Price-amount + span[aria-hidden="true"]'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td .stVarPrice'),
      $this->buildWooSelectorList($tableSelectors, ' tbody td .stVarPrice *'),
    ];
    $priceFilterThumbStyle = !empty($design['price_filter_thumb_style']) ? $design['price_filter_thumb_style'] : 'circle';

    $headerRules = $this->buildWooDesignRule([
      'color' => isset($design['header_text']) ? $design['header_text'] : '',
      'background-color' => isset($design['header_bg']) ? $design['header_bg'] : '',
      'font-size' => !empty($design['header_font_size']) ? absint($design['header_font_size']) . 'px' : '',
      'font-weight' => !empty($design['header_font_weight']) ? absint($design['header_font_weight']) : '',
    ]);
    if ($headerRules !== '') {
      $styles[] = implode(',', array_filter($headerSelectors)) . '{' . $headerRules . '}';
    }

    $sortingBaseRules = $this->buildWooDesignRule([
      'position' => 'relative',
      'padding-right' => '28px',
      'background-image' => 'none',
      'background-repeat' => 'no-repeat',
    ]);
    if ($sortingBaseRules !== '') {
      $styles[] = implode(',', array_filter($sortingBaseSelectors)) . '{' . $sortingBaseRules . '}';
    }

    $sortingPseudoRules = $this->buildWooDesignRule([
      'content' => '""',
      'position' => 'absolute',
      'right' => '10px',
      'transform' => 'translate(0px, -50%)',
      'border-left' => '4px solid transparent',
      'border-right' => '4px solid transparent',
      'opacity' => '0.35',
      'pointer-events' => 'none',
    ]);
    if ($sortingPseudoRules !== '') {
      $styles[] = implode(',', array_filter($sortingBeforeSelectors)) . '{' . $sortingPseudoRules . ';top:40%!important;border-bottom:6px solid currentColor!important;}';
      $styles[] = implode(',', array_filter($sortingAfterSelectors)) . '{' . $sortingPseudoRules . ';top:60%!important;border-top:6px solid currentColor!important;}';
    }

    if (!empty(array_filter($sortingAscBeforeSelectors))) {
      $styles[] = implode(',', array_filter($sortingAscBeforeSelectors)) . '{opacity:1!important;}';
    }
    if (!empty(array_filter($sortingAscAfterSelectors))) {
      $styles[] = implode(',', array_filter($sortingAscAfterSelectors)) . '{opacity:0.18!important;}';
    }
    if (!empty(array_filter($sortingDescBeforeSelectors))) {
      $styles[] = implode(',', array_filter($sortingDescBeforeSelectors)) . '{opacity:0.18!important;}';
    }
    if (!empty(array_filter($sortingDescAfterSelectors))) {
      $styles[] = implode(',', array_filter($sortingDescAfterSelectors)) . '{opacity:1!important;}';
    }
    if (!empty(array_filter($sortingDisabledBeforeSelectors))) {
      $styles[] = implode(',', array_filter($sortingDisabledBeforeSelectors)) . '{opacity:0.12!important;}';
    }
    if (!empty(array_filter($sortingDisabledAfterSelectors))) {
      $styles[] = implode(',', array_filter($sortingDisabledAfterSelectors)) . '{opacity:0.12!important;}';
    }

    $bodyRules = $this->buildWooDesignRule([
      'color' => isset($design['body_text']) ? $design['body_text'] : '',
      'background-color' => isset($design['body_bg']) ? $design['body_bg'] : '',
      'font-size' => !empty($design['body_font_size']) ? absint($design['body_font_size']) . 'px' : '',
      'font-weight' => !empty($design['body_font_weight']) ? absint($design['body_font_weight']) : '',
    ]);
    if ($bodyRules !== '') {
      $styles[] = implode(',', array_filter($bodySelectors)) . '{' . $bodyRules . '}';
    }

    $bodyTextRules = $this->buildWooDesignRule([
      'color' => isset($design['body_text']) ? $design['body_text'] : '',
      'font-size' => !empty($design['body_font_size']) ? absint($design['body_font_size']) . 'px' : '',
      'font-weight' => !empty($design['body_font_weight']) ? absint($design['body_font_weight']) : '',
    ]);
    if ($bodyTextRules !== '') {
      $styles[] = implode(',', array_filter($bodyTextSelectors)) . '{' . $bodyTextRules . '}';
    }

    $inputRules = $this->buildWooDesignRule([
      'color' => isset($design['input_text']) ? $design['input_text'] : '',
      'background-color' => isset($design['input_bg']) ? $design['input_bg'] : '',
      'border-color' => isset($design['border_color']) ? $design['border_color'] : '',
    ]);
    if ($inputRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper .dataTables_filter input,' . $wrapSelector . ' .stAddToCartWrapper .quantity input,' . $wrapSelector . ' .stAddToCartWrapper .quantity .qty,' . $wrapSelector . ' .stbWooColumnsToggleSearchInput,' . $wrapSelector . ' .stb-before-woo .stWooPriceInput{' . $inputRules . '}';
      $styles[] = implode(',', array_filter($headerInputSelectors)) . '{' . $inputRules . 'font-size:' . (!empty($design['body_font_size']) ? absint($design['body_font_size']) . 'px' : '14px') . ';font-weight:' . (!empty($design['body_font_weight']) ? absint($design['body_font_weight']) : '400') . ';box-shadow:none;}';
    }

    $inputPlaceholderRules = $this->buildWooDesignRule([
      'color' => isset($design['input_text']) ? $design['input_text'] : '',
      'opacity' => '1',
    ]);
    if ($inputPlaceholderRules !== '') {
      $styles[] = implode(',', array_filter($headerInputPlaceholderSelectors)) . '{' . $inputPlaceholderRules . '}';
      $styles[] = $wrapSelector . ' .stbWooColumnsToggleSearchInput::placeholder,' . $wrapSelector . ' .stbWooColumnsToggleSearchInput::-webkit-input-placeholder,' . $wrapSelector . ' .stbWooColumnsToggleSearchInput::-moz-placeholder,' . $wrapSelector . ' .stbWooColumnsToggleSearchInput:-ms-input-placeholder,' . $wrapSelector . ' .stbWooColumnsToggleSearchInput::-ms-input-placeholder,' . $wrapSelector . ' .stb-before-woo .stWooPriceInput::placeholder,' . $wrapSelector . ' .stb-before-woo .stWooPriceInput::-webkit-input-placeholder,' . $wrapSelector . ' .stb-before-woo .stWooPriceInput::-moz-placeholder,' . $wrapSelector . ' .stb-before-woo .stWooPriceInput:-ms-input-placeholder,' . $wrapSelector . ' .stb-before-woo .stWooPriceInput::-ms-input-placeholder{' . $inputPlaceholderRules . '}';
    }

    $selectRules = $this->buildWooDesignRule([
      'color' => isset($design['select_text']) ? $design['select_text'] : '',
      'background-color' => isset($design['select_bg']) ? $design['select_bg'] : '',
      'border-color' => isset($design['border_color']) ? $design['border_color'] : '',
    ]);
    if ($selectRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper .dataTables_length select,' . $wrapSelector . ' .stb-before-woo select,' . $wrapSelector . ' .stVarAttributes select{' . $selectRules . '}';
      $styles[] = implode(',', array_filter($headerSelectSelectors)) . '{' . $selectRules . 'font-size:' . (!empty($design['body_font_size']) ? absint($design['body_font_size']) . 'px' : '14px') . ';font-weight:' . (!empty($design['body_font_weight']) ? absint($design['body_font_weight']) : '400') . ';box-shadow:none;}';
    }

    $lengthRules = $this->buildWooDesignRule([
      'color' => isset($design['length_color']) ? $design['length_color'] : '',
      'font-size' => !empty($design['length_font_size']) ? absint($design['length_font_size']) . 'px' : '',
      'font-weight' => !empty($design['length_font_weight']) ? absint($design['length_font_weight']) : '',
    ]);
    if ($lengthRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper div.dataTables_length label,' . $wrapSelector . ' .dataTables_wrapper div.dataTables_length label select{' . $lengthRules . '}';
    }

    $infoRules = $this->buildWooDesignRule([
      'color' => isset($design['info_color']) ? $design['info_color'] : '',
      'font-size' => !empty($design['info_font_size']) ? absint($design['info_font_size']) . 'px' : '',
      'font-weight' => !empty($design['info_font_weight']) ? absint($design['info_font_weight']) : '',
    ]);
    if ($infoRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper div.dataTables_info{' . $infoRules . '}';
    }

    $descriptionRules = $this->buildWooDesignRule([
      'color' => isset($design['description_color']) ? $design['description_color'] : '',
      'font-size' => !empty($design['description_font_size']) ? absint($design['description_font_size']) . 'px' : '',
      'font-weight' => !empty($design['description_font_weight']) ? absint($design['description_font_weight']) : '',
    ]);
    if ($descriptionRules !== '') {
      $styles[] = $wrapSelector . ' .table-desc{' . $descriptionRules . '}';
    }

    $signatureRules = $this->buildWooDesignRule([
      'color' => isset($design['signature_color']) ? $design['signature_color'] : '',
      'font-size' => !empty($design['signature_font_size']) ? absint($design['signature_font_size']) . 'px' : '',
      'font-weight' => !empty($design['signature_font_weight']) ? absint($design['signature_font_weight']) : '',
    ]);
    if ($signatureRules !== '') {
      $styles[] = $wrapSelector . ' .table-signature{' . $signatureRules . '}';
    }

    $paginationRules = $this->buildWooDesignRule([
      'color' => isset($design['body_text']) ? $design['body_text'] : '',
      'background' => isset($design['body_bg']) ? $design['body_bg'] : '',
      'background-image' => 'none',
      'border' => $paginationBorder,
      'border-radius' => '999px',
      'box-shadow' => 'none',
      'text-shadow' => 'none',
      'transition' => 'all 0.2s ease',
    ]);
    if ($paginationRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper .dataTables_paginate .paginate_button{' . $paginationRules . '}';
    }

    $paginationCurrentRules = $this->buildWooDesignRule([
      'color' => isset($design['button_text']) ? $design['button_text'] : '',
      'background' => isset($design['button_bg']) ? $design['button_bg'] : '',
      'background-image' => 'none',
      'border' => !empty($design['button_bg']) ? '1px solid ' . $design['button_bg'] : '',
      'box-shadow' => 'none',
      'text-shadow' => 'none',
      'font-weight' => !empty($design['button_font_weight']) ? absint($design['button_font_weight']) : '',
    ]);
    if ($paginationCurrentRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper .dataTables_paginate .paginate_button.current,' . $wrapSelector . ' .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover{' . $paginationCurrentRules . '}';
    }

    $paginationHoverRules = $this->buildWooDesignRule([
      'color' => isset($design['button_hover_text']) ? $design['button_hover_text'] : '',
      'background' => isset($design['button_hover_bg']) ? $design['button_hover_bg'] : '',
      'background-image' => 'none',
      'border' => !empty($design['button_hover_bg']) ? '1px solid ' . $design['button_hover_bg'] : '',
      'box-shadow' => 'none',
      'text-shadow' => 'none',
    ]);
    if ($paginationHoverRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper .dataTables_paginate .paginate_button:hover:not(.disabled):not(.current),' . $wrapSelector . ' .dataTables_wrapper .dataTables_paginate .paginate_button:focus:not(.disabled):not(.current){' . $paginationHoverRules . '}';
    }

    $paginationDisabledRules = $this->buildWooDesignRule([
      'background-image' => 'none',
      'text-shadow' => 'none',
      'opacity' => '0.45',
      'cursor' => 'default',
    ]);
    if ($paginationDisabledRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,' . $wrapSelector . ' .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover,' . $wrapSelector . ' .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:focus{' . $paginationDisabledRules . '}';
    }

    $buttonRules = $this->buildWooDesignRule([
      'color' => isset($design['button_text']) ? $design['button_text'] : '',
      'background-color' => isset($design['button_bg']) ? $design['button_bg'] : '',
      'border-color' => isset($design['button_bg']) ? $design['button_bg'] : '',
      'font-size' => !empty($design['button_font_size']) ? absint($design['button_font_size']) . 'px' : '',
      'font-weight' => !empty($design['button_font_weight']) ? absint($design['button_font_weight']) : '',
    ]);
    if ($buttonRules !== '') {
      $styles[] = $wrapSelector . ' .stAddToCartWrapper .button.stAddToCart,' . $wrapSelector . ' .stAddToCartWrapper .stAddToCart,' . $wrapSelector . ' .stAddToCartButWrapp .button.stAddToCart,' . $wrapSelector . ' .stAddToCartButWrapp .stAddToCart,' . $wrapSelector . ' .stb-before-woo .stAddMultyButton.button,' . $wrapSelector . ' .stb-before-woo .stAddMultyButton,' . $wrapSelector . ' .stb-before-woo .stbWooColumnsToggleButton,' . $wrapSelector . ' .stAddToCartWrapper .added_to_cart,' . $wrapSelector . ' .stAddToCartWrapper .added_to_cart.wc-forward{' . $buttonRules . '}';
    }

    $buttonHoverRules = $this->buildWooDesignRule([
      'color' => isset($design['button_hover_text']) ? $design['button_hover_text'] : '',
      'background-color' => isset($design['button_hover_bg']) ? $design['button_hover_bg'] : '',
      'border-color' => isset($design['button_hover_bg']) ? $design['button_hover_bg'] : '',
    ]);
    if ($buttonHoverRules !== '') {
      $styles[] = $wrapSelector . ' .stAddToCartWrapper .button.stAddToCart:hover,' . $wrapSelector . ' .stAddToCartWrapper .button.stAddToCart:focus,' . $wrapSelector . ' .stAddToCartWrapper .stAddToCart:hover,' . $wrapSelector . ' .stAddToCartWrapper .stAddToCart:focus,' . $wrapSelector . ' .stAddToCartButWrapp .button.stAddToCart:hover,' . $wrapSelector . ' .stAddToCartButWrapp .button.stAddToCart:focus,' . $wrapSelector . ' .stAddToCartButWrapp .stAddToCart:hover,' . $wrapSelector . ' .stAddToCartButWrapp .stAddToCart:focus,' . $wrapSelector . ' .stb-before-woo .stAddMultyButton.button:hover,' . $wrapSelector . ' .stb-before-woo .stAddMultyButton.button:focus,' . $wrapSelector . ' .stb-before-woo .stAddMultyButton:hover,' . $wrapSelector . ' .stb-before-woo .stAddMultyButton:focus,' . $wrapSelector . ' .stb-before-woo .stbWooColumnsToggleButton:hover,' . $wrapSelector . ' .stb-before-woo .stbWooColumnsToggleButton:focus,' . $wrapSelector . ' .stAddToCartWrapper .added_to_cart:hover,' . $wrapSelector . ' .stAddToCartWrapper .added_to_cart:focus,' . $wrapSelector . ' .stAddToCartWrapper .added_to_cart.wc-forward:hover,' . $wrapSelector . ' .stAddToCartWrapper .added_to_cart.wc-forward:focus{' . $buttonHoverRules . '}';
    }

    $checkoutButtonRules = $this->buildWooDesignRule([
      'color' => isset($design['checkout_button_text']) ? $design['checkout_button_text'] : '',
      'background-color' => isset($design['checkout_button_bg']) ? $design['checkout_button_bg'] : '',
      'border-color' => isset($design['checkout_button_text']) ? $design['checkout_button_text'] : '',
      'font-size' => !empty($design['checkout_button_font_size']) ? absint($design['checkout_button_font_size']) . 'px' : '',
      'font-weight' => !empty($design['checkout_button_font_weight']) ? absint($design['checkout_button_font_weight']) : '',
    ]);
    if ($checkoutButtonRules !== '') {
      $styles[] = $wrapSelector . ' .stb-before-woo .stViewCheckoutButton.button,' . $wrapSelector . ' .stb-before-woo .stViewCheckoutButton{' . $checkoutButtonRules . '}';
    }

    $checkoutButtonHoverRules = $this->buildWooDesignRule([
      'color' => isset($design['checkout_button_hover_text']) ? $design['checkout_button_hover_text'] : '',
      'background-color' => isset($design['checkout_button_hover_bg']) ? $design['checkout_button_hover_bg'] : '',
      'border-color' => isset($design['checkout_button_hover_bg']) ? $design['checkout_button_hover_bg'] : '',
    ]);
    if ($checkoutButtonHoverRules !== '') {
      $styles[] = $wrapSelector . ' .stb-before-woo .stViewCheckoutButton.button:hover,' . $wrapSelector . ' .stb-before-woo .stViewCheckoutButton.button:focus,' . $wrapSelector . ' .stb-before-woo .stViewCheckoutButton:hover,' . $wrapSelector . ' .stb-before-woo .stViewCheckoutButton:focus{' . $checkoutButtonHoverRules . '}';
    }

    $resetButtonRules = $this->buildWooDesignRule([
      'color' => isset($design['reset_button_text']) ? $design['reset_button_text'] : '',
      'background-color' => isset($design['reset_button_bg']) ? $design['reset_button_bg'] : '',
      'border-color' => isset($design['reset_button_text']) ? $design['reset_button_text'] : '',
      'font-size' => !empty($design['reset_button_font_size']) ? absint($design['reset_button_font_size']) . 'px' : '',
      'font-weight' => !empty($design['reset_button_font_weight']) ? absint($design['reset_button_font_weight']) : '',
      'background-image' => 'none',
      'text-shadow' => 'none',
      'box-shadow' => 'none',
    ]);
    if ($resetButtonRules !== '') {
      $styles[] = $wrapSelector . ' .stb-before-woo .stbResetWooFilters{' . $resetButtonRules . '}';
    }

    $resetButtonHoverRules = $this->buildWooDesignRule([
      'color' => isset($design['reset_button_hover_text']) ? $design['reset_button_hover_text'] : '',
      'background-color' => isset($design['reset_button_hover_bg']) ? $design['reset_button_hover_bg'] : '',
      'border-color' => isset($design['reset_button_hover_bg']) ? $design['reset_button_hover_bg'] : '',
      'background-image' => 'none',
      'text-shadow' => 'none',
      'box-shadow' => 'none',
    ]);
    if ($resetButtonHoverRules !== '') {
      $styles[] = $wrapSelector . ' .stb-before-woo .stbResetWooFilters:hover,' . $wrapSelector . ' .stb-before-woo .stbResetWooFilters:focus{' . $resetButtonHoverRules . '}';
    }

    if (!empty($table->settings['styling']) && is_array($table->settings['styling']) && !empty($table->settings['styling']['stripe'])) {
      $stripeRules = $this->buildWooDesignRule([
        'color' => isset($design['stripe_text']) ? $design['stripe_text'] : '',
        'background-color' => isset($design['stripe_bg']) ? $design['stripe_bg'] : '',
      ]);
      if ($stripeRules !== '') {
        $styles[] = implode(',', array_filter($stripeSelectors)) . '{' . $stripeRules . '}';
        $styles[] = implode(',', array_filter($stripeTextSelectors)) . '{color:' . $design['stripe_text'] . ';}';
      }
    }

    if (!empty($table->settings['styling']) && is_array($table->settings['styling']) && !empty($table->settings['styling']['hover'])) {
      $hoverRules = $this->buildWooDesignRule([
        'color' => isset($design['hover_text']) ? $design['hover_text'] : '',
        'background-color' => isset($design['hover_bg']) ? $design['hover_bg'] : '',
      ]);
      if ($hoverRules !== '') {
        $styles[] = implode(',', array_filter($hoverSelectors)) . '{' . $hoverRules . '}';
      }
    }

    $priceTextRules = $this->buildWooDesignRule([
      'color' => isset($design['price_text']) ? $design['price_text'] : '',
      'font-size' => !empty($design['price_font_size']) ? absint($design['price_font_size']) . 'px' : '',
      'font-weight' => !empty($design['price_font_weight']) ? absint($design['price_font_weight']) : '',
    ]);
    if ($priceTextRules !== '') {
      $styles[] = implode(',', array_filter($priceTextSelectors)) . '{' . $priceTextRules . '}';
    }

    $priceFilterInputRules = $this->buildWooDesignRule([
      'color' => isset($design['price_filter_input_text']) ? $design['price_filter_input_text'] : '',
      'background-color' => isset($design['price_filter_input_bg']) ? $design['price_filter_input_bg'] : '',
      'border-color' => isset($design['border_color']) ? $design['border_color'] : '',
      'font-size' => !empty($design['price_filter_input_font_size']) ? absint($design['price_filter_input_font_size']) . 'px' : '',
      'font-weight' => !empty($design['price_filter_input_font_weight']) ? absint($design['price_filter_input_font_weight']) : '',
      'box-shadow' => 'none',
    ]);
    if ($priceFilterInputRules !== '') {
      $styles[] = $wrapSelector . ' .stb-before-woo .stWooPriceInput{' . $priceFilterInputRules . '}';
    }

    $priceFilterVarRules = [];
    if (!empty($design['price_filter_track'])) {
      $priceFilterVarRules[] = '--stb-price-track:' . $design['price_filter_track'];
    }
    if (!empty($design['price_filter_fill'])) {
      $priceFilterVarRules[] = '--stb-price-fill:' . $design['price_filter_fill'];
    }
    if (!empty($design['price_filter_thumb'])) {
      $priceFilterVarRules[] = '--stb-price-thumb:' . $design['price_filter_thumb'];
    }
    $priceFilterVarRules[] = '--stb-price-thumb-radius:' . ($priceFilterThumbStyle === 'square' ? '3px' : ($priceFilterThumbStyle === 'round' ? '8px' : '999px'));
    if (!empty($priceFilterVarRules)) {
      $styles[] = $wrapSelector . ' .stb-before-woo .stWooFilterPrice{' . implode(';', $priceFilterVarRules) . '}';
    }

    $borderRules = $this->buildWooDesignRule([
      'border-color' => isset($design['border_color']) ? $design['border_color'] : '',
    ]);
    if ($borderRules !== '') {
      $styles[] = implode(',', array_filter($borderSelectors)) . '{' . $borderRules . '}';
      $styles[] = $wrapSelector . ' .dataTables_wrapper .dataTables_filter input,' . $wrapSelector . ' .dataTables_wrapper .dataTables_length select,' . $wrapSelector . ' .stb-before-woo select,' . $wrapSelector . ' .stVarAttributes select,' . $wrapSelector . ' .stAddToCartWrapper .quantity input,' . $wrapSelector . ' .stAddToCartWrapper .quantity .qty,' . $wrapSelector . ' .stbWooColumnsToggleButton,' . $wrapSelector . ' .stbWooColumnsToggleDropdown,' . $wrapSelector . ' .stbWooColumnsToggleSearchInput,' . $wrapSelector . ' .stbWooColumnsToggleItemInner,' . $wrapSelector . ' .stb-before-woo .stWooPriceInput{' . $borderRules . '}';
      $styles[] = implode(',', array_filter($headerInputSelectors)) . '{' . $borderRules . '}';
      $styles[] = implode(',', array_filter($headerSelectSelectors)) . '{' . $borderRules . '}';
    }

    $metaTextRules = $this->buildWooDesignRule([
      'color' => isset($design['body_text']) ? $design['body_text'] : '',
    ]);
    if ($metaTextRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper .dataTables_info,' . $wrapSelector . ' .dataTables_wrapper .dataTables_length label,' . $wrapSelector . ' .dataTables_wrapper .dataTables_filter label,' . $wrapSelector . ' .stVarAttributes label,' . $wrapSelector . ' .product-type-variable .reset_variations,' . $wrapSelector . ' .stb-before-woo .stWooFilterPriceLabel,' . $wrapSelector . ' .stb-before-woo .stWooPriceDash{' . $metaTextRules . '}';
      $styles[] = $wrapSelector . ' .stbWooColumnsToggleItemLabel{' . $metaTextRules . '}';
    }

    $columnsPanelRules = $this->buildWooDesignRule([
      'background-color' => isset($design['body_bg']) ? $design['body_bg'] : '',
      'border-color' => isset($design['border_color']) ? $design['border_color'] : '',
    ]);
    if ($columnsPanelRules !== '') {
      $styles[] = $wrapSelector . ' .stbWooColumnsToggleDropdown,' . $wrapSelector . ' .stbWooColumnsToggleItemInner{' . $columnsPanelRules . '}';
    }

    $columnsScrollbarVars = [];
    if (!empty($design['border_color'])) {
      $columnsScrollbarVars[] = '--stb-columns-scrollbar-track:' . $design['border_color'];
    }
    if (!empty($design['button_bg'])) {
      $columnsScrollbarVars[] = '--stb-columns-scrollbar-thumb:' . $design['button_bg'];
    }
    if (!empty($design['button_hover_bg'])) {
      $columnsScrollbarVars[] = '--stb-columns-scrollbar-thumb-hover:' . $design['button_hover_bg'];
    }
    if (!empty($design['body_bg'])) {
      $columnsScrollbarVars[] = '--stb-columns-scrollbar-thumb-border:' . $design['body_bg'];
    }
    if (!empty($columnsScrollbarVars)) {
      $styles[] = $wrapSelector . ' .stbWooColumnsToggleList{' . implode(';', $columnsScrollbarVars) . '}';
      $styles[] = $wrapSelector . '{' . str_replace(
        ['--stb-columns-scrollbar-track', '--stb-columns-scrollbar-thumb', '--stb-columns-scrollbar-thumb-hover', '--stb-columns-scrollbar-thumb-border'],
        ['--stb-cell-scrollbar-track', '--stb-cell-scrollbar-thumb', '--stb-cell-scrollbar-thumb-hover', '--stb-cell-scrollbar-thumb-border'],
        implode(';', $columnsScrollbarVars)
      ) . '}';
    }

    $processingRules = $this->buildWooDesignRule([
      'background' => 'transparent',
      'background-image' => 'none',
      'border' => '0',
      'box-shadow' => 'none',
      'width' => 'auto',
      'height' => 'auto',
      'min-width' => '0',
      'margin-left' => '0',
      'margin-top' => '0',
      'padding-top' => '0',
      'transform' => 'translate(-50%, -50%)',
    ]);
    if ($processingRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper .dataTables_processing{' . $processingRules . '}';
    }

    $processingWrapRules = $this->buildWooDesignRule([
      'display' => 'inline-flex',
      'align-items' => 'center',
      'justify-content' => 'center',
      'min-width' => '68px',
      'min-height' => '68px',
      'padding' => '14px',
      'background-color' => isset($design['processing_bg']) ? $design['processing_bg'] : '',
      'border' => $paginationBorder,
      'border-radius' => '16px',
      'box-shadow' => '0 10px 30px rgba(15,23,42,0.08)',
    ]);
    if ($processingWrapRules !== '') {
      $styles[] = $wrapSelector . ' .stb-processing-loader-wrap{' . $processingWrapRules . '}';
    }

    $processingIconRules = $this->buildWooDesignRule([
      'color' => isset($design['processing_color']) ? $design['processing_color'] : '',
      'background-color' => isset($design['processing_color']) ? $design['processing_color'] : '',
    ]);
    if ($processingIconRules !== '') {
      $styles[] = $wrapSelector . ' .stb-processing-loader-wrap .supsystic-table-loader.spinner{' . $processingIconRules . '}';
      $styles[] = $wrapSelector . ' .stb-processing-loader-wrap .supsystic-table-loader:not(.spinner){color:' . $design['processing_color'] . ';}';
    }

    $wooChromeRules = $this->buildWooDesignRule([
      'border-radius' => '8px',
      'transition' => 'all 0.2s ease',
    ]);
    if ($wooChromeRules !== '') {
      $styles[] = $wrapSelector . ' .dataTables_wrapper .dataTables_filter input,' . $wrapSelector . ' .dataTables_wrapper .dataTables_length select,' . $wrapSelector . ' .dataTables_wrapper .dataTables_paginate .paginate_button,' . $wrapSelector . ' .stAddToCartWrapper .quantity input,' . $wrapSelector . ' .stAddToCartWrapper .quantity .qty,' . $wrapSelector . ' .stb-before-woo select,' . $wrapSelector . ' .stVarAttributes select,' . $wrapSelector . ' .stAddToCartWrapper .button.stAddToCart,' . $wrapSelector . ' .stAddToCartButWrapp .button.stAddToCart,' . $wrapSelector . ' .stb-before-woo .stAddMultyButton.button,' . $wrapSelector . ' .stb-before-woo .stViewCheckoutButton.button,' . $wrapSelector . ' .stb-before-woo .stbWooColumnsToggleButton,' . $wrapSelector . ' .stbWooColumnsToggleSearchInput,' . $wrapSelector . ' .stbWooColumnsToggleItemInner,' . $wrapSelector . ' .stAddToCartWrapper .added_to_cart,' . $wrapSelector . ' .stb-before-woo .stWooPriceInput{' . $wooChromeRules . '}';
      $styles[] = implode(',', array_filter($headerInputSelectors)) . '{' . $wooChromeRules . '}';
      $styles[] = implode(',', array_filter($headerSelectSelectors)) . '{' . $wooChromeRules . '}';
    }

    $stockRules = $this->buildWooDesignRule([
      'display' => 'inline-block',
      'padding' => '4px 10px',
      'border-radius' => '999px',
      'border' => $paginationBorder,
      'font-weight' => '600',
      'line-height' => '1.3',
    ]);
    if ($stockRules !== '') {
      $styles[] = $wrapSelector . ' .stock{' . $stockRules . '}';
    }

    $stockInRules = $this->buildWooDesignRule([
      'color' => isset($design['price_text']) ? $design['price_text'] : '',
      'background-color' => isset($design['price_bg']) ? $design['price_bg'] : '',
    ]);
    if ($stockInRules !== '') {
      $styles[] = $wrapSelector . ' .stock.in-stock,' . $wrapSelector . ' .stock.available-on-backorder{' . $stockInRules . '}';
    }

    $stockOutRules = $this->buildWooDesignRule([
      'color' => isset($design['button_hover_text']) ? $design['button_hover_text'] : '',
      'background-color' => isset($design['button_hover_bg']) ? $design['button_hover_bg'] : '',
      'border' => !empty($design['button_hover_bg']) ? '1px solid ' . $design['button_hover_bg'] : '',
    ]);
    if ($stockOutRules !== '') {
      $styles[] = $wrapSelector . ' .stock.out-of-stock{' . $stockOutRules . '}';
    }

    $badgeRules = $this->buildWooDesignRule([
      'color' => isset($design['button_text']) ? $design['button_text'] : '',
      'background-color' => isset($design['button_bg']) ? $design['button_bg'] : '',
      'border-radius' => '999px',
      'font-weight' => '700',
      'box-shadow' => 'none',
    ]);
    if ($badgeRules !== '') {
      $styles[] = $wrapSelector . ' .onsale{' . $badgeRules . '}';
    }

    $noticeRules = $this->buildWooDesignRule([
      'color' => isset($design['body_text']) ? $design['body_text'] : '',
      'background-color' => isset($design['body_bg']) ? $design['body_bg'] : '',
      'border' => $paginationBorder,
      'border-left' => !empty($design['button_bg']) ? '4px solid ' . $design['button_bg'] : '',
      'border-radius' => '10px',
      'box-shadow' => 'none',
    ]);
    if ($noticeRules !== '') {
      $styles[] = $wrapSelector . ' .woocommerce-message,' . $wrapSelector . ' .woocommerce-info,' . $wrapSelector . ' .woocommerce-error{' . $noticeRules . '}';
    }

    $layoutRules = $this->buildWooDesignRule([
      'gap' => '8px',
      'align-items' => 'center',
    ]);
    if ($layoutRules !== '') {
      $styles[] = $wrapSelector . ' .stAddToCartWrapper{' . $layoutRules . '}';
    }

    if (empty($styles)) {
      return $table;
    }

    if (empty($table->meta) || !is_array($table->meta)) {
      $table->meta = [];
    }

    $markerStart = '/* woo-design-styles:start */';
    $markerEnd = '/* woo-design-styles:end */';
    $existingCss = isset($table->meta['css']) ? (string) $table->meta['css'] : '';
    $existingCss = preg_replace('/\/\* woo-design-styles:start \*\/.*?\/\* woo-design-styles:end \*\/\s*/s', '', $existingCss);

    $table->meta['css'] = trim($existingCss . "\n" . $markerStart . "\n" . implode("\n", $styles) . "\n" . $markerEnd);
    return $table;
  }

  public function disableUnavailableWooFeatures($table)
  {
    if (!$this->isWooProductTable($table) || $this->getEnvironment()->isPro() || empty($table->settings) || !is_array($table->settings)) {
      return $table;
    }

    if (!empty($table->settings['features']) && is_array($table->settings['features'])) {
      unset($table->settings['features']['searching']);
      unset($table->settings['features']['showHideColumnsList']);
    }

    unset($table->settings['serverSideProcessing']);

    if (!empty($table->settings['searching']) && is_array($table->settings['searching'])) {
      $table->settings['searching'] = [];
    }

    return $table;
  }

  public function addProductToTables($productId)
  {
    return;
  }

  public function onWoocommerceTabsContentTemplate()
  {
    return '@woocommerce/partials/tabContent.twig';
  }

  public function onWoocommerceTabsContentData($data)
  {
    if (empty($data['table']->woo_settings) || !is_array($data['table']->woo_settings)) {
      $data['table']->woo_settings = ['woocommerce' => []];
    }
    if (empty($data['table']->woo_settings['woocommerce']) || !is_array($data['table']->woo_settings['woocommerce'])) {
      $data['table']->woo_settings['woocommerce'] = [];
    }
    if (empty($data['table']->woo_settings['woocommerce']['filter_attribute_selected']) || !is_array($data['table']->woo_settings['woocommerce']['filter_attribute_selected'])) {
      $data['table']->woo_settings['woocommerce']['filter_attribute_selected'] = [];
    }

    $data['woocommerceActive'] = $this->isWooCommercePluginActivated();
    $data['wooAdvancedAllowed'] = $this->getEnvironment()->isPro();

    if (!$data['wooAdvancedAllowed'] && !empty($data['table']->settings) && is_array($data['table']->settings)) {
      if (!empty($data['table']->settings['features']) && is_array($data['table']->settings['features'])) {
        unset($data['table']->settings['features']['searching']);
        unset($data['table']->settings['features']['showHideColumnsList']);
      }
      unset($data['table']->settings['serverSideProcessing']);
      if (!empty($data['table']->settings['searching']) && is_array($data['table']->settings['searching'])) {
        $data['table']->settings['searching'] = [];
      }
    }

    if (!$data['woocommerceActive']) {
      $data['woocolumns'] = [];
      $data['thumbSize'] = [];
      $data['wooAttributes'] = [];
      $data['wooCategories'] = [];
      return $data;
    }

    $wooColumnsModel = $this->getModelsFactory()->get('Woocolumns', 'woocommerce');
    $wooColumns = $wooColumnsModel->getAllWooColumns();
    if ($wooColumns) {
      $data['woocolumns'] = $wooColumns;
    }

    if (!empty($data['table']->woo_settings['woocommerce']['order'])) {
      $orders = json_decode($data['table']->woo_settings['woocommerce']['order'], true);
      if (is_array($orders)) {
        $newOrder = [];
        foreach ($orders as $order) {
          if (isset($order['id'])) {
            $newOrder[] = $order['id'];
          }
        }
        $data['colNumbOrder'] = $newOrder;
        $data['orders'] = $orders;
      }
    }

    $sizesArr = $this->getImageSizes();
    $sizes = [];
    foreach ($sizesArr as $key => $size) {
      $sizes[$key] = $key . ' ' . $size['width'] . ' x ' . $size['height'];
    }
    $sizes['full'] = $this->getEnvironment()->translate('full size');
    $sizes['set_size'] = $this->getEnvironment()->translate('set size');
    $data['thumbSize'] = $sizes;

    $data['wooAttributes'] = $this->getAllWooAttributeOptions($data['table']);
    $data['wooCategories'] = $this->getModelsFactory()->get('Wootables', 'woocommerce')->getCategoryHierarchy();
    $excludedProductIds = !empty($data['table']->woo_settings['woocommerce']['exclude_productids']) ? array_filter(array_map('absint', explode(',', $data['table']->woo_settings['woocommerce']['exclude_productids']))) : [];
    $data['excludedProducts'] = !empty($excludedProductIds) ? $this->getModelsFactory()->get('Wootables', 'woocommerce')->getProductLookupItemsByIds($excludedProductIds, true) : [];

    return $data;
  }

  private function loadAssets()
  {
    /** @var SupsysticTables_Ui_Module $ui */
    $ui = $this->getEnvironment()->getModule('ui');

    if (null === $ui) {
      $this->getEnvironment()
        ->getDispatcher()
        ->on('after_ui_loaded', [$this, 'afterUiLoaded']);

      return;
    }

    $this->afterUiLoaded($ui);
  }

  /**
   * Loads the assets.
   * @param \SupsysticTables_Ui_Module $ui
   */
  public function afterUiLoaded(SupsysticTables_Ui_Module $ui)
  {
    parent::afterUiLoaded($ui);
    $this->loadingAssets($ui);
  }

  public function loadingAssets($ui)
  {
    $hookName = 'admin_enqueue_scripts';
    $frontendHookName = 'wp_enqueue_scripts';
    $dynamicHookName = is_admin() ? $hookName : $frontendHookName;
    $config = $this->getEnvironment()->getConfig();
    $version = $config->get('plugin_version');
    $prefix = 'tbl-woocommerce-';
    $location = untrailingslashit(plugin_dir_url(__FILE__));

    if ($this->getEnvironment()->isPluginPage() && $this->getEnvironment()->isModule('tables', 'view') && $this->isWooProductTableView()) {
      $ui->add($ui->createStyle($prefix . 'woocommerce.admin.css')->setHookName($hookName)->setExternalSource($location . '/assets/css/woocommerce.admin.css'));
      $ui->add(
        $ui
          ->createScript($prefix . 'woocommerce.admin.js')
          ->setHookName($hookName)
          ->setExternalSource($location . '/assets/js/woocommerce.admin.js')
          ->addDependency('tables-core')
          ->addDependency('supsystic-tables-tables-view-woo-product')
          ->addDependency('jquery-ui-dialog')
          ->addDependency('jquery-ui-sortable')
          ->addDependency('supsystic-tables-datatables-js')
          ->addDependency('supsystic-tables-datatables-responsive-js')
          ->addDependency('supsystic-tables-datetime-moment-js')
          ->setVersion($version),
      );
      $ui->add(
        $ui
          ->createScript($prefix . 'tables.model.woo.js')
          ->setHookName($hookName)
          ->setExternalSource($location . '/assets/js/tables.model.woo.pro.js')
          ->addDependency('supsystic-tables-tables-model')
          ->setVersion($version),
      );
      $ui->add($ui->createScript($prefix . 'multiple-select.js')->setHookName($hookName)->setExternalSource($location . '/assets/js/multiple-select.js')->addDependency('jquery')->setVersion($version));
      $ui->add($ui->createStyle($prefix . 'multiple-select.css')->setHookName($hookName)->setExternalSource($location . '/assets/css/multiple-select.css'));
    }

    $ui->add($ui->createScript('tables.view.woo.js')->setHookName($dynamicHookName)->setExternalSource($location . '/assets/js/tables.view.woo.pro.js')->addDependency('tables-core')->setVersion($version));
    $ui->add($ui->createStyle($prefix . 'woocommerce.frontend.css')->setHookName($dynamicHookName)->setExternalSource($location . '/assets/css/woocommerce.frontend.css'));
  }

  public function getImageSizes()
  {
    global $_wp_additional_image_sizes;

    $sizes = [];

    foreach (get_intermediate_image_sizes() as $_size) {
      if (in_array($_size, ['thumbnail', 'medium', 'medium_large', 'large'], true)) {
        $sizes[$_size]['width'] = get_option("{$_size}_size_w");
        $sizes[$_size]['height'] = get_option("{$_size}_size_h");
      } elseif (isset($_wp_additional_image_sizes[$_size])) {
        $sizes[$_size] = [
          'width' => $_wp_additional_image_sizes[$_size]['width'],
          'height' => $_wp_additional_image_sizes[$_size]['height'],
        ];
      }
    }

    return $sizes;
  }

  public function isWooCommercePluginActivated()
  {
    return class_exists('WooCommerce');
  }

  public function showNoWoocommerceErrorNotice()
  {
    print $this->getTwig()->render('@woocommerce/notice/noWoocommerceErrorNotice.twig');
  }

  /**
   * @return \SupsysticTables_Core_ModelsFactory
   */
  protected function getModelsFactory()
  {
    /** @var SupsysticTables_Core_Module $core */
    $core = $this->getEnvironment()->getModule('core');

    return $core->getModelsFactory();
  }

  private function isWooProductTable($table)
  {
    if (!is_object($table)) {
      return false;
    }
    if (!empty($table->table_type) && $table->table_type === 'woo_product_table') {
      return true;
    }
    if (!empty($table->settings) && is_array($table->settings) && !empty($table->settings['tableType']) && $table->settings['tableType'] === 'woo_product_table') {
      return true;
    }

    if (empty($table->woo_settings) || !is_array($table->woo_settings) || empty($table->woo_settings['woocommerce']) || !is_array($table->woo_settings['woocommerce']) || empty($table->woo_settings['woocommerce']['enable'])) {
      return false;
    }

    return in_array($table->woo_settings['woocommerce']['enable'], ['on', '1', 1, true], true);
  }

  private function normalizeWooSettings($table)
  {
    if (!is_object($table)) {
      return;
    }
    if (empty($table->woo_settings) || !is_array($table->woo_settings)) {
      $table->woo_settings = ['woocommerce' => []];
    }
    if (empty($table->woo_settings['woocommerce']) || !is_array($table->woo_settings['woocommerce'])) {
      $table->woo_settings['woocommerce'] = [];
    }
    if (!empty($table->table_type) && $table->table_type === 'woo_product_table' && empty($table->woo_settings['woocommerce']['enable'])) {
      $table->woo_settings['woocommerce']['enable'] = 'on';
    }
  }

  private function getWooColumnOrder($table)
  {
    if (!is_object($table) || empty($table->woo_settings['woocommerce']['order'])) {
      return [];
    }

    $orders = json_decode($table->woo_settings['woocommerce']['order'], true);

    return is_array($orders) ? $orders : [];
  }

  private function buildWooDesignRule($properties)
  {
    $rules = [];

    foreach ((array) $properties as $property => $value) {
      if (!is_scalar($value)) {
        continue;
      }

      $value = trim((string) $value);
      if ($value === '') {
        continue;
      }

      $rules[] = $property . ':' . $value . '!important';
    }

    return implode(';', $rules);
  }

  private function buildWooSelectorList($selectors, $suffix)
  {
    $selectors = array_filter(array_map('trim', (array) $selectors));
    if (empty($selectors)) {
      return '';
    }

    return implode(',', array_map(function ($selector) use ($suffix) {
      return $selector . $suffix;
    }, $selectors));
  }

  private function getWooFrontendViewId($table)
  {
    if (is_object($table) && !empty($table->view_id)) {
      return preg_replace('/[^A-Za-z0-9_-]/', '', (string) $table->view_id);
    }

    return !empty($table->id) ? (string) absint($table->id) : '';
  }

  private function getAllWooAttributeOptions($table = null)
  {
    $wooSettings = is_object($table) && !empty($table->woo_settings) && is_array($table->woo_settings) ? $table->woo_settings : ['woocommerce' => []];

    return $this->getModelsFactory()->get('Wootables', 'woocommerce')->getAvailableFilterAttributes($wooSettings);
  }

  private function isWooProductTableView()
  {
    if (!is_admin() || !$this->getEnvironment()->isModule('tables', 'view')) {
      return false;
    }

    $tableId = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if (!$tableId) {
      return false;
    }

    global $wpdb;
    $tableName = $wpdb->prefix . $this->getEnvironment()->getConfig()->get('db_prefix') . 'tables';
    $columns = $wpdb->get_col("DESC {$tableName}", 0);
    if (!is_array($columns)) {
      return false;
    }

    $select = [
      in_array('table_type', $columns, true) ? '`table_type`' : "'default' AS `table_type`",
      in_array('woo_settings', $columns, true) ? '`woo_settings`' : 'NULL AS `woo_settings`',
    ];
    $table = $wpdb->get_row($wpdb->prepare('SELECT ' . implode(', ', $select) . " FROM {$tableName} WHERE `id` = %d", $tableId));

    if (!is_object($table)) {
      return false;
    }
    if (!empty($table->table_type) && $table->table_type === 'woo_product_table') {
      return true;
    }

    $wooSettings = !empty($table->woo_settings) ? @unserialize($table->woo_settings, ['allowed_classes' => false]) : [];
    return is_array($wooSettings) && !empty($wooSettings['woocommerce']) && is_array($wooSettings['woocommerce']) && !empty($wooSettings['woocommerce']['enable']) && in_array($wooSettings['woocommerce']['enable'], ['on', '1', 1, true], true);
  }
}
