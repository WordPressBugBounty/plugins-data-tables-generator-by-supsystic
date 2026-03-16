<?php
/**
 * Class SupsysticTables_Diagram_Module
 */
class SupsysticTables_Woocommerce_Module extends SupsysticTables_Core_BaseModule
{
  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    $this->renderWoocommerceSection();
  }

  /**
   * Runs the callbacks after the table editor tabs rendered.
   */
  private function renderWoocommerceSection()
  {
    $dispatcher = $this->getEnvironment()->getDispatcher();

    $dispatcher->on('tabs_rendered', [$this, 'afterTabsRendered']);
    $dispatcher->on('tabs_content_rendered', [$this, 'afterTabsContentRendered']);
  }

  /**
   * Renders the "Diagrams" tab.
   * @param \stdClass $table Current table
   */
  public function afterTabsRendered()
  {
    $twig = $this->getEnvironment()->getTwig();
    $twig->display('@woocommerce/partials/tab.twig', []);
  }

  /**
   * Renders the "Diagrams" tab content.
   * @param \stdClass $table Current table
   */
  public function afterTabsContentRendered($table)
  {
    $twig = $this->getEnvironment()->getTwig();
    $dispatcher = $this->getEnvironment()->getDispatcher();

    $twig->display($dispatcher->apply('woocommerce_tabs_content_template', ['@woocommerce/partials/tabContent.twig']), $dispatcher->apply('woocommerce_tabs_content_data', [['table' => $table]]));
  }

  /**
   * @return \SupsysticTables_Core_ModelsFactory
   */
  protected function getModelsFactory()
  {
    /** @var SupsysticTables_Core_Module $core */
    $core = $this->getEnvironment()->getModule('core');

    return $core->getModelsFactory();
  }
}
