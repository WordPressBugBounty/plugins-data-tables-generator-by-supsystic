<?php

/**
 * Moves tables from other table plugins, right from the database of the site.
 * It offers this by itself on the pages of the plugin, and from a button on the Settings page.
 */
class SupsysticTables_Importcompetitor_Module extends SupsysticTables_Core_BaseModule
{
  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    if (!is_admin()) {
      return;
    }
    $environment = $this->getEnvironment();
    $environment->getDispatcher()->on('settings_cards', [$this, 'renderSettingsCard']);
    // Modules start in the order of their names, so the Ui module is not ready here yet
    if ($environment->isPluginPage()) {
      add_action('admin_enqueue_scripts', [$this, 'addAssets'], 999);
    }
  }

  public function addAssets()
  {
    if (!current_user_can('manage_options')) {
      return;
    }
    $url = untrailingslashit(plugin_dir_url(__FILE__)) . '/assets';
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-import-2';
    wp_enqueue_style('supsystic-tables-import-competitor-css', $url . '/css/importcompetitor.css', [], $version);
    wp_enqueue_script('supsystic-tables-import-competitor-js', $url . '/js/importcompetitor.js', ['jquery', 'tables-core'], $version, true);

    $manager = $this->getManager();
    wp_localize_script('supsystic-tables-import-competitor-js', 'STB_IMPORT_COMPETITOR', [
      'offer' => $this->isOfferPage() ? $manager->getOffer() : null,
      'strings' => $manager->getStrings(),
    ]);
  }

  /**
   * The offer does not get in the way of work on a table or of the welcome wizard.
   */
  private function isOfferPage()
  {
    $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

    return $action !== 'view' && !isset($_GET['welcome']);
  }

  private function getManager()
  {
    return $this->getEnvironment()->getModule('core')->getModelsFactory()->get('manager', 'importcompetitor');
  }

  public function renderSettingsCard()
  {
    if (!current_user_can('manage_options')) {
      return;
    }
    $sources = [];
    $manager = $this->getManager();
    foreach ($manager->getSources() as $source) {
      $sources[] = ['name' => $source->getName(), 'count' => count($source->getIds())];
    }
    $this->getEnvironment()->getTwig()->display('@importcompetitor/settingsCard.twig', ['sources' => $sources]);
  }
}
