<?php
/**
 * Class SupsysticTables_Diagram_Module
 */
class SupsysticTables_Diagram_Module extends SupsysticTables_Core_BaseModule
{
  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    $this->renderDiagramsSection();
  }

  /**
   * Runs the callbacks after the table editor tabs rendered.
   */
  private function renderDiagramsSection()
  {
    $dispatcher = $this->getEnvironment()->getDispatcher();

    $dispatcher->on('tabs_rendered', [$this, 'afterTabsRendered']);
    $dispatcher->on('tabs_content_rendered', [$this, 'afterTabsContentRendered']);
  }

  /**
   * Renders the "Diagrams" tab.
   * @param \stdClass $table Current table
   */
  public function afterTabsRendered($table = null)
  {
    if ($this->isWooProductTable($table)) {
      return;
    }

    $twig = $this->getEnvironment()->getTwig();
    $twig->display('@diagram/partials/tab.twig', []);
  }

  /**
   * Renders the "Diagrams" tab content.
   * @param \stdClass $table Current table
   */
  public function afterTabsContentRendered($table)
  {
    if ($this->isWooProductTable($table)) {
      return;
    }

    $twig = $this->getEnvironment()->getTwig();
    $dispatcher = $this->getEnvironment()->getDispatcher();

    $twig->display($dispatcher->apply('diagram_tabs_content_template', ['@diagram/partials/tabContent.twig']), $dispatcher->apply('diagram_tabs_content_data', [['table' => $table]]));
  }

  private function isWooProductTable($table)
  {
    if (!is_object($table)) {
      return false;
    }
    if (!empty($table->table_type) && $table->table_type === 'woo_product_table') {
      return true;
    }
    if (!empty($table->settings) && is_array($table->settings) && !empty($table->settings['tableType']) && $table->settings['tableType'] === 'woo_product_table') {
      return true;
    }

    return !empty($table->woo_settings) && is_array($table->woo_settings) && !empty($table->woo_settings['woocommerce']) && is_array($table->woo_settings['woocommerce']) && !empty($table->woo_settings['woocommerce']['enable']) && in_array($table->woo_settings['woocommerce']['enable'], ['on', '1', 1, true], true);
  }
}
