<?php

class SupsysticTables_Importcompetitor_Controller extends SupsysticTables_Core_BaseController
{
  /**
   * Tables of other plugins are read only for those who can manage the whole site.
   */
  private function isAllowed($request)
  {
    return $this->_checkNonce($request) && current_user_can('manage_options');
  }

  private function getManager()
  {
    return $this->getModel('manager', 'importcompetitor');
  }

  private function getImporter()
  {
    return $this->getModel('importer', 'importcompetitor');
  }

  /**
   * @return array The source and its table, by what the browser sent. The options of the table are read again here.
   */
  private function getRequested(RscDtgs_Http_Request $request)
  {
    $source = $this->getManager()->getSource((string) $request->post->get('source'));

    return [$source, $source->load((int) $request->post->get('table'))];
  }

  public function scanAction(RscDtgs_Http_Request $request)
  {
    if (!$this->isAllowed($request)) {
      die();
    }
    try {
      $manager = $this->getManager();

      return $this->ajaxSuccess([
        'sources' => $manager->scan(),
        'palettes' => $manager->getPalettes(),
        'isPro' => (bool) $this->getEnvironment()->isPro(),
        'proUrl' => $this->getEnvironment()->getModule('core')->buildProUrl(['utm_medium' => 'import_competitor']),
        'tablesUrl' => $this->getEnvironment()->generateUrl('tables'),
      ]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  public function postponeAction(RscDtgs_Http_Request $request)
  {
    if (!$this->isAllowed($request)) {
      die();
    }
    try {
      $this->getManager()->postpone((string) $request->post->get('source'), (string) $request->post->get('mode'));

      return $this->ajaxSuccess();
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  public function startAction(RscDtgs_Http_Request $request)
  {
    if (!$this->isAllowed($request)) {
      die();
    }
    try {
      list($source, $table) = $this->getRequested($request);
      $options = ['palette' => (string) $request->post->get('palette')];

      return $this->ajaxSuccess(['table' => $this->getImporter()->start($source, $table, $options)]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  public function rowsAction(RscDtgs_Http_Request $request)
  {
    if (!$this->isAllowed($request)) {
      die();
    }
    try {
      list($source, $table) = $this->getRequested($request);
      $offset = max(0, (int) $request->post->get('offset'));
      $newId = (int) $request->post->get('newId');

      return $this->ajaxSuccess(['offset' => $this->getImporter()->addRows($source, $table, $newId, $offset)]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  public function finishAction(RscDtgs_Http_Request $request)
  {
    if (!$this->isAllowed($request)) {
      die();
    }
    try {
      list($source, $table) = $this->getRequested($request);
      $newId = (int) $request->post->get('newId');
      $result = $this->getImporter()->finish($table, $newId, (int) $request->post->get('total'));
      $this->getManager()->markImported($source->getKey(), $table['id'], $newId);

      return $this->ajaxSuccess(['table' => $result]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  /**
   * A table that stopped half way is removed: the original one can be imported again at any time.
   */
  public function abortAction(RscDtgs_Http_Request $request)
  {
    if (!$this->isAllowed($request)) {
      die();
    }
    try {
      $this->getImporter()->abort((int) $request->post->get('newId'));

      return $this->ajaxSuccess();
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }
}
