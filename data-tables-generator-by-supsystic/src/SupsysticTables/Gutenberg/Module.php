<?php

/**
 * Registers a Gutenberg block for Data Tables by Supsystic. The table is picked in the block sidebar (or in the
 * placeholder until one is picked). When the editor canvas is an iframe, the block shows the table as on the page:
 * the block-preview REST route renders the table and lists the tags of the CSS/JS this one table needs, and block.js
 * adds them to the canvas iframe only (the admin screen itself gets nothing). The picked table renders normally on the
 * published page, same as the shortcode and the classic widget.
 */
class SupsysticTables_Gutenberg_Module extends SupsysticTables_Core_BaseModule
{
  const BLOCK_NAME = 'supsystic-tables/data-table';
  const SCRIPT_HANDLE = 'supsystic-tables-gutenberg-block';
  const REST_NAMESPACE = 'supsystic-tables/v1';

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    add_action('init', [$this, 'registerBlock']);
    // The shared "Supsystic" block category, first in the inserter (plugins/.claude/fix/shared/gutenberg-supsystic-category.md)
    if (function_exists('get_default_block_categories')) { // WP 5.8+
      add_filter('block_categories_all', [$this, 'addSupsysticBlockCategory'], 5);
    } else {
      add_filter('block_categories', [$this, 'addSupsysticBlockCategory'], 5);
    }
    add_action('rest_api_init', [$this, 'registerRestRoutes']);
    add_action('enqueue_block_editor_assets', [$this, 'addEditorData']);
  }

  /**
   * Adds the "Supsystic" category first, once: any other Supsystic plugin may have added it already.
   *
   * @param array $categories
   * @return array
   */
  public function addSupsysticBlockCategory($categories)
  {
    if (!is_array($categories)) {
      return $categories;
    }
    foreach ($categories as $category) {
      if (isset($category['slug']) && $category['slug'] === 'supsystic') {
        return $categories;
      }
    }
    array_unshift($categories, ['slug' => 'supsystic', 'title' => 'Supsystic', 'icon' => null]);
    return $categories;
  }

  public function registerBlock()
  {
    if (!function_exists('register_block_type') || !function_exists('wp_register_script')) {
      return;
    }

    $blockJson = $this->getLocation() . '/block/block.json';
    if (!is_file($blockJson)) {
      return;
    }

    $config = $this->getEnvironment()->getConfig();

    wp_register_script(
      self::SCRIPT_HANDLE,
      $this->getLocationUrl() . '/assets/js/block.js',
      ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n', 'wp-block-editor', 'wp-api-fetch'],
      $config->get('plugin_version') . '-block-drag-1',
      true
    );

    register_block_type($blockJson, [
      'render_callback' => [$this, 'render'],
    ]);
  }

  /**
   * Block editor only (not on every request as before): the table list and where the preview and the table editor are.
   */
  public function addEditorData()
  {
    if (!wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
      return;
    }
    $config = $this->getEnvironment()->getConfig();
    $menu = $config->get('plugin_menu');
    $slug = is_array($menu) && !empty($menu['menu_slug']) ? $menu['menu_slug'] : 'supsystic-tables';
    $capability = is_array($menu) && !empty($menu['capability']) ? $menu['capability'] : 'manage_options';
    wp_add_inline_script(self::SCRIPT_HANDLE, 'window.SupsysticTablesBlockData = ' . wp_json_encode([
      'tables' => $this->getTableOptionsForEditor(),
      'previewPath' => '/' . self::REST_NAMESPACE . '/block-preview/',
      // The Supsystic icon and the Edit / Create links, like CES (plugins/.claude/fix/shared/builder-blocks-branding.md)
      'icon' => $config->get('plugin_url') . '/app/assets/img/supsystic-adminbar.png',
      'canManage' => current_user_can($capability),
      'editUrl' => admin_url('admin.php?page=' . $slug . '&module=tables&action=view&id='),
      'createUrl' => admin_url('admin.php?page=' . $slug),
    ]) . ';', 'before');
  }

  /**
   * Block render_callback - the published page, reuses the shortcode renderer, same as the Elementor widget and the
   * classic widget.
   *
   * @param array $attributes
   * @return string
   */
  public function render($attributes)
  {
    $tableId = !empty($attributes['tableId']) ? (int) $attributes['tableId'] : 0;

    if (!$tableId) {
      return '';
    }

    return supsystic_tables_get($tableId);
  }

  public function registerRestRoutes()
  {
    register_rest_route(self::REST_NAMESPACE, '/block-preview/(?P<id>\d+)', [
      'methods' => 'GET',
      'callback' => [$this, 'restPreview'],
      'permission_callback' => function () {
        return current_user_can('edit_posts');
      },
      'args' => ['id' => ['validate_callback' => function ($value) {
        return is_numeric($value) && (int) $value > 0;
      }]],
    ]);
  }

  /**
   * The table as the published page shows it, and the tags of the CSS/JS it needs: the plugin's own front-end
   * registrations run on a separate queue (as WordPress does for the editor iframe), then the table renders and its
   * assets are added like on the page. Other plugins' and the theme's assets are not touched.
   *
   * @param WP_REST_Request $request
   * @return WP_REST_Response|WP_Error
   */
  public function restPreview($request)
  {
    global $wp_styles, $wp_scripts, $wp_filter;

    $tableId = (int) $request['id'];
    $tables = $this->getEnvironment()->getModule('tables');
    $ui = $this->getEnvironment()->getModule('ui');
    if (!$tables || !$ui || !method_exists($tables, 'doShortcode') || !method_exists($tables, 'filterAssets')) {
      return new WP_Error('supsystic_tables_preview', 'Data Tables is not ready.', ['status' => 500]);
    }

    $currentStyles = wp_styles();
    $currentScripts = wp_scripts();
    $wp_styles = new WP_Styles();
    $wp_scripts = new WP_Scripts();
    $wp_styles->registered = $currentStyles->registered;
    $wp_scripts->registered = $currentScripts->registered;

    $level = ob_get_level();
    $html = '';
    $assets = '';
    try {
      // The canvas iframe has no jQuery of its own; some of the plugin's small scripts (nonce) use it without declaring it
      wp_enqueue_script('jquery');
      // What wp_enqueue_scripts does for this plugin on a page (asset registrations, ajax url, nonces, fonts),
      // and only that: the callbacks of the plugin's and PRO's own objects
      if (!empty($wp_filter['wp_enqueue_scripts']) && $wp_filter['wp_enqueue_scripts'] instanceof WP_Hook) {
        foreach ($wp_filter['wp_enqueue_scripts']->callbacks as $callbacks) {
          foreach ($callbacks as $callback) {
            $function = $callback['function'];
            if (is_array($function) && is_object($function[0]) && strpos(get_class($function[0]), 'SupsysticTables') === 0) {
              call_user_func($function);
            }
          }
        }
      }
      $html = (string) $tables->doShortcode(['id' => $tableId]);
      // doShortcode() leaves the display assets to wp_footer, which a REST request does not have. A pricing table
      // does not use them (DataTables and the rest): its own styles and script came with its render.
      $table = $this->getEnvironment()->getModule('core')->getModelsFactory()->get('tables')->getById($tableId);
      if (!$table || !isset($table->table_type) || $table->table_type !== 'pricing_table') {
        foreach (array_filter($ui->getAssets(), [$tables, 'filterAssets']) as $asset) {
          $asset->load();
        }
      }
      ob_start();
      $wp_styles->do_items();
      $wp_scripts->do_items();
      $assets = (string) ob_get_clean();
    } catch (\Throwable $e) {
      while (ob_get_level() > $level) {
        ob_end_clean();
      }
      $wp_styles = $currentStyles;
      $wp_scripts = $currentScripts;
      return new WP_Error('supsystic_tables_preview', 'The table could not be rendered.', ['status' => 500]);
    }

    $wp_styles = $currentStyles;
    $wp_scripts = $currentScripts;

    return rest_ensure_response(['html' => $html, 'assets' => $assets]);
  }

  /**
   * @return array [{label, value, type}, ...] for the block's SelectControl; type = table_type (the Edit link's wording)
   */
  private function getTableOptionsForEditor()
  {
    $model = new SupsysticTables_Tables_Model_Tables();
    $types = [];
    foreach ((array) $model->getList() as $table) {
      if (!empty($table->id)) {
        $types[(int) $table->id] = isset($table->table_type) ? (string) $table->table_type : 'default';
      }
    }
    $options = [];

    foreach ($model->getOptionsForSelect() as $id => $label) {
      $options[] = ['label' => $label, 'value' => (string) $id, 'type' => isset($types[(int) $id]) ? $types[(int) $id] : 'default'];
    }

    return $options;
  }
}
