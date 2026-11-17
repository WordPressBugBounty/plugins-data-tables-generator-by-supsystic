<?php

/**
 * The page that moves the tables of Pricing Table by Supsystic, and its requests.
 */
class SupsysticTables_Pricingimport_Controller extends SupsysticTables_Core_BaseController
{
  /**
   * @return SupsysticTables_Pricingimport_Module
   */
  private function getPricingImport()
  {
    return $this->getEnvironment()->getModule('pricingimport');
  }

  private function getMigrator()
  {
    return $this->getPricingImport()->getMigrator();
  }

  private function getShortcodes()
  {
    return $this->getModel('shortcodes', 'pricingimport');
  }

  private function isAllowed($request)
  {
    return $this->_checkNonce($request) && current_user_can('manage_options');
  }

  /**
   * @return array Everything the page shows: the tables, what happened to them and the pages with old shortcodes.
   */
  private function getData()
  {
    $migrator = $this->getMigrator();
    $source = $migrator->getSource();
    $module = $this->getPricingImport();
    $list = $source->getList($module->withTemplates());
    $map = $migrator->getMap();
    $state = $migrator->getState();
    $tables = [];
    $templates = [];
    foreach ($list as $id => $item) {
      $originalId = $item['original_id'];
      if ($originalId && !isset($templates[$originalId])) {
        $templates[$originalId] = $source->getTemplateLabel($originalId);
      }
      $result = isset($map[$id]) ? $migrator->getResult($map[$id], 'kept', isset($state['reports'][$id]) ? $state['reports'][$id] : []) : null;
      $tables[] = [
        'id' => $id,
        'title' => $item['label'] !== '' ? $item['label'] : sprintf($this->translate('Table %d'), $id),
        'template' => $originalId ? $templates[$originalId] : '',
        'created' => $item['created'] ? mysql2date(get_option('date_format'), $item['created']) : '',
        'moved' => $result,
        'time' => isset($state['times'][$id]) ? (int) $state['times'][$id] : 0,
      ];
    }

    $coupon = $module->getCoupon();

    return [
      'tables' => $tables,
      'shortcodes' => $this->getShortcodes()->find($map),
      'isPro' => (bool) $this->getEnvironment()->isPro(),
      'coupon' => $coupon ? $coupon['code'] : '',
      'couponDiscount' => $coupon ? $coupon['discount'] : 0,
      'proUrl' => $module->getProUrl(),
      'sourceActive' => class_exists('framePts', false),
      'hasSource' => $source->exists(),
    ];
  }

