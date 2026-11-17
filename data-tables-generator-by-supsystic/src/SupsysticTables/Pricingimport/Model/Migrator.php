<?php

/**
 * Makes pricing tables of Data Tables from the tables of Pricing Table and remembers which is which,
 * so moving a table again never makes a copy. The tables of Pricing Table are only read.
 */
class SupsysticTables_Pricingimport_Model_Migrator extends SupsysticTables_Core_BaseModel
{
  const OPTION = 'supsystic_tbl_pricing_import';
  const UPLOADS_FOLDER = 'supsystic-tables/pricing-table';

  private function getFactory()
  {
    return $this->environment->getModule('core')->getModelsFactory();
  }

  /**
   * @return SupsysticTables_Pricingimport_Model_Source
   */
  public function getSource()
  {
    return $this->getFactory()->get('source', 'pricingimport');
  }

  private function getTables()
  {
    return $this->getFactory()->get('tables');
  }

  public function getState()
  {
    $state = get_option(self::OPTION, []);
    if (!is_array($state)) {
      $state = [];
    }

    return array_merge(['tables' => [], 'reports' => [], 'times' => []], $state);
  }

  private function saveState(array $state)
  {
    update_option(self::OPTION, $state, false);
  }

  /**
   * @return array Id of a table of Pricing Table => id of our table, only for our tables that still exist.
   */
  public function getMap()
  {
    $state = $this->getState();
    $map = [];
    foreach ((array) $state['tables'] as $sourceId => $id) {
      if ($this->tableExists((int) $id)) {
        $map[(int) $sourceId] = (int) $id;
      }
    }

    return $map;
  }

  private function tableExists($id)
  {
    if ($id <= 0) {
      return false;
    }
    $name = $this->db->prefix . $this->environment->getConfig()->get('db_prefix') . 'tables';

    return (int) $this->db->get_var($this->db->prepare("SELECT COUNT(*) FROM {$name} WHERE id = %d AND table_type = 'pricing_table'", $id)) === 1;
  }

  /**
   * @return array The Pricing module of Data Tables: what the active version can do.
   */
  public function getCapabilities()
  {
    return $this->environment->getModule('pricing')->getCapabilities();
  }

  /**
   * Converts a table without saving it: for the list of tables and for checks.
   */
  public function preview($sourceId)
  {
    $source = $this->getSource();
    $table = $source->load($sourceId);
    if ($table['suspicious']) {
      throw new RuntimeException($this->environment->translate('This table has suspicious scripts in it, so Pricing Table does not show it either. It was not moved.'));
    }
    $rendered = $source->render($table);

    return $this->getFactory()->get('converter', 'pricingimport')->convert($table, $rendered, $this->getCapabilities(), $source);
  }

  /**
   * Moves one table.
   * @param bool $update For a table moved before: put the current version of the old table into the same table
   *                     of Data Tables. Without it such a table is left as it is.
   * @return array id, title, url, shortcode, status (created, updated, kept), report.
   */
  public function import($sourceId, $update = false)
  {
    $sourceId = (int) $sourceId;
    $map = $this->getMap();
    $existing = isset($map[$sourceId]) ? $map[$sourceId] : 0;
    $state = $this->getState();
    if ($existing && !$update) {
      $report = isset($state['reports'][$sourceId]) ? $state['reports'][$sourceId] : [];

      return $this->getResult($existing, 'kept', $report);
    }

    $source = $this->getSource();
    $list = $source->getList(true);
    if (!isset($list[$sourceId])) {
      throw new InvalidArgumentException($this->environment->translate('This table no longer exists.'));
    }
    $converted = $this->preview($sourceId);
    $builder = $this->copyAssets($converted['builder'], $converted['assets'], $source);
    $title = $list[$sourceId]['label'] !== '' ? $list[$sourceId]['label'] : sprintf($this->environment->translate('Pricing table %d'), $sourceId);

    $model = $this->getTables();
    $this->environment->getModule('tables')->setIniLimits();
    if ($existing) {
      $id = $existing;
      $model->set($id, ['title' => $title]);
      $status = 'updated';
    } else {
      $id = (int) $model->add([
        'title' => $title,
        'table_type' => 'pricing_table',
        'settings' => serialize(['tableType' => 'pricing_table', 'sourceType' => 'manual', 'updateMode' => 'manual', 'designMode' => 'default', 'importedFrom' => 'pricing-table', 'importedId' => $sourceId]),
      ]);
      if (!$id) {
        throw new RuntimeException($this->environment->translate('The table could not be created.'));
      }
      // The same placeholder rows as a pricing table made by the wizard: cloning and other table tools expect rows
      $emptyCell = ['data' => '', 'calculatedValue' => '', 'hidden' => '', 'type' => 'text', 'formatType' => '', 'meta' => []];
      $model->setRows($id, array_fill(0, 3, ['cells' => array_fill(0, 3, $emptyCell)]));
      $status = 'created';
    }

    try {
      $pricing = $this->environment->getModule('pricing');
      $this->getFactory()->get('builder', 'pricing')->saveBuilder($model->getById($id), $builder, $pricing->getCapabilities(), $pricing->getTemplates());
    } catch (Throwable $e) {
      if ($status === 'created') {
        $model->removeById($id);
      }
      throw $e;
    }

    $state = $this->getState();
    $state['tables'][$sourceId] = $id;
    $state['reports'][$sourceId] = $converted['report'];
    $state['times'][$sourceId] = time();
    $this->saveState($state);

    return $this->getResult($id, $status, $converted['report']);
  }

