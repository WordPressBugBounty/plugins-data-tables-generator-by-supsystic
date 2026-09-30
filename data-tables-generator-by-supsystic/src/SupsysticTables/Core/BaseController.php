<?php

abstract class SupsysticTables_Core_BaseController extends RscDtgs_Mvc_Controller
{
  /**
   * Returns an instance of the model.
   * @param string $model
   * @param $module
   * @return SupsysticTables_Core_BaseModel
   */
  public function getModel($model, $module = null)
  {
    return $this->getCoreModule()->getModelsFactory()->get($model, $module);
  }

  /**
   * Creates an instance of the model and returns it.
   * @param string $model
   * @param string|RscDtgs_Mvc_Module $module
   * @return SupsysticTables_Core_BaseModel
   */
  public function createModel($model, $module = null)
  {
    return $this->getCoreModule()->getModelsFactory()->factory($model, $module);
  }

  /**
   * Translates a string.
   * @param string $string String to translate
   * @return string
   */
  public function translate($string)
  {
    return $this->getEnvironment()->translate($string);
  }

  /**
   * Is ajax request?
   * @return bool
   */
  public function isAjax()
  {
    // $request->isXmlHttpRequest() has a bug and not working.
    // So do it with headers params
    $request = $this->getRequest();

    return $request->headers->has('X_REQUESTED_WITH') && $request->headers->get('X_REQUESTED_WITH') === 'XMLHttpRequest';
  }

  /**
   * Returns AJAX success response.
   * @param array $data
   * @return RscDtgs_Http_Response
   */
  public function ajaxSuccess(array $data = [])
  {
    return $this->response(RscDtgs_Http_Response::AJAX, array_merge($data, ['success' => true]));
  }

  /**
   * Returns AJAX error response.
   * @param string $message
   * @param array $data
   * @return RscDtgs_Http_Response
   */
  public function ajaxError($message = 'Something went wrong.', array $data = [])
  {
    return $this->response(RscDtgs_Http_Response::AJAX, array_merge($data, ['success' => false, 'message' => $message]));
  }

  /**
   * Returns the core module
   * @returns SupsysticTables_Core_Module
   */
  private function getCoreModule()
  {
    return $this->getEnvironment()->getModule('core');
  }

  public function _checkNonce($request)
  {
    $nonce = '';
    if (!empty(($requestRoute = $request->post->get('route')))) {
      if (!empty($requestRoute['nonce'])) {
        $nonce = $requestRoute['nonce'];
      }
    }
    if (!empty($request->post->get('nonce'))) {
      $nonce = $request->post->get('nonce');
    }
    if (!empty($request->query->get('nonce'))) {
      $nonce = $request->query->get('nonce');
    }
    // A nonce alone is not a permission: also require the same rights the nonce is issued for
    // (administrators and the roles allowed in the plugin's "access_roles" setting).
    if (!empty($nonce) && wp_verify_nonce($nonce, 'dtgs_nonce') && $this->_currentUserCanUsePlugin()) {
      return true;
    }
    return false;
  }

  /**
   * Administrators, and roles listed in the plugin's "access_roles" setting.
   */
  public function _currentUserCanUsePlugin()
  {
    if (current_user_can('manage_options') || current_user_can('administrator')) {
      return true;
    }
    $settings = get_option('supsystic_tbl_settings');
    $allowedRoles = $settings && !empty($settings['access_roles']) ? (array) $settings['access_roles'] : [];
    $user = wp_get_current_user();
    return $user && $user->ID && array_intersect((array) $user->roles, $allowedRoles) ? true : false;
  }

  public function _checkNonceFrontend($request)
  {
    $nonce = '';
    if (!empty(($requestRoute = $request->post->get('route')))) {
      if (!empty($requestRoute['nonce'])) {
        $nonce = $requestRoute['nonce'];
      }
    }
    if (!empty($request->post->get('nonce'))) {
      $nonce = $request->post->get('nonce');
    }
    if (!empty($request->query->get('nonce'))) {
      $nonce = $request->query->get('nonce');
    }
    if (!empty($nonce) && wp_verify_nonce($nonce, 'dtgs_nonce_frontend')) {
      return true;
    }
    return false;
  }
}
