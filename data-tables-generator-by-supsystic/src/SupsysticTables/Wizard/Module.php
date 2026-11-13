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
  }

  private function addAssets(SupsysticTables_Ui_Module $ui)
  {
    $environment = $this->getEnvironment();
    $hook = 'admin_enqueue_scripts';
    $version = $environment->getConfig()->get('plugin_version') . '-wizard-8';
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
