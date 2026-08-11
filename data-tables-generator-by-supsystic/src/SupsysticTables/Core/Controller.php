<?php

class SupsysticTables_Core_Controller extends SupsysticTables_Core_BaseController
{
  public function rollbackAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request) || !current_user_can('manage_options')) {
      die();
    }
    $config = $this->getEnvironment()->getConfig();
    $revision = (int) $request->query->get('revision');

    update_option($config->get('revision_key'), $revision);

    return $this->redirect($this->generateUrl('tables', 'index'));
  }
}
