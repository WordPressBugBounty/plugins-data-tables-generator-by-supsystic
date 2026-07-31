<?php

/**
 * Plugin Name: Data Tables Generator by Supsystic
 * Plugin URI: http://supsystic.com
 * Description: Create and manage beautiful data tables with custom design. No HTML knowledge is required
 * Version: 1.13.1
 * Author: supsystic.com
 * Author URI: http://supsystic.com
 * Text Domain: supsystic_tables
 * Domain Path: /app/langs
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