  public function getResult($id, $status, array $report)
  {
    $table = $this->getTables()->getById((int) $id);

    return [
      'id' => (int) $id,
      'title' => $table ? (string) $table->title : '',
      'status' => $status,
      'url' => $this->environment->generateUrl('tables', 'view', ['id' => (int) $id, 'nonce' => wp_create_nonce('dtgs_nonce')]),
      'shortcode' => '[' . $this->environment->getConfig()->get('shortcode_name') . ' id=' . (int) $id . ']',
      'report' => $report,
    ];
  }

  /**
   * Pictures that came with the templates of Pricing Table live in its folder. They are copied to the uploads
   * folder once, so the tables keep them after the old plugin is removed.
   */
  private function copyAssets(array $builder, array $links, SupsysticTables_Pricingimport_Model_Source $source)
  {
    if (!$links) {
      return $builder;
    }
    $uploads = wp_upload_dir(null, false);
    if (!empty($uploads['error'])) {
      return $builder;
    }
    $baseUrl = $source->getAssetsUrl();
    $baseDir = realpath($source->getAssetsDir());
    if (!$baseDir) {
      return $builder;
    }
    $targetDir = trailingslashit($uploads['basedir']) . self::UPLOADS_FOLDER . '/';
    $targetUrl = trailingslashit($uploads['baseurl']) . self::UPLOADS_FOLDER . '/';
    $replace = [];
    foreach ($links as $link) {
      $relative = ltrim(substr($link, strlen($baseUrl)), '/');
      $file = realpath($baseDir . '/' . $relative);
      // Only files inside the folder of pictures, never a path that leads out of it
      if (!$file || strpos($file, $baseDir) !== 0 || !is_file($file) || !preg_match('/\.(png|jpe?g|gif|svg|webp)$/i', $file)) {
        continue;
      }
      $target = $targetDir . $relative;
      if (!file_exists($target)) {
        if (!wp_mkdir_p(dirname($target)) || !@copy($file, $target)) {
          continue;
        }
      }
      $replace[$link] = $targetUrl . $relative;
    }
    if (!$replace) {
      return $builder;
    }
    $builder['html'] = strtr($builder['html'], $replace);
    $builder['css'] = strtr($builder['css'], $replace);
    foreach ((array) $builder['toggleVariants'] as $key => $html) {
      $builder['toggleVariants'][$key] = strtr($html, $replace);
    }

    return $builder;
  }

  /**
   * @return array For the plugin of Pricing Table and the page: how many tables there are and how many moved.
   */
  public function getStatus()
  {
    $list = $this->getSource()->getList();
    $map = $this->getMap();

    return [
      'total' => count($list),
      'imported' => count(array_intersect_key($map, $list)),
    ];
  }
}
