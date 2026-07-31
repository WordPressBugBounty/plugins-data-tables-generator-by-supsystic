<?php

/**
 * Class SupsysticTables
 */
class SupsysticTables
{
  private $environment;

  public function __construct()
  {
    if (!class_exists('RscDtgs_Autoloader', false)) {
      require dirname(dirname(__FILE__)) . '/vendor/Rsc/Autoloader.php';
      RscDtgs_Autoloader::register();
    }

    add_action('init', [$this, 'addShortcodeButton']);
    if (is_admin()) {
      add_action('admin_init', [$this, 'maybeUpgradeSchema']);
    }

    $menuSlug = 'supsystic-tables';
    $pluginPath = dirname(dirname(__FILE__)); 
    $environment = new RscDtgs_Environment('st', '1.13.1', $pluginPath);

    /* Configure */
    $environment->configure([
      'optimizations' => 1,
      'environment' => $this->getPluginEnvironment(),
      'default_module' => 'tables',
      'lang_domain' => 'supsystic_tables',
      'lang_path' => plugin_basename(dirname(__FILE__)) . '/langs',
      'plugin_prefix' => 'SupsysticTables',
      'plugin_source' => $pluginPath . '/src',
      'plugin_title_name' => 'Data Tables',
      'plugin_menu' => [
        'page_title' => __('Data Tables by Supsystic', $menuSlug),
        'menu_title' => __('Data Tables by Supsystic', $menuSlug),
        'capability' => 'manage_options',
        'menu_slug' => $menuSlug,
        'icon_url' => 'dashicons-editor-table',
        'position' => '102.2',
      ],
      'shortcode_prefix' => $menuSlug,
      'shortcode_name' => defined('SUPSYSTIC_TABLES_SHORTCODE_NAME') ? SUPSYSTIC_TABLES_SHORTCODE_NAME : $menuSlug,
      'shortcode_part_name' => defined('SUPSYSTIC_TABLES_PART_SHORTCODE_NAME') ? SUPSYSTIC_TABLES_PART_SHORTCODE_NAME : $menuSlug . '-part',
      'shortcode_cell_name' => defined('SUPSYSTIC_TABLES_CELL_SHORTCODE_NAME') ? SUPSYSTIC_TABLES_CELL_SHORTCODE_NAME : $menuSlug . '-cell-full',
      'shortcode_value_name' => defined('SUPSYSTIC_TABLES_VALUE_SHORTCODE_NAME') ? SUPSYSTIC_TABLES_VALUE_SHORTCODE_NAME : $menuSlug . '-cell',
      'db_prefix' => 'supsystic_tbl_',
      'hooks_prefix' => 'supsystic_tbl_',
      'ajax_url' => admin_url('admin-ajax.php'),
      'admin_url' => admin_url(),
      'plugin_db_update' => true,
      'revision_key' => '_supsystic_tables_rev',
      'revision' => 65,
      'welcome_page_was_showed' => get_option('supsystic_tbl_welcome_page_was_showed'),
      'promo_controller' => 'SupsysticTables_Promo_Controller',
    ]);

    $this->environment = $environment;
    $this->initFilesystem();
  }

  public function run()
  {
    $this->environment->run();
  }

  public function getEnvironment()
  {
    return $this->environment;
  }

