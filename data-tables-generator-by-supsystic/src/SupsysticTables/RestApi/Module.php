<?php

/**
 * Class SupsysticTables_Restapi_Module
 *
 * Registers WordPress REST API routes for Data Tables by Supsystic.
 * Follows the same pattern as other modules (Tables, Diagram, etc.)
 *
 * To activate — add 'restapi' to the modules list in app/SupsysticTables.php:
 *   $environment->configure([ ... ]);
 *   // The Resolver auto-discovers this module via glob() — no extra config needed.
 */
class SupsysticTables_Restapi_Module extends SupsysticTables_Core_BaseModule
{
  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    // Register REST API routes on the standard WP hook
    add_action('rest_api_init', [$this, 'registerRoutes']);
  }

  /**
   * Registers all REST API routes by delegating to the Controller.
   */
  public function registerRoutes()
  {
    /** @var SupsysticTables_Restapi_Controller $controller */
    $controller = $this->getController();

    if ($controller) {
      $controller->registerRoutes();
    }
  }
}
