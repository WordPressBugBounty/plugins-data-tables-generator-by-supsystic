<?php

/**
 * Class SupsysticTables_Restapi_Controller_Rows
 *
 * Read-only row/column endpoints (FREE). Write operations are provided by the
 * Data Tables AI PRO plugin when its license is active.
 *
 * Routes:
 *   GET  /wp-json/supsystic-tables/v1/tables/{id}/rows
 *   GET  /wp-json/supsystic-tables/v1/tables/{id}/columns
 */
class SupsysticTables_Restapi_Controller_Rows extends SupsysticTables_Core_BaseController
{
  /**
   * @param string $namespace
   */
  public function register($namespace)
  {
    $permission = ['SupsysticTables_Restapi_Controller', 'checkPermission'];

    // All rows
    register_rest_route($namespace, '/tables/(?P<id>\d+)/rows', [
      'methods' => WP_REST_Server::READABLE,
      'callback' => [$this, 'getRows'],
      'permission_callback' => $permission,
    ]);

    // Columns
    register_rest_route($namespace, '/tables/(?P<id>\d+)/columns', [
      'methods' => WP_REST_Server::READABLE,
      'callback' => [$this, 'getColumns'],
      'permission_callback' => $permission,
    ]);
  }

  /**
   * GET /tables/{id}/rows
   */
  public function getRows(WP_REST_Request $request)
  {
    try {
      $id = (int) $request->get_param('id');
      $table = $this->getModel('tables')->getById($id);

      if (!$table) {
        return $this->restError(sprintf('Table with ID %d not found.', $id), 404);
      }

      $rows = $this->getModel('tables')->getRows($id);

      return rest_ensure_response([
        'table_id' => $id,
        'count' => count($rows),
        'rows' => $rows,
      ]);
    } catch (Exception $e) {
      return $this->restError($e->getMessage());
    }
  }

  /**
   * GET /tables/{id}/columns
   */
  public function getColumns(WP_REST_Request $request)
  {
    try {
      $id = (int) $request->get_param('id');
      $table = $this->getModel('tables')->getById($id);

      if (!$table) {
        return $this->restError(sprintf('Table with ID %d not found.', $id), 404);
      }

      $columns = $this->getModel('tables')->getColumns($id);

      return rest_ensure_response([
        'table_id' => $id,
        'columns' => $columns,
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
