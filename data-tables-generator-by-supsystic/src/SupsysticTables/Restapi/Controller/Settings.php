<?php

/**
 * Class SupsysticTables_Restapi_Controller_Settings
 *
 * Read-only settings/meta endpoints (FREE). Write operations are provided by the
 * Data Tables AI PRO plugin when its license is active.
 *
 * Routes:
 *   GET  /wp-json/supsystic-tables/v1/tables/{id}/settings
 *   GET  /wp-json/supsystic-tables/v1/tables/{id}/meta
 */
class SupsysticTables_Restapi_Controller_Settings extends SupsysticTables_Core_BaseController
{
  /**
   * @param string $namespace
   */
  public function register($namespace)
  {
    $permission = ['SupsysticTables_Restapi_Controller', 'checkPermission'];

    register_rest_route($namespace, '/tables/(?P<id>\d+)/settings', [
      'methods' => WP_REST_Server::READABLE,
      'callback' => [$this, 'getSettings'],
      'permission_callback' => $permission,
    ]);

    register_rest_route($namespace, '/tables/(?P<id>\d+)/meta', [
      'methods' => WP_REST_Server::READABLE,
      'callback' => [$this, 'getMeta'],
      'permission_callback' => $permission,
    ]);
  }

  /**
   * GET /tables/{id}/settings
   */
  public function getSettings(WP_REST_Request $request)
  {
    try {
      $id = (int) $request->get_param('id');
      $tablesModel = $this->getModel('tables');
      $table = $tablesModel->getById($id);

      if (!$table) {
        return $this->restError(sprintf('Table with ID %d not found.', $id), 404);
      }

      $settings = $tablesModel->getSettings($id);

      return rest_ensure_response([
        'table_id' => $id,
        'settings' => is_array($settings) ? $settings : [],
      ]);
    } catch (Exception $e) {
      return $this->restError($e->getMessage());
    }
  }

  /**
   * GET /tables/{id}/meta
   */
  public function getMeta(WP_REST_Request $request)
  {
    try {
      $id = (int) $request->get_param('id');
      $tablesModel = $this->getModel('tables');
      $table = $tablesModel->getById($id);

      if (!$table) {
        return $this->restError(sprintf('Table with ID %d not found.', $id), 404);
      }

      $meta = $tablesModel->getMeta($id);

      return rest_ensure_response([
        'table_id' => $id,
        'meta' => is_array($meta) ? $meta : [],
      ]);
    } catch (Exception $e) {
      return $this->restError($e->getMessage());
    }
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  private function restError($message, $status = 500)
  {
    return new WP_REST_Response(['success' => false, 'message' => $message], $status);
  }
}
