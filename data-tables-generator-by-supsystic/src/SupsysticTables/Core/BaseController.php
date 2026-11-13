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
   * Roles of the plugin's "access_roles" setting. Anything that is not a list of role names (the option is missing, empty,
   * damaged or of another type) gives no roles at all, so that then only administrators can use the plugin.
   * @return string[]
   */
  public static function getAccessRoles()
  {
    $settings = get_option('supsystic_tbl_settings');
    if (!is_array($settings) || empty($settings['access_roles'])) {
      return [];
    }
    $roles = is_array($settings['access_roles']) ? $settings['access_roles'] : [$settings['access_roles']];
    $clean = [];
    foreach ($roles as $role) {
      if (is_string($role) && trim($role) !== '') {
        $clean[] = $role;
      }
    }

    return array_values(array_unique($clean));
  }

  /**
   * Administrators, and roles listed in the plugin's "access_roles" setting.
   */
  public function _currentUserCanUsePlugin()
  {
    if (current_user_can('manage_options') || current_user_can('administrator')) {
      return true;
    }
    $allowedRoles = self::getAccessRoles();
    $user = wp_get_current_user();
    if (!$allowedRoles || !$user || empty($user->ID) || !is_array($user->roles)) {
      return false;
    }
    foreach ($user->roles as $role) {
      // Exact comparison: a loose one would let some odd values in the setting (0, false) match every role
      if (is_string($role) && in_array($role, $allowedRoles, true)) {
        return true;
      }
    }

    return false;
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