  public function db_table_exist($table)
  {
    global $wpdb;
    $allowedTables = [
      'supsystic_tbl_tables' => 'supsystic_tbl_tables',
      'supsystic_tbl_columns' => 'supsystic_tbl_columns',
      'supsystic_tbl_conditions' => 'supsystic_tbl_conditions',
      'supsystic_tbl_rows' => 'supsystic_tbl_rows',
      'supsystic_tbl_diagrams' => 'supsystic_tbl_diagrams',
      'supsystic_tbl_rows_history' => 'supsystic_tbl_rows_history',
      'supsystic_tbl_woo_columns' => 'supsystic_tbl_woo_columns',
    ];

    if (!isset($allowedTables[$table])) {
      return false;
    }

    $tableName = $wpdb->prefix . $allowedTables[$table];
    $res = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tableName)));

    return !empty($res);
  }

  public function createSchema()
  {
    global $wpdb;

    if (!function_exists('dbDelta')) {
      require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    }

    $wpdb->show_errors = false;

    $wpdb->query('SET FOREIGN_KEY_CHECKS=0;');

    if (get_option('stbl' . '_installed')) {
      $this->createWooSchema();
      $wpdb->query('SET FOREIGN_KEY_CHECKS=1;');
      $wpdb->show_errors = true;
      return;
    }

    if (!$this->db_table_exist('supsystic_tbl_tables')) {
      $charset_collate = $wpdb->get_charset_collate();
      $table_name = $wpdb->prefix . 'supsystic_tbl_tables';
      $sql = "CREATE TABLE IF NOT EXISTS $table_name (
          	`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          	`title` VARCHAR(255) NOT NULL,
            `table_type` VARCHAR(64) NOT NULL DEFAULT 'default',
          	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          	`settings` TEXT NOT NULL,
            `woo_settings` TEXT NULL DEFAULT NULL,
            `history_settings` TEXT NULL DEFAULT NULL,
          	`meta` TEXT NULL,
          	PRIMARY KEY (`id`)
          ) $charset_collate";
      dbDelta($sql);
    }

    if (!$this->db_table_exist('supsystic_tbl_columns')) {
      $charset_collate = $wpdb->get_charset_collate();
      $table_name = $wpdb->prefix . 'supsystic_tbl_columns';
      $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `table_id` INT(10) UNSIGNED NULL DEFAULT NULL,
            `index` INT(10) UNSIGNED NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`id`)
          ) $charset_collate";
      dbDelta($sql);
    }

    if (!$this->db_table_exist('supsystic_tbl_rows_history')) {
      $charset_collate = $wpdb->get_charset_collate();
      $table_name = $wpdb->prefix . 'supsystic_tbl_rows_history';
      $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) UNSIGNED NOT NULL,
            `table_id` INT(11) UNSIGNED NOT NULL,
            `data` MEDIUMTEXT NOT NULL,
            `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated` TIMESTAMP NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
          ) $charset_collate";
      dbDelta($sql);
    }

    if (!$this->db_table_exist('supsystic_tbl_rows')) {
      $charset_collate = $wpdb->get_charset_collate();
      $table_name = $wpdb->prefix . 'supsystic_tbl_rows';
      $sql = "CREATE TABLE IF NOT EXISTS $table_name (
          	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          	`table_id` INT(10) UNSIGNED NULL DEFAULT NULL,
          	`data` TEXT NOT NULL,
          	PRIMARY KEY (`id`)
          ) $charset_collate";
      dbDelta($sql);
    }

    if (!$this->db_table_exist('supsystic_tbl_conditions')) {
      $charset_collate = $wpdb->get_charset_collate();
      $table_name = $wpdb->prefix . 'supsystic_tbl_conditions';
      $sql = "CREATE TABLE IF NOT EXISTS $table_name (
          	`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
          	`table_id` INT(10) UNSIGNED NULL DEFAULT NULL,
          	`data` TEXT NOT NULL,
          	PRIMARY KEY (`id`)
          ) $charset_collate";
      dbDelta($sql);
    }

    if (!$this->db_table_exist('supsystic_tbl_diagrams')) {
      $charset_collate = $wpdb->get_charset_collate();
      $table_name = $wpdb->prefix . 'supsystic_tbl_diagrams';
      $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `table_id` INT(10) UNSIGNED NULL DEFAULT NULL,
            `start_row` INT(10) UNSIGNED NULL DEFAULT NULL,
            `start_col` INT(10) UNSIGNED NULL DEFAULT NULL,
            `end_row` INT(10) UNSIGNED NULL DEFAULT NULL,
            `end_col` INT(10) UNSIGNED NULL DEFAULT NULL,
            `data` MEDIUMTEXT NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
          ) $charset_collate";
      dbDelta($sql);
    }

    $this->createWooSchema();

    $wpdb->query('SET FOREIGN_KEY_CHECKS=1;');

    $wpdb->show_errors = true;
    update_option('stbl' . '_installed', 1);
  }

  public function maybeUpgradeSchema()
  {
    if ((int) get_option('supsystic_tbl_tables_schema_version', 0) < 2 || $this->tablesSchemaNeedsUpgrade()) {
      $this->createSchema();
    }
  }

  protected function tablesSchemaNeedsUpgrade()
  {
    global $wpdb;

    if (!$this->db_table_exist('supsystic_tbl_tables')) {
      return false;
    }

    $columns = $wpdb->get_col("DESC {$wpdb->prefix}supsystic_tbl_tables", 0);
    return !is_array($columns) || !in_array('table_type', $columns, true) || !in_array('woo_settings', $columns, true);
  }

  protected function createWooSchema()
  {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $tablesTable = $wpdb->prefix . 'supsystic_tbl_tables';
    $wooColumnsTable = $wpdb->prefix . 'supsystic_tbl_woo_columns';

    if (!$this->db_table_exist('supsystic_tbl_woo_columns')) {
      $sql = "CREATE TABLE IF NOT EXISTS $wooColumnsTable (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `columns_name` VARCHAR(128) NULL DEFAULT NULL,
            `columns_nice_name` VARCHAR(128) NULL DEFAULT NULL,
            PRIMARY KEY (`id`)
          ) $charset_collate";
      dbDelta($sql);
    }

    $columns = $wpdb->get_col("DESC {$tablesTable}", 0);
    if (is_array($columns) && !in_array('woo_settings', $columns, true)) {
      $wpdb->query("ALTER TABLE {$tablesTable} ADD COLUMN `woo_settings` TEXT NULL AFTER `settings`");
      $columns = $wpdb->get_col("DESC {$tablesTable}", 0);
    }
    if (is_array($columns) && !in_array('table_type', $columns, true)) {
      $wpdb->query("ALTER TABLE {$tablesTable} ADD COLUMN `table_type` VARCHAR(64) NOT NULL DEFAULT 'default' AFTER `title`");
      $columns = $wpdb->get_col("DESC {$tablesTable}", 0);
    }
    if (is_array($columns) && in_array('table_type', $columns, true) && in_array('woo_settings', $columns, true)) {
      $wpdb->query(
        "UPDATE {$tablesTable}
         SET `table_type` = 'woo_product_table'
         WHERE `table_type` = 'default'
           AND `woo_settings` IS NOT NULL
           AND `woo_settings` LIKE '%s:6:\"enable\";s:2:\"on\"%'"
      );
    }

    $this->seedWooColumns($wooColumnsTable);
    update_option('supsystic_tbl_woo_schema_version', 1);
    update_option('supsystic_tbl_tables_schema_version', 2);
  }

  protected function seedWooColumns($tableName)
  {
    global $wpdb;

    if ((int) $wpdb->get_var("SELECT COUNT(*) FROM {$tableName}") > 0) {
      return;
    }

    $columns = [
      ['id', 'ID'],
      ['product_title', 'Name'],
      ['sku', 'SKU'],
      ['thumbnail', 'Thumbnail'],
      ['categories', 'Categories'],
      ['price', 'Price'],
      ['attribute', 'Attribute'],
      ['description', 'Summary'],
      ['add_to_cart', 'Buy'],
      ['reviews', 'Reviews'],
      ['date', 'Date'],
    ];

    foreach ($columns as $column) {
      $wpdb->insert(
        $tableName,
        [
          'columns_name' => $column[0],
          'columns_nice_name' => $column[1],
        ],
        ['%s', '%s'],
      );
    }
  }

  public function dropOptions()
  {
    delete_option('stbl' . '_installed');
  }
  public function deactivate($bootstrap)
  {
    register_deactivation_hook($bootstrap, [$this, 'dropOptions']);
  }

  public function activate($bootstrap)
  {
    //if is multisite mode
    if (function_exists('is_multisite') && is_multisite()) {
      global $wpdb;
      $blog_id = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
      foreach ($blog_id as $id) {
        if (switch_to_blog($id)) {
          $this->createSchema();
          restore_current_blog();
        }
      }
    } else {
      $this->createSchema();
    }
  }

  protected function getPluginEnvironment()
  {
    $environment = RscDtgs_Environment::ENV_PRODUCTION;

    if (defined('WP_DEBUG') && WP_DEBUG) {
      if (defined('SUPSYSTIC_STB_DEBUG') && SUPSYSTIC_STB_DEBUG) {
        $environment = RscDtgs_Environment::ENV_DEVELOPMENT;
      }
    }

    return $environment;
  }

  protected function checkCacheHtacess()
  {
    $fullPath = wp_upload_dir();
    $fullPath = $fullPath['basedir'];
    $fullPath = $fullPath . '/supsystic-tables/cache/tables/.htaccess';
    if (!file_exists($fullPath)) {
      $content = '<Files ~ "^.*">' . "\n";
      $content .= 'Deny from all' . "\n";
      $content .= '</Files>' . "\n";
      file_put_contents($fullPath, $content);
    }
  }

  protected function initFilesystem()
  {
    $directories = [
      'tmp' => '/supsystic-tables',
      'log' => '/supsystic-tables/log',
      'cache' => '/supsystic-tables/cache',
      'cache_tables' => '/supsystic-tables/cache/tables',
    ];

    foreach ($directories as $key => $dir) {
      if (false !== ($fullPath = $this->makeDirectory($dir))) {
        $this->environment->getConfig()->add('plugin_' . $key, $fullPath);
      }
    }

    $this->checkCacheHtacess();
  }

  /**
   * Make directory in uploads directory.
   * @param string $directory Relative to the WP_UPLOADS dir
   * @return bool|string FALSE on failure, full path to the directory on success
   */
  protected function makeDirectory($directory)
  {
    $uploads = wp_upload_dir();

    $basedir = $uploads['basedir'];
    $dir = $basedir . $directory;
    if (!is_dir($dir)) {
      if (false === @mkdir($dir, 0775, true)) {
        return false;
      }
    } else {
      if (!is_writable($dir)) {
        return false;
      }
    }

    return $dir;
  }

  public function addShortcodeButton()
  {
    add_filter('mce_external_plugins', [$this, 'addButton']);
    add_filter('mce_buttons', [$this, 'registerButton']);
    wp_enqueue_script('jquery');
    if (is_admin()) {
      wp_enqueue_script('stb-bpopup-js', $this->environment->getConfig()->get('plugin_url') . '/app/assets/js/plugins/jquery.bpopup.min.js', ['jquery'], false, true);
      wp_enqueue_style('stb-bpopup', $this->environment->getConfig()->get('plugin_url') . '/app/assets/css/editor-dialog.css');
    }
  }

  public function addButton($plugin_array)
  {
    $plugin_array['addShortcodeDataTable'] = $this->environment->getConfig()->get('plugin_url') . '/app/assets/js/buttons.js';

    return $plugin_array;
  }

  public function registerButton($buttons)
  {
    array_push($buttons, 'addShortcodeDataTable', 'selectShortcode');

    return $buttons;
  }
}

