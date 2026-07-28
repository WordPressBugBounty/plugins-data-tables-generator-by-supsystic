<?php

/**
 * Class SupsysticTables_Woocommerce_Controller
 */
class SupsysticTables_Woocommerce_Controller extends SupsysticTables_Core_BaseController
{
  public function saveWoocommerceSettingsAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      return $this->ajaxError($this->translate('Invalid security token. Please reload the page and try again.'));
    }

    $id = (int) $request->post->get('id');
    $data = $request->post->get('settings');

    if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
      $data = stripslashes($data);
    }
    parse_str($data, $settings);
    $settings = $this->sanitizeSettings($settings);
    $table = $id ? $this->getModel('tables')->getById($id) : null;
    $updateData = ['woo_settings' => serialize($settings)];
    if ($this->isWooProductTable($table)) {
      if (empty($settings['woocommerce']) || !is_array($settings['woocommerce'])) {
        $settings['woocommerce'] = [];
      }
      $settings['woocommerce']['enable'] = 'on';
      $existingTableSettings = $this->getTableSettingsFromTable($table);
      $tableSettings = $this->sanitizeWooTableCommonSettings($request, $existingTableSettings);
      $tableSettings = $this->sanitizeWooTableGeneralSettings($tableSettings);
      $updateData['woo_settings'] = serialize($settings);
      $updateData['settings'] = htmlspecialchars(serialize($tableSettings), ENT_QUOTES);
    }
    $moduleTables = $this->getEnvironment()->getModule('tables');

    try {
      $moduleTables->setIniLimits();
      $this->getModel('tables')->set($id, $updateData);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
    $moduleTables->getController()->cleanCache($id);
    return $this->ajaxSuccess();
  }

  public function getSearchProductsAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      return $this->ajaxError($this->translate('Invalid security token. Please reload the page and try again.'));
    }

    $params = $this->sanitizeProductQueryParams($request->post->all());
    $draw = (int) $request->post->get('draw', 0);
    $return = [
      'draw' => $draw,
      'recordsTotal' => 0,
      'recordsFiltered' => 0,
      'data' => [],
    ];
    $dataArr = $this->getModel('Wootables', 'woocommerce')->getProductsData($params, true);
    if (is_array($dataArr)) {
      $return = array_merge($return, $dataArr);
    }
    return $this->ajaxSuccess($return);
  }

  public function searchExcludeProductsAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      return $this->ajaxError($this->translate('Invalid security token. Please reload the page and try again.'));
    }

    if (!$this->isAdvancedWooAllowed()) {
      return $this->ajaxSuccess(['items' => []]);
    }

    $term = sanitize_text_field(wp_unslash((string) $request->post->get('term', '')));
    $limit = max(1, min(10, absint($request->post->get('limit', 5))));
    $excludeIds = $this->sanitizeIds(explode(',', (string) $request->post->get('exclude_ids', '')));
    $showPrivate = !empty($request->post->get('show_private'));

    $items = $this->getModel('Wootables', 'woocommerce')->getProductLookupItems($term, $limit, true, $showPrivate, $excludeIds);

    return $this->ajaxSuccess(['items' => $items]);
  }

  public function getProductContentAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      return $this->ajaxError($this->translate('Invalid security token. Please reload the page and try again.'));
    }

    $params = $this->sanitizeProductQueryParams($request->post->all());
    $draw = (int) $request->post->get('draw', 0);
    $tableId = (int) $request->post->get('tableid');
    if (empty($tableId)) {
      return false;
    }

    $settings = $this->getModel('tables')->getWooSettings($tableId);
    $wooSettings = $this->maybeUnserializeArray($settings);
    if (empty($wooSettings['woocommerce']) || !is_array($wooSettings['woocommerce'])) {
      $wooSettings['woocommerce'] = [];
    }
    $productIds = $this->getPreviewProductIds($request, $wooSettings['woocommerce']);

    $return = [
      'draw' => $draw,
      'variations' => 0,
      'recordsTotal' => 0,
      'recordsFiltered' => 0,
      'data' => [],
    ];
    if (!empty($productIds)) {
      $dataArr = $this->getModel('Wootables', 'woocommerce')->getProductsData($params, false, $productIds);
      if (is_array($dataArr)) {
        $return = array_merge($return, $dataArr);
      }
    }
    return $this->ajaxSuccess($return);
  }

  public function addProductsAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      return $this->ajaxError($this->translate('Invalid security token. Please reload the page and try again.'));
    }

    $productIdsSelected = !empty($request->post->get('productIdSelected')) ? $this->sanitizeIds($request->post->get('productIdSelected')) : [];
    $id = (int) $request->post->get('tableid');
    if (empty($id) || empty($productIdsSelected)) {
      return false;
    }
    $table = $this->getModel('tables')->getWooSettings($id);
    $tableSettings = $this->maybeUnserializeArray($table);
    $productIdsExits = !empty($tableSettings['woocommerce']['productids']) ? $this->sanitizeIds(explode(',', $tableSettings['woocommerce']['productids'])) : [];
    $productIds = array_unique(array_merge($productIdsExits, $productIdsSelected));
    $productIds = implode(',', $productIds);
    $tableSettings['woocommerce']['productids'] = $productIds;

    try {
      $this->getModel('tables')->set($id, ['woo_settings' => serialize($tableSettings)]);
      return $this->ajaxSuccess(['productIds' => $productIds]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  public function removeProductsAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      return $this->ajaxError($this->translate('Invalid security token. Please reload the page and try again.'));
    }

    $productIdsSelected = !empty($request->post->get('productIdSelected')) ? $this->sanitizeIds($request->post->get('productIdSelected')) : [];
    $id = (int) $request->post->get('tableid');
    if (empty($productIdsSelected) || empty($id)) {
      return false;
    }
    $table = $this->getModel('tables')->getWooSettings($id);
    $tableSettings = $this->maybeUnserializeArray($table);
    $productIdsExits = !empty($tableSettings['woocommerce']['productids']) ? $this->sanitizeIds(explode(',', $tableSettings['woocommerce']['productids'])) : [];
    $productIds = array_diff($productIdsExits, $productIdsSelected);
    $productIds = implode(',', $productIds);
    $tableSettings['woocommerce']['productids'] = $productIds;

    try {
      $this->getModel('tables')->set($id, ['woo_settings' => serialize($tableSettings)]);
      return $this->ajaxSuccess(['productIds' => $productIds]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  public function getRows($id, $mainSettings, $start = false, $length = false, $search = false, $export = false)
  {
    $settings = $this->getModel('tables')->getWooSettings($id);
    $wooTables = $this->getModel('Wootables', 'woocommerce');
    $rows = $wooTables->getRows($id, $this->maybeUnserializeArray($settings), $start, $length, $search, $export);

    if (isset($mainSettings['elements']['head']) && $mainSettings['elements']['head'] == 'on' && isset($mainSettings['headerRowsCount']) && $mainSettings['headerRowsCount'] > 0) {
      $rows = $wooTables->getHeader($rows);
    }

    $data = $wooTables->generateFrontendTableRows($rows);
    return $data;
  }

  public function getRowsByPart($id, $mainSettings, $start = false, $length = false, $search = false, $searchCols = [], $orderCol = false, $orderAsc = true, $searchParams = [])
  {
    $settings = $this->getModel('tables')->getWooSettings($id);
    $wooTables = $this->getModel('Wootables', 'woocommerce');
    $rows = $wooTables->getRowsByPart($id, $this->maybeUnserializeArray($settings), $start, $length, $search, $searchCols, $orderCol, $orderAsc, $searchParams);

    if (!is_array($rows) || empty($rows)) {
      $rows = [
        'draw' => 0,
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => [],
      ];
    } else {
      $rows['data'] = $wooTables->generateFrontendTableRows($rows['data']);
    }

    return $rows;
  }

  public function productsAddToCartAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request) && !$this->_checkNonceFrontend($request)) {
      return $this->ajaxError($this->translate('Invalid security token. Please reload the page and try again.'));
    }

    $selectedProducts = $this->sanitizeCartProducts($request->post->get('selectedProducts'));

    if (!function_exists('wc_notice_count') || !function_exists('wc_print_notices') || !function_exists('WC') || !WC()->cart) {
      return $this->ajaxError(__('WooCommerce cart is not available.', 'supsystic-tables'));
    }

    if (!empty($selectedProducts)) {
      try {
        foreach ($selectedProducts as $selectedProduct) {
          if (!empty($selectedProduct['id']) && !empty($selectedProduct['quantity'])) {
            WC()->cart->add_to_cart($selectedProduct['id'], $selectedProduct['quantity'], $selectedProduct['varId'], $selectedProduct['variation']);
          }
        }
      } catch (Throwable $e) {
        return $this->ajaxError($e->getMessage());
      }
    } else {
      return $this->ajaxError(__('Please select products', 'supsystic-tables'));
    }

    $errors = wc_notice_count() > 0;

    return $this->ajaxSuccess(['errors' => $errors, 'message' => $errors ? wc_print_notices(true) : __('Product(s) added to the cart', 'supsystic-tables')]);
  }

  public function exportSettingsAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      return $this->ajaxError($this->translate('Invalid security token. Please reload the page and try again.'));
    }

    $id = (int) $request->post->get('id');
    if (!$id) {
      return $this->ajaxError($this->translate('Invalid table id.'));
    }

    try {
      $table = $this->getModel('tables')->getById($id);
      if (!$this->isWooProductTable($table)) {
        return $this->ajaxError($this->translate('Settings export is available only for WooCommerce product tables.'));
      }

      $wooSettings = $this->getWooSettingsFromTable($table);
      $payload = [
        'schema' => 'supsystic_tables_woo_product_table_settings',
        'version' => 1,
        'plugin_version' => $this->getEnvironment()->getConfig()->get('plugin_version'),
        'exported_at' => gmdate('c'),
        'table_type' => 'woo_product_table',
        'title' => sanitize_text_field($table->title),
        'settings' => $this->sanitizeWooTableGeneralSettings($this->getTableSettingsFromTable($table)),
        'woo_settings' => $this->removeProductsFromWooSettings($wooSettings),
      ];

      $filename = sanitize_title($table->title);
      if ($filename === '') {
        $filename = 'woo-product-table-' . $id;
      }

      return $this->ajaxSuccess([
        'filename' => $filename . '-woo-settings.json',
        'payload' => $payload,
      ]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  public function importSettingsAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      return $this->ajaxError($this->translate('Invalid security token. Please reload the page and try again.'));
    }

    $id = (int) $request->post->get('id');
    $rawSettings = $request->post->get('settings');
    if (function_exists('wp_unslash')) {
      $rawSettings = wp_unslash($rawSettings);
    }

    if (!$id || !is_string($rawSettings) || $rawSettings === '') {
      return $this->ajaxError($this->translate('Invalid settings file.'));
    }

    $payload = json_decode($rawSettings, true);
    if (!is_array($payload)) {
      return $this->ajaxError($this->translate('Invalid settings JSON.'));
    }

    try {
      $table = $this->getModel('tables')->getById($id);
      if (!$this->isWooProductTable($table)) {
        return $this->ajaxError($this->translate('Settings import is available only for WooCommerce product tables.'));
      }
      if (!empty($payload['table_type']) && $payload['table_type'] !== 'woo_product_table') {
        return $this->ajaxError($this->translate('This settings file is not for a WooCommerce product table.'));
      }

      $wooSettings = !empty($payload['woo_settings']) && is_array($payload['woo_settings']) ? $payload['woo_settings'] : $payload;
      $wooSettings = $this->removeProductsFromWooSettings($wooSettings);
      $generalSettings = !empty($payload['settings']) && is_array($payload['settings']) ? $payload['settings'] : [];

      $this->getModel('tables')->set($id, [
        'settings' => htmlspecialchars(serialize($this->sanitizeWooTableGeneralSettings($generalSettings)), ENT_QUOTES),
        'woo_settings' => serialize($wooSettings),
      ]);

      $this->getEnvironment()->getModule('tables')->getController()->cleanCache($id);

      return $this->ajaxSuccess(['message' => $this->translate('WooCommerce table settings imported successfully.')]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  private function maybeUnserializeArray($value)
  {
    if (!is_string($value) || $value === '') {
      return [];
    }

    $data = @unserialize($value, ['allowed_classes' => false]);

    return is_array($data) ? $data : [];
  }

  private function applyRuntimeWooPreviewSettings(RscDtgs_Http_Request $request, $wooSettings)
  {
    $wooSettings = is_array($wooSettings) ? $wooSettings : [];

    if ($request->post->has('current_productids')) {
      $wooSettings['productids'] = implode(',', $this->sanitizeIds(explode(',', (string) $request->post->get('current_productids', ''))));
    }

    if ($request->post->has('current_exclude_productids')) {
      $wooSettings['exclude_productids'] = implode(',', $this->sanitizeIds(explode(',', (string) $request->post->get('current_exclude_productids', ''))));
    }

    if ($request->post->has('current_auto_categories_enable')) {
      $wooSettings['auto_categories_enable'] = $request->post->get('current_auto_categories_enable') ? 'on' : '';
    }

    if ($request->post->has('current_auto_categories_list')) {
      $wooSettings['auto_categories_list'] = implode(',', $this->sanitizeCategoryIds(explode(',', (string) $request->post->get('current_auto_categories_list', ''))));
    }

    return $wooSettings;
  }

  private function getPreviewProductIds(RscDtgs_Http_Request $request, $wooSettings)
  {
    $wooSettings = is_array($wooSettings) ? $wooSettings : [];

    if ($request->post->has('current_productids')) {
      return $this->sanitizeIds(explode(',', (string) $request->post->get('current_productids', '')));
    }

    return !empty($wooSettings['productids']) ? $this->sanitizeIds(explode(',', (string) $wooSettings['productids'])) : [];
  }

  private function isWooProductTable($table)
  {
    return is_object($table) && !empty($table->table_type) && $table->table_type === 'woo_product_table';
  }

  private function getWooSettingsFromTable($table)
  {
    if (!empty($table->woo_settings) && is_array($table->woo_settings)) {
      return $table->woo_settings;
    }

    if (!empty($table->woo_settings) && is_string($table->woo_settings)) {
      return $this->maybeUnserializeArray($table->woo_settings);
    }

    return ['woocommerce' => []];
  }

  private function removeProductsFromWooSettings($settings)
  {
    $settings = is_array($settings) ? $settings : [];
    if (!isset($settings['woocommerce']) || !is_array($settings['woocommerce'])) {
      $settings = ['woocommerce' => $settings];
    }

    $settings['woocommerce']['productids'] = '';

    return $this->sanitizeSettings($settings);
  }

  private function sanitizeWooTableGeneralSettings($settings)
  {
    if (is_string($settings)) {
      $settings = $this->maybeUnserializeArray($settings);
    }

    $settings = is_array($settings) ? $settings : [];
    $settings = $this->getModel('tables')->sanitize_array($settings);
    $updateMode = !empty($settings['updateMode']) && $settings['updateMode'] === 'auto' && $this->isAdvancedWooAllowed() ? 'auto' : 'manual';
    $designMode = !empty($settings['designMode']) ? sanitize_key($settings['designMode']) : 'default';
    if (!in_array($designMode, ['default', 'custom', 'templates'], true) || ($designMode === 'templates' && !$this->isAdvancedWooAllowed())) {
      $designMode = 'default';
    }

    $settings['tableType'] = 'woo_product_table';
    $settings['sourceType'] = 'woocommerce';
    $settings['updateMode'] = $updateMode;
    $settings['designMode'] = $designMode;
    $settings['builderPlaceholder'] = false;

    if (empty($settings['headerRowsCount'])) {
      $settings['headerRowsCount'] = 1;
    }

    return $settings;
  }

  private function sanitizeSettings($settings)
  {
    $woocommerce = isset($settings['woocommerce']) && is_array($settings['woocommerce']) ? $settings['woocommerce'] : [];
    $design = isset($woocommerce['design']) && is_array($woocommerce['design']) ? $woocommerce['design'] : [];

    $clean = [
      'enable' => !empty($woocommerce['enable']) ? 'on' : '',
      'thumbnail_size' => isset($woocommerce['thumbnail_size']) ? sanitize_key($woocommerce['thumbnail_size']) : 'thumbnail',
      'thumbnail_width' => isset($woocommerce['thumbnail_width']) ? absint($woocommerce['thumbnail_width']) : '',
      'thumbnail_height' => isset($woocommerce['thumbnail_height']) ? absint($woocommerce['thumbnail_height']) : '',
      'multiple_add_cart' => !empty($woocommerce['multiple_add_cart']) ? 'on' : '',
      'view_checkout_show' => !empty($woocommerce['_view_checkout_show_present']) ? (!empty($woocommerce['view_checkout_show']) ? 'on' : '') : 'on',
      'view_cart_hide' => !empty($woocommerce['view_cart_hide']) ? 'on' : '',
      'show_private' => !empty($woocommerce['show_private']) ? 'on' : '',
      'hide_out_of_stock' => !empty($woocommerce['hide_out_of_stock']) ? 'on' : '',
      'productids' => !empty($woocommerce['productids']) ? implode(',', $this->sanitizeIds(explode(',', $woocommerce['productids']))) : '',
      'exclude_productids' => '',
      'order' => isset($woocommerce['order']) ? $this->sanitizeColumnOrder($woocommerce['order']) : '',
      'filter_attribute' => '',
      'filter_category_list' => '',
      'filter_stock_status' => '',
      'expand_filter_row' => '',
      'filter_price_show_inputs' => '',
      'filter_price_show_slider' => '',
      'filter_attribute_selected' => [],
      'auto_categories_enable' => '',
      'auto_categories_list' => '',
      'translations' => $this->getWooTranslationDefaults(),
      'design' => $this->getWooDesignDefaults(),
    ];

    if ($this->isAdvancedWooAllowed()) {
      $clean['filter_attribute'] = !empty($woocommerce['filter_attribute']) ? 'on' : '';
      $clean['filter_category_list'] = !empty($woocommerce['filter_category_list']) ? 'on' : '';
      $clean['filter_stock_status'] = empty($clean['hide_out_of_stock']) && !empty($woocommerce['filter_stock_status']) ? 'on' : '';
      $clean['expand_filter_row'] = !empty($woocommerce['expand_filter_row']) ? 'on' : '';
      $clean['filter_price_show_inputs'] = !empty($woocommerce['_filter_price_show_inputs_present']) ? (!empty($woocommerce['filter_price_show_inputs']) ? 'on' : '') : 'on';
      $clean['filter_price_show_slider'] = !empty($woocommerce['_filter_price_show_slider_present']) ? (!empty($woocommerce['filter_price_show_slider']) ? 'on' : '') : 'on';
      $clean['filter_attribute_selected'] = !empty($woocommerce['filter_attribute_selected']) ? $this->sanitizeAttributeFilterKeys($woocommerce['filter_attribute_selected']) : [];
      $clean['auto_categories_enable'] = !empty($woocommerce['auto_categories_enable']) ? 'on' : '';
      $clean['auto_categories_list'] = !empty($woocommerce['auto_categories_list']) ? implode(',', $this->sanitizeCategoryIds(explode(',', $woocommerce['auto_categories_list']))) : '';
      $clean['exclude_productids'] = !empty($woocommerce['exclude_productids']) ? implode(',', $this->sanitizeIds(explode(',', $woocommerce['exclude_productids']))) : '';
      $clean['design'] = $this->sanitizeDesignSettings($design);
    }

    if (!empty($woocommerce['translations']) && is_array($woocommerce['translations'])) {
      $clean['translations'] = $this->sanitizeTranslationSettings($woocommerce['translations']);
    }

    return ['woocommerce' => $clean];
  }

  private function getWooTranslationDefaults()
  {
    return [
      'length_menu' => '',
      'info' => '',
      'info_filtered' => '',
      'previous' => '',
      'next' => '',
      'filters' => '',
      'availability' => '',
      'price_label' => '',
      'columns' => '',
      'columns_search' => '',
      'reset' => '',
      'price_from' => '',
      'price_to' => '',
      'view_checkout' => '',
      'add_selected_to_cart' => '',
      'add_to_cart' => '',
    ];
  }

  private function getWooDesignDefaults()
  {
    return [
      'enabled' => '',
      'header_text' => '#1f2933',
      'header_bg' => '#f8fafc',
      'header_font_size' => 14,
      'header_font_weight' => 600,
      'body_text' => '#334155',
      'body_bg' => '#ffffff',
      'body_font_size' => 14,
      'body_font_weight' => 400,
      'input_text' => '#1f2933',
      'input_bg' => '#ffffff',
      'select_text' => '#1f2933',
      'select_bg' => '#ffffff',
      'length_color' => '#334155',
      'length_font_size' => 13,
      'length_font_weight' => 400,
      'info_color' => '#334155',
      'info_font_size' => 13,
      'info_font_weight' => 400,
      'description_color' => '#334155',
      'description_font_size' => 14,
      'description_font_weight' => 400,
      'signature_color' => '#334155',
      'signature_font_size' => 13,
      'signature_font_weight' => 400,
      'processing_bg' => '#ffffff',
      'processing_color' => '#000000',
      'button_text' => '#ffffff',
      'button_bg' => '#2271b1',
      'button_font_size' => 13,
      'button_font_weight' => 600,
      'button_hover_text' => '#ffffff',
      'button_hover_bg' => '#135e96',
      'stripe_text' => '#334155',
      'stripe_bg' => '#f8fafc',
      'hover_text' => '#111827',
      'hover_bg' => '#eef2f7',
      'price_text' => '#111827',
      'price_bg' => '#ffffff',
      'price_font_size' => 16,
      'price_font_weight' => 600,
      'price_filter_input_text' => '#1f2933',
      'price_filter_input_bg' => '#ffffff',
      'price_filter_input_font_size' => 13,
      'price_filter_input_font_weight' => 400,
      'price_filter_track' => '#d7dce1',
      'price_filter_fill' => '#2271b1',
      'price_filter_thumb' => '#2271b1',
      'price_filter_thumb_style' => 'circle',
      'checkout_button_text' => '#2271b1',
      'checkout_button_bg' => '#ffffff',
      'checkout_button_font_size' => 13,
      'checkout_button_font_weight' => 600,
      'checkout_button_hover_text' => '#ffffff',
      'checkout_button_hover_bg' => '#135e96',
      'reset_button_text' => '#334155',
      'reset_button_bg' => '#ffffff',
      'reset_button_font_size' => 13,
      'reset_button_font_weight' => 600,
      'reset_button_hover_text' => '#ffffff',
      'reset_button_hover_bg' => '#2271b1',
      'border_color' => '#d7dce1',
    ];
  }

  private function sanitizeDesignSettings($design)
  {
    $defaults = $this->getWooDesignDefaults();
    $design = is_array($design) ? $design : [];
    $clean = [
      'enabled' => !empty($design['enabled']) ? 'on' : '',
    ];

    foreach ($defaults as $key => $value) {
      if ($key === 'enabled') {
        continue;
      }

      if (in_array($key, ['header_font_size', 'body_font_size', 'button_font_size', 'length_font_size', 'info_font_size', 'description_font_size', 'signature_font_size', 'price_font_size', 'price_filter_input_font_size', 'checkout_button_font_size', 'reset_button_font_size'], true)) {
        $clean[$key] = isset($design[$key]) ? min(48, max(10, absint($design[$key]))) : $value;
        continue;
      }

      if (in_array($key, ['header_font_weight', 'body_font_weight', 'button_font_weight', 'length_font_weight', 'info_font_weight', 'description_font_weight', 'signature_font_weight', 'price_font_weight', 'price_filter_input_font_weight', 'checkout_button_font_weight', 'reset_button_font_weight'], true)) {
        $allowedWeights = [300, 400, 500, 600, 700, 800];
        $weight = isset($design[$key]) ? absint($design[$key]) : 0;
        $clean[$key] = in_array($weight, $allowedWeights, true) ? $weight : $value;
        continue;
      }

      if ($key === 'price_filter_thumb_style') {
        $style = isset($design[$key]) ? sanitize_key($design[$key]) : '';
        $clean[$key] = in_array($style, ['circle', 'round', 'square'], true) ? $style : $value;
        continue;
      }

      $sanitized = isset($design[$key]) ? sanitize_hex_color($design[$key]) : '';
      $clean[$key] = $sanitized ? $sanitized : $value;
    }

    return $clean;
  }

  private function sanitizeTranslationSettings($translations)
  {
    $defaults = $this->getWooTranslationDefaults();
    $translations = is_array($translations) ? $translations : [];
    $clean = [];

    foreach ($defaults as $key => $value) {
      $clean[$key] = isset($translations[$key]) ? sanitize_text_field(wp_unslash($translations[$key])) : $value;
    }

    return $clean;
  }

  private function sanitizeProductQueryParams($params)
  {
    $params = is_array($params) ? $params : [];
    $params['draw'] = isset($params['draw']) ? absint($params['draw']) : 0;
    $params['start'] = isset($params['start']) ? absint($params['start']) : 0;
    $params['tableid'] = isset($params['tableid']) ? absint($params['tableid']) : 0;
    $params['show_variations'] = !empty($params['show_variations']) ? '1' : '0';
    $params['show_private'] = !empty($params['show_private']) && $this->isAdvancedWooAllowed() ? '1' : '0';

    if (isset($params['search']['value'])) {
      $params['search']['value'] = sanitize_text_field(wp_unslash($params['search']['value']));
    }

    if (isset($params['order'][0]['column'])) {
      $params['order'][0]['column'] = absint($params['order'][0]['column']);
    }
    if (isset($params['order'][0]['dir'])) {
      $params['order'][0]['dir'] = strtolower($params['order'][0]['dir']) === 'asc' ? 'ASC' : 'DESC';
    }

    return $params;
  }

  private function sanitizeIds($ids)
  {
    if (!is_array($ids)) {
      $ids = [$ids];
    }

    $ids = array_map('absint', $ids);
    $ids = array_filter($ids);

    return array_values(array_unique($ids));
  }

  private function sanitizeAttributeFilterKeys($keys)
  {
    if (!is_array($keys)) {
      $keys = [$keys];
    }

    $clean = [];
    foreach ($keys as $key) {
      if (is_numeric($key)) {
        $key = absint($key);
        if ($key > 0) {
          $clean[] = $key;
        }
        continue;
      }

      $key = strtolower(trim((string) $key));
      if (strpos($key, 'custom:') === 0) {
        $customKey = sanitize_title(substr($key, 7));
        if ($customKey !== '') {
          $clean[] = 'custom:' . $customKey;
        }
      }
    }

    return array_values(array_unique($clean));
  }

  private function sanitizeCategoryIds($ids)
  {
    if (!is_array($ids)) {
      $ids = [$ids];
    }

    $clean = [];
    foreach ($ids as $id) {
      if ($id === 'all') {
        $clean[] = 'all';
        continue;
      }

      $id = absint($id);
      if ($id > 0) {
        $clean[] = $id;
      }
    }

    return array_values(array_unique($clean));
  }

  private function sanitizeCartProducts($selectedProducts)
  {
    if (!is_array($selectedProducts)) {
      return [];
    }

    $clean = [];
    foreach ($selectedProducts as $selectedProduct) {
      if (!is_array($selectedProduct)) {
        continue;
      }

      $variation = [];
      if (!empty($selectedProduct['variation']) && is_array($selectedProduct['variation'])) {
        foreach ($selectedProduct['variation'] as $key => $value) {
          $variation[sanitize_key($key)] = sanitize_text_field(wp_unslash($value));
        }
      }

      $clean[] = [
        'id' => isset($selectedProduct['id']) ? absint($selectedProduct['id']) : 0,
        'quantity' => isset($selectedProduct['quantity']) ? max(1, absint($selectedProduct['quantity'])) : 0,
        'varId' => isset($selectedProduct['varId']) ? absint($selectedProduct['varId']) : 0,
        'variation' => $variation,
      ];
    }

    return $clean;
  }

  private function sanitizeColumnOrder($order)
  {
    $order = is_string($order) ? wp_unslash($order) : '';
    $decoded = json_decode($order, true);
    if (!is_array($decoded)) {
      return '';
    }

    $clean = [];
    foreach ($decoded as $column) {
      if (!is_array($column)) {
        continue;
      }

      $cleanColumn = [
        'id' => isset($column['id']) ? sanitize_text_field(wp_unslash($column['id'])) : '',
        'slug' => isset($column['slug']) ? sanitize_key($column['slug']) : '',
        'name' => isset($column['name']) ? sanitize_text_field(wp_unslash($column['name'])) : '',
        'original_name' => isset($column['original_name']) ? sanitize_text_field(wp_unslash($column['original_name'])) : '',
        'show_display_name' => !empty($column['show_display_name']) ? 1 : 0,
        'display_name' => isset($column['display_name']) ? sanitize_text_field(wp_unslash($column['display_name'])) : '',
        'max_width' => $this->isAdvancedWooAllowed() && !empty($column['max_width']) ? absint($column['max_width']) : '',
        'hide_column' => $this->isAdvancedWooAllowed() && !empty($column['hide_column']) ? 1 : 0,
        'hide_responsive' => $this->isAdvancedWooAllowed() && !empty($column['hide_responsive']) ? 1 : 0,
        'hide_search_input' => $this->isAdvancedWooAllowed() && !empty($column['hide_search_input']) ? 1 : 0,
        'disable_sorting' => $this->isAdvancedWooAllowed() && !empty($column['disable_sorting']) ? 1 : 0,
        'text_align' => $this->isAdvancedWooAllowed() && !empty($column['text_align']) && in_array($column['text_align'], ['left', 'center', 'right'], true) ? $column['text_align'] : '',
        'vertical_align' => $this->isAdvancedWooAllowed() && !empty($column['vertical_align']) && in_array($column['vertical_align'], ['top', 'middle', 'bottom'], true) ? $column['vertical_align'] : '',
        'show_price_search_inputs' => $this->isAdvancedWooAllowed() && !empty($column['show_price_search_inputs']) ? 1 : 0,
      ];

      if ($cleanColumn['slug'] !== '') {
        $clean[] = $cleanColumn;
      }
    }

    return empty($clean) ? '' : wp_json_encode($clean);
  }

  private function isAdvancedWooAllowed()
  {
    return $this->getEnvironment()->isPro();
  }

  private function getTableSettingsFromTable($table)
  {
    if (!is_object($table)) {
      return [];
    }

    if (!empty($table->settings) && is_array($table->settings)) {
      return $table->settings;
    }

    if (!empty($table->settings) && is_string($table->settings)) {
      return $this->maybeUnserializeArray($table->settings);
    }

    return [];
  }

  private function sanitizeWooTableCommonSettings(RscDtgs_Http_Request $request, $existingSettings)
  {
    $rawSettings = $request->post->get('settings');
    if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
      $rawSettings = stripslashes($rawSettings);
    }

    $parsed = [];
    parse_str($rawSettings, $parsed);

    if (isset($parsed['woocommerce'])) {
      unset($parsed['woocommerce']);
    }

    $tablesModel = $this->getModel('tables');
    $existingSettings = is_array($existingSettings) ? $tablesModel->sanitize_array($existingSettings) : [];
    $parsed = is_array($parsed) ? $tablesModel->sanitize_array($parsed) : [];

    if (!isset($existingSettings['elements']) || !is_array($existingSettings['elements'])) {
      $existingSettings['elements'] = [];
    }
    $existingSettings['elements']['descriptionText'] = isset($parsed['elements']['descriptionText']) ? $parsed['elements']['descriptionText'] : '';
    $existingSettings['elements']['signatureText'] = isset($parsed['elements']['signatureText']) ? $parsed['elements']['signatureText'] : '';
    unset($existingSettings['elements']['caption']);
    unset($existingSettings['elements']['description']);
    unset($existingSettings['elements']['signature']);
    unset($existingSettings['elements']['head']);
    if ($existingSettings['elements']['descriptionText'] !== '') {
      $existingSettings['elements']['description'] = 'on';
    }
    if ($existingSettings['elements']['signatureText'] !== '') {
      $existingSettings['elements']['signature'] = 'on';
    }
    if (!empty($parsed['elements']['head'])) {
      $existingSettings['elements']['head'] = 'on';
    }
    $headerEnabled = !empty($existingSettings['elements']['head']);

    if (!isset($existingSettings['features']) || !is_array($existingSettings['features'])) {
      $existingSettings['features'] = [];
    }
    $advancedAllowed = $this->isAdvancedWooAllowed();
    foreach (['info', 'ordering', 'paging', 'searching', 'auto_width', 'showHideColumnsList'] as $key) {
      unset($existingSettings['features'][$key]);
      if (in_array($key, ['searching', 'showHideColumnsList'], true) && !$advancedAllowed) {
        continue;
      }
      if (!$headerEnabled && in_array($key, ['ordering', 'searching'], true)) {
        continue;
      }
      if (!empty($parsed['features'][$key])) {
        $existingSettings['features'][$key] = 'on';
      }
    }

    if (!isset($existingSettings['searching']) || !is_array($existingSettings['searching'])) {
      $existingSettings['searching'] = [];
    }
    foreach (['columnSearch', 'columnSearchShowLabel', 'searchByHiddenField', 'resultOnly', 'showTable', 'strictMatching'] as $key) {
      unset($existingSettings['searching'][$key]);
      if (!$advancedAllowed || !$headerEnabled || empty($existingSettings['features']['searching'])) {
        continue;
      }
      if (!empty($parsed['searching'][$key])) {
        $existingSettings['searching'][$key] = 'on';
      }
    }
    if ($advancedAllowed && $headerEnabled && !empty($existingSettings['features']['searching'])) {
      $existingSettings['searching']['columnSearchPosition'] = !empty($parsed['searching']['columnSearchPosition']) && in_array($parsed['searching']['columnSearchPosition'], ['top', 'bottom'], true) ? $parsed['searching']['columnSearchPosition'] : 'bottom';
      $existingSettings['searching']['minChars'] = isset($parsed['searching']['minChars']) ? absint($parsed['searching']['minChars']) : '';
    } else {
      unset($existingSettings['searching']['columnSearchPosition'], $existingSettings['searching']['minChars']);
    }

    if (!isset($existingSettings['styling']) || !is_array($existingSettings['styling'])) {
      $existingSettings['styling'] = [];
    }
    foreach (['lfixed', 'compact', 'nowrap', 'paragraphMode', 'stripe', 'hover', 'order-column'] as $key) {
      unset($existingSettings['styling'][$key]);
      if (!empty($parsed['styling'][$key])) {
        $existingSettings['styling'][$key] = 'on';
      }
    }
    $existingSettings['styling']['border'] = !empty($parsed['styling']['border']) && in_array($parsed['styling']['border'], ['cell-border', 'row-border', 'no-border'], true) ? $parsed['styling']['border'] : 'cell-border';

    if (!isset($existingSettings['tableLoader']) || !is_array($existingSettings['tableLoader'])) {
      $existingSettings['tableLoader'] = [];
    }
    unset($existingSettings['tableLoader']['disable']);
    if (!empty($parsed['tableLoader']['disable'])) {
      $existingSettings['tableLoader']['disable'] = 'on';
    }
    $existingSettings['tableLoader']['iconName'] = !empty($parsed['tableLoader']['iconName']) ? sanitize_key($parsed['tableLoader']['iconName']) : 'default';
    $existingSettings['tableLoader']['iconItems'] = !empty($parsed['tableLoader']['iconItems']) ? absint($parsed['tableLoader']['iconItems']) : 0;
    $existingSettings['tableLoader']['color'] = !empty($parsed['tableLoader']['color']) ? sanitize_hex_color($parsed['tableLoader']['color']) : '#000000';

    $existingSettings['responsiveMode'] = isset($parsed['responsiveMode']) && in_array((string) $parsed['responsiveMode'], ['0', '1', '2', '3'], true) ? (string) $parsed['responsiveMode'] : '1';
    $existingSettings['sortingOrder'] = !empty($parsed['sortingOrder']) && $parsed['sortingOrder'] === 'desc' ? 'desc' : 'asc';
    $existingSettings['sortingOrderColumn'] = isset($parsed['sortingOrderColumn']) ? absint($parsed['sortingOrderColumn']) : 1;
    $existingSettings['paginationMenuLength'] = isset($parsed['paginationMenuLength']) ? $parsed['paginationMenuLength'] : '50,100,All';
    $existingSettings['paginationSize'] = !empty($parsed['paginationSize']) && in_array($parsed['paginationSize'], ['pagination-large', 'pagination-medium', 'pagination-small'], true) ? $parsed['paginationSize'] : 'pagination-large';
    unset($existingSettings['scrollTopByPagination']);
    if (!empty($parsed['scrollTopByPagination'])) {
      $existingSettings['scrollTopByPagination'] = 'on';
    }
    unset($existingSettings['serverSideProcessing']);
    if ($advancedAllowed && !empty($parsed['serverSideProcessing'])) {
      $existingSettings['serverSideProcessing'] = 'on';
    }
    unset($existingSettings['alignByFirstTable']);
    if (!empty($parsed['alignByFirstTable'])) {
      $existingSettings['alignByFirstTable'] = 'on';
    }

    $existingSettings['tableWidth'] = isset($parsed['tableWidth']) ? $parsed['tableWidth'] : '100';
    $existingSettings['tableWidthType'] = !empty($parsed['tableWidthType']) && in_array($parsed['tableWidthType'], ['%', 'px', 'auto'], true) ? $parsed['tableWidthType'] : '%';
    $existingSettings['tableWidthMobile'] = isset($parsed['tableWidthMobile']) ? $parsed['tableWidthMobile'] : '100';
    $existingSettings['tableWidthMobileType'] = !empty($parsed['tableWidthMobileType']) && in_array($parsed['tableWidthMobileType'], ['%', 'px', 'auto'], true) ? $parsed['tableWidthMobileType'] : '%';

    if ($advancedAllowed) {
      $supportedExportFormats = ['csv', 'xls', 'xlsx', 'pdf', 'print'];
      if (!isset($existingSettings['features']) || !is_array($existingSettings['features'])) {
        $existingSettings['features'] = [];
      }
      unset($existingSettings['features']['export']);
      if (!empty($parsed['features']['export']) && is_array($parsed['features']['export'])) {
        $selectedFormats = array_values(array_unique(array_intersect(array_map('sanitize_key', $parsed['features']['export']), $supportedExportFormats)));
        if (!empty($selectedFormats)) {
          $existingSettings['features']['export'] = $selectedFormats;
        }
      }

      $existingSettings['exportLinksPosition'] = 'before_table';
      $existingSettings['exportOnlyVisible'] = 'on';

      $supportedPdfPaperSizes = ['auto', 'a4', 'letter', 'legal', 'tabloid'];
      $existingSettings['pdfPaperSize'] = !empty($parsed['pdfPaperSize']) && in_array($parsed['pdfPaperSize'], $supportedPdfPaperSizes, true) ? $parsed['pdfPaperSize'] : 'auto';
      $existingSettings['pdfOrientation'] = !empty($parsed['pdfOrientation']) && in_array($parsed['pdfOrientation'], ['portrait', 'landscape'], true) ? $parsed['pdfOrientation'] : 'portrait';
      unset($existingSettings['pdfFullWidth']);
      if (!empty($parsed['pdfFullWidth'])) {
        $existingSettings['pdfFullWidth'] = 'on';
      }
    }

    $existingSettings['headerRowsCount'] = 1;
    $existingSettings['tableType'] = 'woo_product_table';
    $existingSettings['sourceType'] = 'woocommerce';

    return $existingSettings;
  }
}
