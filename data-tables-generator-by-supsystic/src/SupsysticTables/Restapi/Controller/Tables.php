<?php

/**
 * Class SupsysticTables_Restapi_Controller_Tables
 *
 * Read-only table endpoints (FREE). Write operations are provided by the
 * Data Tables AI PRO plugin when its license is active.
 *
 * Routes:
 *   GET  /wp-json/supsystic-tables/v1/tables
 *   GET  /wp-json/supsystic-tables/v1/tables/{id}
 */
class SupsysticTables_Restapi_Controller_Tables extends SupsysticTables_Core_BaseController
{
  /**
   * @param string $namespace
   */
  public function register($namespace)
  {
    $permission = ['SupsysticTables_Restapi_Controller', 'checkPermission'];

    // Collection
    register_rest_route($namespace, '/tables', [
      'methods' => WP_REST_Server::READABLE,
      'callback' => [$this, 'index'],
      'permission_callback' => $permission,
      'args' => [
        'page' => ['type' => 'integer', 'default' => 1, 'minimum' => 1],
        'per_page' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
        'search' => ['type' => 'string', 'default' => ''],
      ],
    ]);

    // Single item
    register_rest_route($namespace, '/tables/(?P<id>\d+)', [
      'methods' => WP_REST_Server::READABLE,
      'callback' => [$this, 'show'],
      'permission_callback' => $permission,
      'args' => [
        'include_data' => ['type' => 'boolean', 'default' => true],
      ],
    ]);
  }

  /**
   * GET /tables
   */
  public function index(WP_REST_Request $request)
  {
    try {
      $tablesModel = $this->getModel('tables');
      $page = (int) $request->get_param('page');
      $perPage = (int) $request->get_param('per_page');
      $search = (string) $request->get_param('search');
      $offset = ($page - 1) * $perPage;

      if (!empty($search)) {
        $rows = $tablesModel->getListTbl([
          'search' => ['text_like' => $search],
          'orderBy' => 'id',
          'sortOrder' => 'DESC',
          'rowsLimit' => $perPage,
          'limitStart' => $offset,
        ]);
        $total = count(
          $tablesModel->getListTbl([
            'search' => ['text_like' => $search],
            'orderBy' => 'id',
            'sortOrder' => 'DESC',
            'rowsLimit' => 99999,
            'limitStart' => 0,
          ]),
        );
      } else {
        $all = $tablesModel->getAll(['order' => 'DESC', 'order_by' => 'id', 'limit' => $perPage, 'offset' => $offset]);
        $rows = $all;
        $total = $tablesModel->getTablesCount();
      }

      $tables = [];
      foreach ((array) $rows as $row) {
        $tables[] = $this->formatTableMeta($row);
      }

      return rest_ensure_response([
        'data' => $tables,
        'total' => (int) $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => (int) ceil($total / $perPage),
      ]);
    } catch (Exception $e) {
      return $this->restError($e->getMessage());
    }
  }

  /**
   * GET /tables/{id}
   */
  public function show(WP_REST_Request $request)
  {
    try {
      $id = (int) $request->get_param('id');
      $includeData = (bool) $request->get_param('include_data');
      $tablesModel = $this->getModel('tables');
      $table = $tablesModel->getById($id);

      if (!$table) {
        return $this->restError(sprintf('Table with ID %d not found.', $id), 404);
      }

      return rest_ensure_response($this->formatTable($table, $includeData));
    } catch (Exception $e) {
      return $this->restError($e->getMessage());
    }
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  private function formatTableMeta($table)
  {
    $table = (object) $table;
    return [
      'id' => (int) $table->id,
      'title' => $table->title,
      'created_at' => isset($table->created_at) ? $table->created_at : null,
      'shortcode' => '[supsystic-tables id="' . (int) $table->id . '"]',
    ];
  }

  private function formatTable($table, $includeData = true)
  {
    $result = $this->formatTableMeta($table);

    $settings = [];
    if (!empty($table->settings)) {
      $decoded = is_string($table->settings) ? @unserialize($table->settings) : $table->settings;
      $settings = is_array($decoded) ? $decoded : [];
    }
    $result['settings'] = $settings;

    $meta = [];
    if (!empty($table->meta)) {
      $decoded = is_string($table->meta) ? @unserialize($table->meta) : $table->meta;
      $meta = is_array($decoded) ? $decoded : [];
    }
    $result['meta'] = $meta;

    if ($includeData) {
      $tablesModel = $this->getModel('tables');
      $result['rows'] = $tablesModel->getRows((int) $table->id);
      $result['cols'] = $tablesModel->getColumns((int) $table->id);
    }

    return $result;
  }

  private function restError($message, $status = 500)
  {
    return new WP_REST_Response(['success' => false, 'message' => $message], $status);
  }
}
