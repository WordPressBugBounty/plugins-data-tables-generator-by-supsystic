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

		add_action('init', array($this, 'addShortcodeButton'));

        $menuSlug = 'supsystic-tables';
        $pluginPath = dirname(dirname(__FILE__));
		    $environment = new RscDtgs_Environment('st', '1.10.36', $pluginPath);

        /* Configure */
        $environment->configure(
            array(
                'optimizations'    	=> 1,
                'environment'      	=> $this->getPluginEnvironment(),
                'default_module'   	=> 'tables',
                'lang_domain'      	=> 'supsystic_tables',
                'lang_path'        	=> plugin_basename(dirname(__FILE__)) . '/langs',
                'plugin_prefix'    	=> 'SupsysticTables',
                'plugin_source'    	=> $pluginPath . '/src',
				        'plugin_title_name' => 'Data Tables',
                'plugin_menu'      	=> array(
                    'page_title' => __('Data Tables by Supsystic', $menuSlug),
                    'menu_title' => __('Data Tables by Supsystic', $menuSlug),
                    'capability' => 'manage_options',
                    'menu_slug'  => $menuSlug,
                    'icon_url'   => 'dashicons-editor-table',
                    'position'   => '102.2',
                ),
                'shortcode_prefix'   				=> $menuSlug,
                'shortcode_name'   					=> defined('SUPSYSTIC_TABLES_SHORTCODE_NAME') ? SUPSYSTIC_TABLES_SHORTCODE_NAME : $menuSlug,
                'shortcode_part_name'   			=> defined('SUPSYSTIC_TABLES_PART_SHORTCODE_NAME') ? SUPSYSTIC_TABLES_PART_SHORTCODE_NAME : $menuSlug . '-part',
      				  'shortcode_cell_name'   			=> defined('SUPSYSTIC_TABLES_CELL_SHORTCODE_NAME') ? SUPSYSTIC_TABLES_CELL_SHORTCODE_NAME : $menuSlug . '-cell-full',
      				  'shortcode_value_name'				=> defined('SUPSYSTIC_TABLES_VALUE_SHORTCODE_NAME') ? SUPSYSTIC_TABLES_VALUE_SHORTCODE_NAME : $menuSlug . '-cell',
                'db_prefix'       					=> 'supsystic_tbl_',
                'hooks_prefix'						=> 'supsystic_tbl_',
				        'ajax_url'		     				=> admin_url('admin-ajax.php'),
                'admin_url'							=> admin_url(),
                'plugin_db_update' 					=> true,
                'revision_key'     					=> '_supsystic_tables_rev',
                'revision'							=> 61,
        				'welcome_page_was_showed'			=> get_option('supsystic_tbl_welcome_page_was_showed'),
        				'promo_controller' 					=> 'SupsysticTables_Promo_Controller'
            )
        );

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

    public function db_table_exist($table) {
      global $wpdb;
      switch ($table) {
         case 'supsystic_tbl_tables':
            $res = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}supsystic_tbl_tables'");
         break;
         case 'supsystic_tbl_columns':
            $res = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}supsystic_tbl_columns'");
         break;
         case 'supsystic_tbl_conditions':
            $res = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}supsystic_tbl_conditions'");
         break;
         case 'supsystic_tbl_rows':
            $res = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}supsystic_tbl_rows'");
         break;
         case 'supsystic_tbl_diagrams':
            $res = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}supsystic_tbl_diagrams'");
         break;
         case 'supsystic_rows_history':
            $res = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}supsystic_rows_history'");
         break;
      }
      return !empty($res);
   }

   public function createSchema()
    {
        global $wpdb;
  	    if(get_option('stbl'.'_installed')) return;

        if (!function_exists('dbDelta')) {
            require_once(ABSPATH.'wp-admin/includes/upgrade.php');
        }

        $wpdb->show_errors = false;

        $wpdb->query('SET FOREIGN_KEY_CHECKS=0;');

        if (!$this->db_table_exist('supsystic_tbl_tables')) {
          $charset_collate = $wpdb->get_charset_collate();
    			$table_name = $wpdb->prefix . 'supsystic_tbl_tables';
    			$sql = "CREATE TABLE IF NOT EXISTS $table_name (
          	`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          	`title` VARCHAR(255) NOT NULL,
          	`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          	`settings` TEXT NOT NULL,
            `history_settings` TEXT NULL DEFAULT NULL,
          	`meta` TEXT NULL,
          	PRIMARY KEY (`id`)
          ) $charset_collate";
    			dbDelta( $sql );
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
    			dbDelta( $sql );
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
    			dbDelta( $sql );
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
    			dbDelta( $sql );
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
    			dbDelta( $sql );
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
    			dbDelta( $sql );
        }

        $wpdb->query('SET FOREIGN_KEY_CHECKS=1;');

        $wpdb->show_errors = true;
        update_option('stbl'.'_installed', 1);

  }

  public function dropOptions()
	{
		delete_option('stbl'.'_installed');
	}
    public function deactivate($bootstrap)
    {
        register_deactivation_hook($bootstrap, array($this, 'dropOptions'));
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
      $fullPath = $fullPath.'/supsystic-tables/cache/tables/.htaccess';
      if(!file_exists($fullPath)){
          $content = '<Files ~ "^.*">' . "\n";
          $content .= 'Deny from all' . "\n";
          $content .= '</Files>' . "\n";
          file_put_contents($fullPath, $content);
      }
    }

    protected function initFilesystem()
    {
        $directories = array(
            'tmp' => '/supsystic-tables',
            'log' => '/supsystic-tables/log',
            'cache' => '/supsystic-tables/cache',
            'cache_tables' => '/supsystic-tables/cache/tables',
        );

        foreach ($directories as $key => $dir) {
            if (false !== $fullPath = $this->makeDirectory($dir)) {
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
            if (! is_writable($dir)) {
                return false;
            }
        }

        return $dir;
    }

	public function addShortcodeButton() {
		add_filter('mce_external_plugins', array($this, 'addButton'));
		add_filter('mce_buttons', array($this, 'registerButton'));
		wp_enqueue_script('jquery');
		if(is_admin()) {
			wp_enqueue_script('stb-bpopup-js', $this->environment->getConfig()->get('plugin_url') . '/app/assets/js/plugins/jquery.bpopup.min.js', array('jquery'), false, true);
			wp_enqueue_style('stb-bpopup', $this->environment->getConfig()->get('plugin_url') . '/app/assets/css/editor-dialog.css');
		}
	}

	public function addButton( $plugin_array ) {
		$plugin_array['addShortcodeDataTable'] = $this->environment->getConfig()->get('plugin_url') . '/app/assets/js/buttons.js';

		return $plugin_array;
	}

	public function registerButton( $buttons ) {
		array_push( $buttons, 'addShortcodeDataTable', 'selectShortcode' );

		return $buttons;
	}
}
