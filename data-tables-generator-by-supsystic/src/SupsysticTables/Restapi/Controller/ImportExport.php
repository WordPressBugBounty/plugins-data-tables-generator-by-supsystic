<?php

/**
 * Class SupsysticTables_Restapi_Controller_ImportExport
 *
 * Read-only export endpoints (FREE). Import operations are provided by the
 * Data Tables AI PRO plugin when its license is active.
 *
 * Routes:
 *   GET  /wp-json/supsystic-tables/v1/tables/{id}/export/csv
 *   GET  /wp-json/supsystic-tables/v1/tables/{id}/export/json
 */
class SupsysticTables_Restapi_Controller_ImportExport extends SupsysticTables_Core_BaseController
{
  /**
   * @param string $namespace
   */
  public function register($namespace)
  {
    $permission = ['SupsysticTables_Restapi_Controller', 'checkPermission'];

    // CSV Export
    register_rest_route($namespace, '/tables/(?P<id>\d+)/export/csv', [
      'methods' => WP_REST_Server::READABLE,
      'callback' => [$this, 'exportCsv'],
      'permission_callback' => $permission,
      'args' => [
        'include_header' => ['type' => 'boolean', 'default' => true],
        'delimiter' => ['type' => 'string', 'default' => ','],
      ],
    ]);

    // JSON Export
    register_rest_route($namespace, '/tables/(?P<id>\d+)/export/json', [
      'methods' => WP_REST_Server::READABLE,
      'callback' => [$this, 'exportJson'],
      'permission_callback' => $permission,
      'args' => [
        'include_header' => ['type' => 'boolean', 'default' => true],
      ],
    ]);
  }

  /**
   * GET /tables/{id}/export/csv
   */
  public function exportCsv(WP_REST_Request $request)
  {
    try {
      $id = (int) $request->get_param('id');
      $includeHeader = (bool) $request->get_param('include_header');
      $delimiter = $request->get_param('delimiter');
      $tablesModel = $this->getModel('tables');
      $table = $tablesModel->getById($id);

      if (!$table) {
        return $this->restError(sprintf('Table with ID %d not found.', $id), 404);
      }

      $rows = $tablesModel->getRows($id);
      $columns = $tablesModel->getColumns($id);
      $csv = [];

      if ($includeHeader && !empty($columns)) {
        $csv[] = $this->toCsvLine($columns, $delimiter);
      }

      foreach ($rows as $row) {
        if (empty($row['cells'])) {
          continue;
        }
        $line = [];
        foreach ($row['cells'] as $cell) {
          $value = isset($cell['calculatedValue']) && $cell['calculatedValue'] !== ''
            ? $cell['calculatedValue']
            : (isset($cell['data']) ? $cell['data'] : '');
          $line[] = $value;
        }
        $csv[] = $this->toCsvLine($line, $delimiter);
      }

      return rest_ensure_response([
        'success' => true,
        'table_id' => $id,
        'title' => $table->title,
        'csv' => implode("\n", $csv),
        'rows_count' => count($rows),
        'filename' => sanitize_file_name($table->title) . '.csv',
      ]);
    } catch (Exception $e) {
      return $this->restError($e->getMessage());
    }
  }

  /**
   * GET /tables/{id}/export/json
   */
  public function exportJson(WP_REST_Request $request)
  {
    try {
      $id = (int) $request->get_param('id');
      $includeHeader = (bool) $request->get_param('include_header');
      $tablesModel = $this->getModel('tables');
      $table = $tablesModel->getById($id);

      if (!$table) {
        return $this->restError(sprintf('Table with ID %d not found.', $id), 404);
      }

      $rows = $tablesModel->getRows($id);
      $columns = $tablesModel->getColumns($id);
      $data = [];

      foreach ($rows as $rowIndex => $row) {
        if (empty($row['cells'])) {
          continue;
        }
        $record = [];
        foreach ($row['cells'] as $colIndex => $cell) {
          $value = isset($cell['calculatedValue']) && $cell['calculatedValue'] !== ''
            ? $cell['calculatedValue']
            : (isset($cell['data']) ? $cell['data'] : '');
          $key = !empty($columns[$colIndex]) ? $columns[$colIndex] : 'col_' . $colIndex;
          $record[$key] = $value;
        }
        $data[] = $record;
      }

      return rest_ensure_response([
        'success' => true,
        'table_id' => $id,
        'title' => $table->title,
        'columns' => $columns,
        'data' => $data,
        'count' => count($data),
      ]);
    } catch (Exception $e) {
      return $this->restError($e->getMessage());
    }
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  private function toCsvLine(array $values, $delimiter = ',')
  {
    $escaped = [];
    foreach ($values as $value) {
      $value = str_replace('"', '""', (string) $value);
      $escaped[] = '"' . $value . '"';
    }
    return implode($delimiter, $escaped);
  }

  private function restError($message, $status = 500)
  {
    return new WP_REST_Response(['success' => false, 'message' => $message], $status);
  }
}
