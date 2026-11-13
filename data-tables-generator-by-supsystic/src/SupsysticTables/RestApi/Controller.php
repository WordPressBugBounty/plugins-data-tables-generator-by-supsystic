<?php

/**
 * Class SupsysticTables_Restapi_Controller
 *
 * Main REST API controller. Instantiates sub-controllers and registers
 * all routes under the 'supsystic-tables/v1' namespace.
 *
 * Authentication: WordPress Application Passwords (WP 5.6+).
 * Authorization header: Basic base64(username:app_password)
 *
 * Example:
 *   curl -u "admin:xxxx xxxx xxxx" https://site.com/wp-json/supsystic-tables/v1/tables
 */
class SupsysticTables_Restapi_Controller extends SupsysticTables_Core_BaseController
{
  const REST_NAMESPACE = 'supsystic-tables/v1';

  /**
   * Registers all REST API routes by delegating to sub-controllers.
   */
  public function registerRoutes()
  {
    $env = $this->getEnvironment();
    $req = $this->getRequest();

    $subControllers = [new SupsysticTables_Restapi_Controller_Tables($env, $req), new SupsysticTables_Restapi_Controller_Rows($env, $req), new SupsysticTables_Restapi_Controller_Settings($env, $req), new SupsysticTables_Restapi_Controller_ImportExport($env, $req)];

    foreach ($subControllers as $controller) {
      $controller->register(self::REST_NAMESPACE);
    }
  }

  /**
   * Default permission callback for all REST endpoints.
   * Requires user to be logged in and have manage_options capability.
   *
   * @param WP_REST_Request $request
   * @return bool|WP_Error
   */
  public static function checkPermission(WP_REST_Request $request)
  {
    if (!is_user_logged_in()) {
      return new WP_Error('rest_forbidden', 'Authentication required.', ['status' => 401]);
    }

    if (!current_user_can('manage_options')) {
      return new WP_Error('rest_forbidden', 'You do not have permission to access this resource.', ['status' => 403]);
    }

    return true;
  }

}
