<?php

class SupsysticTables_Settings_Controller extends SupsysticTables_Core_BaseController
{
  /**
   * @return RscDtgs_Http_Response
   */
  public function indexAction()
  {
    wp_enqueue_style('supsystic-tables-settings-index-css');
    wp_enqueue_script('supsystic-tables-settings-index-js');

    $templates = $this->getModule('settings')->getTemplatesAliases();
    $settings = get_option($this->getConfig()->get('db_prefix') . 'settings');

    try {
      return $this->response($templates['settings.index'], ['settings' => $settings, 'wpRoles' => wp_roles()->role_names]);
    } catch (Throwable $e) {
      return $this->response('error.twig', ['exception' => $e]);
    }
  }
  /**
   * @return RscDtgs_Http_Response
   */
  public function getSettingsAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      die();
    }
    $settings = get_option($this->getConfig()->get('db_prefix') . 'settings');
    return $this->response(RscDtgs_Http_Response::AJAX, array_merge(['settings' => $settings], ['success' => true]));
  }
  public function saveSettingsAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      die();
    }
    $optionsName = $this->getConfig()->get('db_prefix') . 'settings';
    $currentSettings = get_option($optionsName);
    $settings = $request->post->get('settings', []);

    if (!$currentSettings) {
      $currentSettings = [];
    }

    // array_diff()/array_intersect() cast sub-array elements to string for
    // comparison, which triggers "Array to string conversion" (not silenced
    // by @ for this specific case), so compare with a type-safe callback.
    $compareValues = function ($a, $b) {
      if (is_array($a) || is_array($b)) {
        return $a == $b ? 0 : 1;
      }
      return strcmp((string) $a, (string) $b);
    };
    $diff = array_udiff($settings, $currentSettings, $compareValues);
    $intersect = array_uintersect($settings, $currentSettings, $compareValues);
    $merge = array_merge($intersect, $diff);

    update_option($optionsName, $merge);
    return $this->redirect($this->generateUrl('settings'));
  }
}
