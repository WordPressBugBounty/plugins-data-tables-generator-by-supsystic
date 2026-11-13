<?php

/**
 * Class SupsysticTables_Overview_Module
 */
class SupsysticTables_Overview_Module extends SupsysticTables_Core_BaseModule
{
  public function onInit()
  {
    parent::onInit();

    $this->registerMenu();

    $config = $this->getEnvironment()->getConfig();
    $config->add('post_url', 'http://supsystic.com/news/main.html');
    $config->add('mail', 'support@supsystic.zendesk.com');
  }

  /**
   * {@inheritdoc}
   */
  public function afterUiLoaded(SupsysticTables_Ui_Module $ui)
  {
    parent::afterUiLoaded($ui);

    $hook = 'admin_enqueue_scripts';
    $environment = $this->getEnvironment();
    $version = $environment->getConfig()->get('plugin_version');
    $cachingAllowed = $environment->isProd();

    // This theme was historically provided by the Overview module for shared
    // dialogs (including Add new table), so it must remain available globally.
    $ui->add($ui->createStyle('jquery-ui-min')->setHookName($hook)->setLocalSource('css/jquery-ui.min.css')->setCachingAllowed($cachingAllowed)->setVersion($version));

    if (!$environment->isModule('overview')) {
      return;
    }

    $ui->add($ui->createStyle('supsystic-tables-overview-css')->setHookName($hook)->setModuleSource($this, 'css/overview.css')->setCachingAllowed($cachingAllowed)->setVersion($version . '-overview-7'));
    $ui->add($ui->createScript('supsystic-tables-overview-js')->setHookName($hook)->setModuleSource($this, 'js/overview.settings.js')->setCachingAllowed($cachingAllowed)->setVersion($version . '-overview-7'));
    $ui->add($ui->createStyle('supsystic-tables-overview-classic-demo')->setHookName($hook)->setModuleSource($this, 'css/classic-demo.css')->setCachingAllowed($cachingAllowed)->setVersion($version . '-demo-2'));
    $ui->add($ui->createScript('supsystic-tables-overview-classic-demo')->setHookName($hook)->setModuleSource($this, 'js/classic-demo.js')->addDependency('jquery')->setCachingAllowed($cachingAllowed)->setVersion($version . '-demo-2'));
    if ($this->showProOffer()) {
      $ui->add($ui->createStyle('supsystic-tables-overview-pro-offer-css')->setHookName($hook)->setModuleSource($this, 'css/pro-offer.css')->setCachingAllowed($cachingAllowed)->setVersion($version . '-pro-offer-3'));
      $ui->add($ui->createScript('supsystic-tables-overview-pro-offer-js')->setHookName($hook)->setModuleSource($this, 'js/pro-offer.js')->setCachingAllowed($cachingAllowed)->setVersion($version . '-pro-offer-2'));
    }
  }

  /**
   * The Free vs PRO comparison and the PRO pricing are shown while PRO is not active: its plugin is not loaded (not
   * installed or deactivated) and the PRO features are off.
   * @return bool
   */
  public function showProOffer()
  {
    $show = !$this->getEnvironment()->isPro() && !class_exists('SupsysticTablesPro_Loader', false);

    return (bool) apply_filters('supsystic_tables_show_pro_offer', $show);
  }

  private function registerMenu()
  {
    $environment = $this->getEnvironment();
    $menu = $environment->getMenu();
    $plugin_menu = $this->getConfig()->get('plugin_menu');
    $capability = $plugin_menu['capability'];

    $submenu = $menu->createSubmenuItem();
    $submenu
      ->setCapability($capability)
      ->setMenuSlug($menu->getMenuSlug() . '&module=' . $this->getModuleName())
      ->setMenuTitle($environment->translate('Overview'))
      ->setPageTitle($environment->translate('Overview'))
      ->setModuleName('overview');
    // Avoid conflicts with old vendor version
    if (method_exists($submenu, 'setSortOrder')) {
      $submenu->setSortOrder(10);
    }

    $menu->addSubmenuItem('overview', $submenu);
  }
}
