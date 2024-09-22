<?php

/**
 * Plugin Name: Data Tables Generator by Supsystic
 * Plugin URI: http://supsystic.com
 * Description: Create and manage beautiful data tables with custom design. No HTML knowledge is required
 * Version: 1.10.36
 * Author: supsystic.com
 * Author URI: http://supsystic.com
 * Text Domain: supsystic_tables
 * Domain Path: /app/langs
 */

 //Fix RSC Class rename for PRO plugin
 function dtgsChangeProVersionNotice(){
		global $pagenow;
		if ( $pagenow == 'admin.php' || $pagenow == 'plugins.php' ) {
			echo '<div class="notice notice-warning is-dismissible"><p><b>WARNING!</b> You using <b>OLD Data Tables by Supsystic PRO</b> version! For continued use and before activating the PRO plugin - please <b>UPDATE PRO VERSION</b>. Thank you.<br><b>You can download new compatible PRO version direct from this <a href="https://supsystic.com/pro/tables-generator-pro.zip">LINK</a></b>.</p></div>';
		}
 }
 require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
 $proPluginPath = dirname(__FILE__);
 $proPluginPath = str_replace('data-tables-generator-by-supsystic', 'tables-generator-pro', $proPluginPath);
 $proPluginPath = $proPluginPath . '/index.php';
 if (file_exists($proPluginPath)) {
	 $pluginData = get_file_data($proPluginPath, array('Version' => 'Version'), false);
	 if (!empty($pluginData['Version']) && version_compare($pluginData['Version'], '1.7.12', '<')) {
		 add_action('admin_notices', 'dtgsChangeProVersionNotice');
		 deactivate_plugins('tables-generator-pro/index.php');
	 }
 }

include dirname(__FILE__) . '/app/SupsysticTables.php';

if (!defined('SUPSYSTIC_STB_DEBUG')) {
	define('SUPSYSTIC_STB_DEBUG', false);
}
if (!defined('SUPSYSTIC_TABLES_SHORTCODE_NAME')) {
    define('SUPSYSTIC_TABLES_SHORTCODE_NAME', 'supsystic-tables');
}
if (!defined('SUPSYSTIC_TABLES_PART_SHORTCODE_NAME')) {
	define('SUPSYSTIC_TABLES_PART_SHORTCODE_NAME', SUPSYSTIC_TABLES_SHORTCODE_NAME.'-part');
}
if (!defined('SUPSYSTIC_TABLES_CELL_SHORTCODE_NAME')) {
	define('SUPSYSTIC_TABLES_CELL_SHORTCODE_NAME', SUPSYSTIC_TABLES_SHORTCODE_NAME.'-cell-full');
}
if (!defined('SUPSYSTIC_TABLES_VALUE_SHORTCODE_NAME')) {
	define('SUPSYSTIC_TABLES_VALUE_SHORTCODE_NAME', SUPSYSTIC_TABLES_SHORTCODE_NAME.'-cell');
}
if (!defined('DTGS_PLUGIN_URL')) {
	define('DTGS_PLUGIN_URL', plugin_dir_url( __FILE__ ));
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
        return do_shortcode(sprintf('[%s id="%d"]', SUPSYSTIC_TABLES_SHORTCODE_NAME, (int)$id));
    }
}