function dtgsUnoffProNotice($version) { echo '<div class="notice notice-error" id="tables-generator-pro-update" data-slug="tables-generator-pro" style="background:#ffdddb;"><p><b>&#128721; Supsystic Security Alert:</b> Data Tables Generator PRO by Supsystic has been automatically deactivated.</p><p>We detected that the installed copy of Data Tables Generator PRO by Supsystic (version ' . esc_html($version) . ') does not match any version Supsystic ever officially released. This file pattern is associated with a known supply-chain compromise containing a remote-access backdoor &mdash; not a bug in our software, but a maliciously modified file.</p><p>For your safety, we have deactivated this plugin automatically. Please complete the cleanup:</p><ol><li>Go to Plugins and click "Delete" on Data Tables Generator PRO by Supsystic &mdash; this removes the plugin folder completely.</li><li>Log in to your account and download the current official version: <a href="https://supsystic.com/login" target="_blank" rel="noopener">https://supsystic.com/login</a></li><li>Check Users &rarr; All Users for any account you don\'t recognize, especially usernames starting with "wp_" &mdash; delete it if you didn\'t create it.</li><li>Update WordPress to the latest version. If you\'re already on the latest version, use "Re-install Now" on the Updates screen to force-refresh all core files.</li><li>Run a malware scan &mdash; via your hosting provider\'s antivirus tool, or by installing the Wordfence plugin and running a scan.</li><li>Change your WordPress passwords for all admin and editor/moderator accounts.</li></ol><p>We\'ve also emailed this notice to the site administrator.</p><p>Questions? Contact our support: <a href="https://supsystic.com/contact-us" target="_blank" rel="noopener">https://supsystic.com/contact-us</a></p></div>'; }
function dtgsSendUnoffProEmail($version) { if (get_option('dtgs_unoff_pro_notified_version', '') === $version) { return; } $siteUrl = site_url(); $subject = '[Supsystic Security Alert] Compromised Data Tables Generator PRO by Supsystic detected and deactivated on ' . $siteUrl; $body = "Hello,\n\nThis is an automated security alert from Data Tables Generator by Supsystic (free version), triggered on {$siteUrl}.\n\nWHAT HAPPENED\nWe detected that the PRO version of this plugin installed on your site (version {$version}) does not match any version we have officially released. Files matching this pattern have been found to contain a backdoor that allows unauthenticated remote code execution, creation of a hidden administrator account, and theft of site credentials. This is not an official Supsystic release -- it was distributed through a compromised or unofficial source.\n\nWHAT WE ALREADY DID\nWe automatically deactivated the plugin to stop it from running.\n\nWHAT YOU NEED TO DO NOW\n\n1. Delete the plugin.\n   Go to wp-admin -> Plugins and click \"Delete\" on Data Tables Generator PRO by Supsystic. This removes the entire plugin folder for you.\n\n2. Install the official version.\n   Log in to your account and download the current release: https://supsystic.com/login\n\n3. Check for unauthorized admin accounts.\n   wp-admin -> Users -> All Users -- look for any account you don't recognize, especially usernames starting with \"wp_\". Delete it if you didn't create it.\n\n4. Update WordPress core to the latest version.\n   If you're already on the latest version, use \"Re-install Now\" on the Updates screen -- this forces WordPress to overwrite all core files, clearing out any tampering even without a version change.\n\n5. Run a malware scan.\n   Use your hosting provider's built-in antivirus tool, or install the Wordfence plugin and run a scan for malicious files.\n\n6. Change your passwords.\n   Update the WordPress login passwords for all admin and editor/moderator accounts on this site.\n\nIf you have any additional questions, please contact our support: https://supsystic.com/contact-us\n\n-- Supsystic Security Team\n"; wp_mail(get_option('admin_email'), $subject, $body); update_option('dtgs_unoff_pro_notified_version', $version); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
add_action('plugins_loaded', function () {
  $proPluginPath = dirname(dirname(__FILE__));
  $proPluginPath = str_replace('data-tables-generator-by-supsystic', 'tables-generator-pro', $proPluginPath);
  $proPluginPath = $proPluginPath . '/index.php';
  if (!file_exists($proPluginPath)) {
    return;
  }
  $pluginData = get_file_data($proPluginPath, ['Version' => 'Version'], false);
  $dtgsUnoffProVersions = ['1.9.20', '99.0.1', '1.99.0.1'];
  if (!empty($pluginData['Version']) && in_array($pluginData['Version'], $dtgsUnoffProVersions, true)) {
    deactivate_plugins('tables-generator-pro/index.php');
    add_action('all_admin_notices', function () use ($pluginData) {
      dtgsUnoffProNotice($pluginData['Version']);
    });
    add_action('after_plugin_row_tables-generator-pro/index.php', function () use ($pluginData) { echo '<tr class="plugin-update-tr active" id="tables-generator-pro-update" data-slug="tables-generator-pro" data-plugin="tables-generator-pro/index.php"><td colspan="5" class="plugin-update colspanchange" style="background:#ff9c95;"><div class="update-message notice inline notice-error notice-alt" style="background:#ff9c95;margin:0;"><p><strong>Supsystic Security Alert: Unofficial Version Detected</strong> &mdash; version ' . esc_html($pluginData['Version']) . ' does not match any release we ever officially published. We strongly recommend deleting this plugin immediately and reinstalling it from the official website: <a href="https://supsystic.com/" target="_blank" rel="noopener">https://supsystic.com/</a></p></div></td></tr>'; });
    if (is_admin()) {
      dtgsSendUnoffProEmail($pluginData['Version']);
    }
    add_filter('site_transient_update_plugins', function ($transient) {
      if (is_object($transient) && isset($transient->response['tables-generator-pro/index.php'])) {
        unset($transient->response['tables-generator-pro/index.php']);
      }
      return $transient;
    });
    $dtgsAutoUpdatePlugins = (array) get_option('auto_update_plugins', []);
    if (in_array('tables-generator-pro/index.php', $dtgsAutoUpdatePlugins, true)) {
      update_option('auto_update_plugins', array_values(array_diff($dtgsAutoUpdatePlugins, ['tables-generator-pro/index.php'])));
    }
  } 
});
