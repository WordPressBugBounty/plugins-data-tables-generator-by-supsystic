<?php

/**
 * Plugin Name: Data Tables Generator by Supsystic
 * Plugin URI: http://supsystic.com
 * Description: Three table types in one plugin: Excel-like data tables with formulas, WooCommerce product tables and pricing tables. Responsive and searchable.
 * Version: 1.18.1
 * Author: supsystic.com
 * Author URI: http://supsystic.com
 * Text Domain: supsystic_tables
 * Domain Path: /app/langs
 * Requires PHP: 7.4
 */

function dtgsDeactivateLegacyWooAddonNotice()
{
  global $pagenow;
  if ($pagenow == 'admin.php' || $pagenow == 'plugins.php') {
    echo '<div class="notice notice-warning is-dismissible"><p><b>Woo Product Tables by Supsystic</b> was deactivated to prevent conflicts. WooCommerce product catalog functionality is now included in <b>Data Tables Generator by Supsystic</b>; advanced WooCommerce features are handled by Data Tables PRO.</p></div>';
  }
}
if (is_admin() && function_exists('is_plugin_active') && is_plugin_active('tables-woo-generator-pro/index.php')) {
  deactivate_plugins('tables-woo-generator-pro/index.php');
  add_action('admin_notices', 'dtgsDeactivateLegacyWooAddonNotice');
}

include dirname(__FILE__) . '/app/SupsysticTables.php';

if (!defined('SUPSYSTIC_STB_DEBUG')) {
  define('SUPSYSTIC_STB_DEBUG', false);
}
if (!defined('SUPSYSTIC_TABLES_SHORTCODE_NAME')) {
  define('SUPSYSTIC_TABLES_SHORTCODE_NAME', 'supsystic-tables');
}
if (!defined('SUPSYSTIC_TABLES_PART_SHORTCODE_NAME')) {
  define('SUPSYSTIC_TABLES_PART_SHORTCODE_NAME', SUPSYSTIC_TABLES_SHORTCODE_NAME . '-part');
}
if (!defined('SUPSYSTIC_TABLES_CELL_SHORTCODE_NAME')) {
  define('SUPSYSTIC_TABLES_CELL_SHORTCODE_NAME', SUPSYSTIC_TABLES_SHORTCODE_NAME . '-cell-full');
}
if (!defined('SUPSYSTIC_TABLES_VALUE_SHORTCODE_NAME')) {
  define('SUPSYSTIC_TABLES_VALUE_SHORTCODE_NAME', SUPSYSTIC_TABLES_SHORTCODE_NAME . '-cell');
}
if (!defined('DTGS_WOO_CATALOG_BUILT_IN')) {
  define('DTGS_WOO_CATALOG_BUILT_IN', true);
}
if (!defined('DTGS_PLUGIN_URL')) {
  define('DTGS_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('DTGS_PLUGIN_ADMIN_URL')) {
  define('DTGS_PLUGIN_ADMIN_URL', admin_url());
}

$supsysticTables = new SupsysticTables();
$supsysticTables->run();

$supsysticTables->activate(__FILE__);
$supsysticTables->deactivate(__FILE__);

if (!function_exists('supsystic_tables_get')) {
  function supsystic_tables_get($id)
  {
    return do_shortcode(sprintf('[%s id="%d"]', SUPSYSTIC_TABLES_SHORTCODE_NAME, (int) $id));
  }
}

if (!function_exists('supsystic_tables_pricing_import_url')) {
  /**
   * The page of Data Tables that moves the tables of Pricing Table by Supsystic, for the button of that plugin.
   * @param array $args from (pricing-table), ids (ids of the tables to tick, array or "1,2").
   * @return string
   */
  function supsystic_tables_pricing_import_url(array $args = [])
  {
    $query = ['page' => 'supsystic-tables', 'module' => 'pricingimport'];
    if (!empty($args['from'])) {
      $query['from'] = sanitize_key($args['from']);
    }
    if (!empty($args['ids'])) {
      $ids = is_array($args['ids']) ? $args['ids'] : explode(',', (string) $args['ids']);
      $query['ids'] = implode(',', array_filter(array_map('absint', $ids)));
    }

    return add_query_arg($query, admin_url('admin.php'));
  }
}

if (!function_exists('supsystic_tables_pricing_import_status')) {
  /**
   * How many tables of Pricing Table there are and how many of them are moved to Data Tables.
   * @return array total, imported, url.
   */
  function supsystic_tables_pricing_import_status()
  {
    global $wpdb;
    $source = $wpdb->prefix . 'pts_tables';
    $ids = [];
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($source))) === $source) {
      $ids = array_map('intval', (array) $wpdb->get_col("SELECT id FROM {$source} WHERE original_id <> 0"));
    }
    $state = get_option('supsystic_tbl_pricing_import', []);
    $map = is_array($state) && !empty($state['tables']) && is_array($state['tables']) ? array_intersect_key($state['tables'], array_flip($ids)) : [];
    $imported = 0;
    if ($map) {
      $tables = $wpdb->prefix . 'supsystic_tbl_tables';
      $existing = array_map('intval', (array) $wpdb->get_col("SELECT id FROM {$tables} WHERE id IN (" . implode(',', array_map('intval', $map)) . ") AND table_type = 'pricing_table'"));
      $imported = count(array_intersect(array_map('intval', $map), $existing));
    }

    return ['total' => count($ids), 'imported' => $imported, 'url' => supsystic_tables_pricing_import_url()];
  }
}
