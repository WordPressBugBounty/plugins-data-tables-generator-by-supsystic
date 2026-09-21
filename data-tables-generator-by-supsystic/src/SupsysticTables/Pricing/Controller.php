<?php

class SupsysticTables_Pricing_Controller extends SupsysticTables_Core_BaseController
{
  public function viewAction(RscDtgs_Http_Request $request)
  {
    $id = (int) $request->query->get('id');
    $table = $this->getModel('tables')->getById($id);
    if (!$table || $table->table_type !== 'pricing_table') {
      return $this->response('error.twig', ['exception' => new InvalidArgumentException('Pricing table not found.')]);
    }
    $pricing = $this->getEnvironment()->getModule('pricing');
    $builder = $this->getModel('builder', 'pricing')->getBuilder($table);
    if (!isset($pricing->getTemplates()[$builder['template']])) {
      $builder['template'] = 'classic';
    }
    return $this->response('@pricing/editor.twig', [
      'table' => $table,
      'builder' => $builder,
      'builderJson' => wp_json_encode($builder, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
      'capabilities' => $pricing->getCapabilities(),
      'templates' => $pricing->getTemplates(),
      'premiumTemplates' => $pricing->getPremiumTemplates(),
      'nonce' => wp_create_nonce('dtgs_nonce'),
      'canvasCssJson' => wp_json_encode($pricing->getCanvasStyles(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
      'fontLibraryJson' => wp_json_encode($pricing->getFontLibrary(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
      'iconLibraryJson' => wp_json_encode($pricing->getIconLibrary(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
      'woocommerceActive' => $pricing->isWooCommerceAvailable(),
      'woocommerceProductsJson' => wp_json_encode($pricing->getWooProducts(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
      'shortcode' => '[' . $this->getConfig()->get('shortcode_name') . ' id=' . $id . ']',
      'proLicenseUrl' => admin_url('admin.php?page=supsystic-tables&module=license'),
    ]);
  }

  public function saveAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request) || !current_user_can($this->getConfig()->get('plugin_menu')['capability'])) {
      return $this->ajaxError('Not allowed.');
    }
    $id = (int) $request->post->get('id');
    $table = $this->getModel('tables')->getById($id);
    if (!$table || $table->table_type !== 'pricing_table') {
      return $this->ajaxError('Pricing table not found.');
    }
    $data = $request->post->get('builder');
    if (!is_array($data)) {
      return $this->ajaxError('Invalid builder data.');
    }
    try {
      $model = $this->getModel('builder', 'pricing');
      $pricing = $this->getEnvironment()->getModule('pricing');
      $model->saveBuilder($table, $data, $pricing->getCapabilities(), $pricing->getTemplates());
      $this->getEnvironment()->getModule('tables')->getController()->cleanCache($id);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
    return $this->ajaxSuccess();
  }
}
