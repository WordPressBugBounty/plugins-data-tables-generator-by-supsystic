<?php

/**
 * Class SupsysticTables_Review_Controller
 */
class SupsysticTables_Review_Controller extends SupsysticTables_Core_BaseController
{
  /**
   * Saves the answer to the review card: rate, done, later, support or close.
   * @param RscDtgs_Http_Request $request
   * @return RscDtgs_Http_Response
   */
  public function respondAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      die();
    }
    $answer = $request->post->get('answer');

    /** @var SupsysticTables_Review_Module $module */
    $module = $this->getEnvironment()->getModule('review');
    if (!$module->saveAnswer(get_current_user_id(), is_string($answer) ? $answer : '')) {
      return $this->ajaxError($this->translate('Unknown answer.'));
    }

    return $this->ajaxSuccess();
  }
}