  public function indexAction(RscDtgs_Http_Request $request)
  {
    if (!current_user_can('manage_options')) {
      return $this->response('error.twig', ['exception' => new RuntimeException($this->translate('You are not allowed to move tables.'))]);
    }
    try {
      $ids = array_filter(array_map('absint', explode(',', (string) $request->query->get('ids', ''))));
      $from = sanitize_key((string) $request->query->get('from', ''));

      $data = $this->getData();
      $json = wp_json_encode([
        'data' => $data,
        'preselect' => array_values($ids),
        'strings' => $this->getStrings(),
      ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

      return $this->response('@pricingimport/index.twig', [
        'data' => $data,
        'json' => $json,
        'fromPricingTable' => $from === 'pricing-table',
      ]);
    } catch (Throwable $e) {
      return $this->response('error.twig', ['exception' => $e]);
    }
  }

  public function stateAction(RscDtgs_Http_Request $request)
  {
    if (!$this->isAllowed($request)) {
      die();
    }
    try {
      return $this->ajaxSuccess(['data' => $this->getData()]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  /**
   * Moves one table: the page sends the tables one by one and shows the progress.
   */
  public function importAction(RscDtgs_Http_Request $request)
  {
    if (!$this->isAllowed($request)) {
      die();
    }
    try {
      $id = (int) $request->post->get('table');
      $list = $this->getMigrator()->getSource()->getList($this->getPricingImport()->withTemplates());
      if (!isset($list[$id])) {
        throw new InvalidArgumentException($this->translate('This table no longer exists.'));
      }

      return $this->ajaxSuccess(['result' => $this->getMigrator()->import($id, (bool) $request->post->get('update'))]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  public function replaceAction(RscDtgs_Http_Request $request)
  {
    if (!$this->isAllowed($request)) {
      die();
    }
    try {
      $keys = (array) $request->post->get('places', []);
      $result = $this->getShortcodes()->replace(array_map('sanitize_text_field', $keys), $this->getMigrator()->getMap());

      return $this->ajaxSuccess(['result' => $result, 'data' => $this->getData()]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  public function restoreAction(RscDtgs_Http_Request $request)
  {
    if (!$this->isAllowed($request)) {
      die();
    }
    try {
      $keys = $request->post->get('places', 'all');
      $keys = $keys === 'all' ? null : array_map('sanitize_text_field', (array) $keys);
      $result = $this->getShortcodes()->restore($keys);

      return $this->ajaxSuccess(['result' => $result, 'data' => $this->getData()]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  private function getStrings()
  {
    $strings = [];
    foreach ([
      'summary' => '%d tables found, %d moved',
      'summaryOne' => '1 table found, %d moved',
      'moveAll' => 'Move all tables',
      'moveSelected' => 'Move selected',
      'moving' => 'Moving %d of %d…',
      'notMoved' => 'Not moved yet',
      'movedAs' => 'Moved · table #%d',
      'updated' => 'Updated · table #%d',
      'inProgress' => 'Moving…',
      'waiting' => 'Waiting',
      'failed' => 'Not moved',
      'open' => 'Open',
      'copy' => 'Copy shortcode',
      'copied' => 'Copied',
      'update' => 'Move again',
      'details' => 'What moved',
      'hideDetails' => 'Hide',
      'fromTemplate' => 'Template: %s',
      'created' => 'Made on %s',
      'kindMoved' => 'Moved',
      'kindReplaced' => 'Work the Data Tables way',
      'kindPro' => 'Need Data Tables PRO',
      'kindSkipped' => 'Not moved',
      'inTables' => 'in %d tables',
      'inOneTable' => 'in 1 table',
      'doneTitle' => '%d tables are in Data Tables',
      'doneTitleOne' => '1 table is in Data Tables',
      'doneText' => 'Choose a theme in the table editor, then replace the old shortcodes below.',
      'proActive' => 'Data Tables PRO is active now: move these tables again to bring these options too.',
      'replaceSelected' => 'Replace shortcodes',
      'replacePages' => 'Replace on %d pages',
      'replaceOne' => 'Replace on 1 page',
      'notMovedTable' => 'table #%d is not moved',
      'undo' => 'Undo',
      'undoAll' => 'Undo all',
      'replacedOn' => 'Replaced on %s',
      'shortcodesCount' => '%d shortcodes',
      'shortcodesOne' => '1 shortcode',
      'confirmUpdateTitle' => 'Move this table again?',
      'confirmUpdateText' => 'Table #%d of Data Tables gets the current version of the old table. Changes made to it in Data Tables are replaced.',
      'confirmUpdate' => 'Move again',
      'confirmReplaceTitle' => 'Replace the shortcodes?',
      'confirmReplaceText' => 'The pages will show the tables of Data Tables. You can undo it here at any time.',
      'confirmReplace' => 'Replace',
      'confirmUndoTitle' => 'Put the old shortcodes back?',
      'confirmUndoText' => 'The pages will show the tables of Pricing Table again, if that plugin is active.',
      'confirmUndo' => 'Put back',
      'cancel' => 'Cancel',
      'close' => 'Close',
      'error' => 'Something went wrong. Please try again.',
      'leave' => 'The tables are still moving. Leave this page?',
    ] as $key => $text) {
      $strings[$key] = $this->translate($text);
    }

    return $strings;
  }
}
