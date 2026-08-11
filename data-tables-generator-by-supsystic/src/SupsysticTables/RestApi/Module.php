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

    // If rest_api_init already fired (e.g. another plugin called rest_get_server()
    // early during plugins_loaded), register routes immediately.
    // Otherwise hook normally.
    if (did_action('rest_api_init')) {
      $this->registerRoutes();
    } else {
      add_action('rest_api_init', [$this, 'registerRoutes']);
    }
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
