<?php

class SupsysticTables_Wizard_Controller extends SupsysticTables_Core_BaseController
{
  /**
   * Everything the wizard shows: it is loaded when the wizard opens, not with every page.
   */
  public function configAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      die();
    }
    try {
      return $this->ajaxSuccess(['config' => $this->getModel('catalog', 'wizard')->getConfig()]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  public function createAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      die();
    }
    $answers = json_decode((string) wp_unslash($request->post->get('answers')), true);
    if (!is_array($answers)) {
      return $this->ajaxError($this->translate('The answers of the wizard could not be read. Please try again.'));
    }
    try {
      return $this->ajaxSuccess(['table' => $this->getModel('builder', 'wizard')->create($answers)]);
    } catch (Throwable $e) {
      return $this->ajaxError($e->getMessage());
    }
  }

  /**
   * A draft page with the shortcode of the new table, to see it on the site right away.
   */
  public function draftPageAction(RscDtgs_Http_Request $request)
  {
    if (!$this->_checkNonce($request)) {
      die();
    }
    if (!current_user_can('edit_pages')) {
      return $this->ajaxError($this->translate('You are not allowed to create pages.'));
    }
    $table = $this->getModel('tables')->getById((int) $request->post->get('id'));
    if (!$table) {
      return $this->ajaxError($this->translate('The table was not found.'));
    }
    $pageId = wp_insert_post([
      'post_type' => 'page',
      'post_status' => 'draft',
      'post_title' => $table->title,
      'post_content' => '[' . $this->getConfig()->get('shortcode_name') . ' id=' . (int) $table->id . ']',
    ], true);
    if (is_wp_error($pageId)) {
      return $this->ajaxError($pageId->get_error_message());
    }

    return $this->ajaxSuccess(['preview' => get_preview_post_link($pageId), 'edit' => get_edit_post_link($pageId, 'raw')]);
  }
}
