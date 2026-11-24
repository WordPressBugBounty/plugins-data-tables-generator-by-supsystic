<?php

/**
 * The wizard that creates tables. It opens over any page of the plugin by the #add link,
 * and it welcomes people right after the plugin is activated.
 */
class SupsysticTables_Wizard_Module extends SupsysticTables_Core_BaseModule
{
  const WELCOME_OPTION = 'supsystic_tbl_wizard_welcome';

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    if (is_admin()) {
      add_action('admin_init', [$this, 'redirectAfterActivation']);
      // This module starts after the Ui module has announced that it is ready, so it adds its assets by itself
      if ($this->getEnvironment()->isPluginPage()) {
        $this->addAssets($this->getEnvironment()->getModule('ui'));
      }
    }
    // Before other plugins' "+ New" items (they use 300), e.g. "Pricing Table" of the Pricing Table plugin
    add_action('admin_bar_menu', [$this, 'addAdminBarNewItems'], 290);
  }

  /**
   * Admin bar "+ New", with the Supsystic icon like every Supsystic plugin (plugins/.claude/fix/shared/adminbar-new-items.md):
   * "Data Table" opens the table wizard like the plugin's Create table buttons, "Woo Product Table" and
   * "Pricing Table" open it with that goal already chosen (#add-woo, #add-pricing).
   *
   * @param WP_Admin_Bar $adminBar
   */
  public function addAdminBarNewItems($adminBar)
  {
    $config = $this->getEnvironment()->getConfig();
    $menu = $config->get('plugin_menu');
    if (!is_object($adminBar) || !is_array($menu) || empty($menu['menu_slug']) || !current_user_can(!empty($menu['capability']) ? $menu['capability'] : 'manage_options')) {
      return;
    }
    $url = admin_url('admin.php?page=' . $menu['menu_slug']);
    $icon = '<img src="' . esc_url($config->get('plugin_url') . '/app/assets/img/supsystic-adminbar.png') . '" alt="" width="16" height="16" style="width:16px;height:16px;vertical-align:middle;margin:-3px 6px 0 0;display:inline-block;">';
    $adminBar->add_menu([
      'parent' => 'new-content',
      'id' => $menu['menu_slug'] . '-admin-bar-new-item',
      'title' => $icon . esc_html__('Data Table', 'supsystic_tables'),
      'href' => $url . '#add',
    ]);
    $adminBar->add_menu([
      'parent' => 'new-content',
      'id' => $menu['menu_slug'] . '-admin-bar-new-woo-item',
      'title' => $icon . esc_html__('Woo Product Table', 'supsystic_tables'),
      'href' => $url . '#add-woo',
    ]);
    $adminBar->add_menu([
      'parent' => 'new-content',
      'id' => $menu['menu_slug'] . '-admin-bar-new-pricing-item',
      'title' => $icon . esc_html__('Pricing Table', 'supsystic_tables'),
      'href' => $url . '#add-pricing',
    ]);
  }

  private function addAssets(SupsysticTables_Ui_Module $ui)
  {
    $environment = $this->getEnvironment();
    $hook = 'admin_enqueue_scripts';
    $version = $environment->getConfig()->get('plugin_version') . '-wizard-9';
    $cachingAllowed = $environment->isProd();

    $ui->add($ui->createStyle('supsystic-tables-wizard-css')->setHookName($hook)->setModuleSource($this, 'css/tables.wizard.css')->setVersion($version)->setCachingAllowed($cachingAllowed));
    $ui->add($ui->createScript('supsystic-tables-wizard-js')->setHookName($hook)->setModuleSource($this, 'js/tables.wizard.js')->addDependency('jquery')->addDependency('tables-core')->setVersion($version)->setCachingAllowed($cachingAllowed));
    add_action($hook, [$this, 'addWelcomeData'], 999);
  }

  public function addWelcomeData()
  {
    $welcome = isset($_GET['welcome']) || (defined('SUPSYSTIC_TBL_WELCOME_LANDING') && SUPSYSTIC_TBL_WELCOME_LANDING);
    wp_localize_script('supsystic-tables-wizard-js', 'STB_WIZARD', ['welcome' => $welcome ? '1' : '']);
  }

  /**
   * Once after the first activation: the wizard, on the welcome page.
   */
  public function redirectAfterActivation()
  {
    // A background request or a visitor without rights must not use up the welcome
    if (wp_doing_ajax() || !current_user_can('manage_options') || !get_option(self::WELCOME_OPTION)) {
      return;
    }
    delete_option(self::WELCOME_OPTION);

    if (is_network_admin() || isset($_GET['activate-multi'])) {
      return;
    }
    update_option('supsystic_tbl_default_page_visited', 1);
    wp_safe_redirect(admin_url('admin.php?page=supsystic-tables&module=overview&welcome=1#add'));
    exit;
  }
}
