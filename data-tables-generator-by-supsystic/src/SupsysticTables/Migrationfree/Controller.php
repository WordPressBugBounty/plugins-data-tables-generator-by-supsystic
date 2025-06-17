<?php

class SupsysticTables_Migrationfree_Controller extends SupsysticTables_Core_BaseController
{
	/**
     * Generates download url.
     * @param \RscDtgs_Http_Request $request
     * @return \RscDtgs_Http_Response
     */
    public function generateUrlAction(RscDtgs_Http_Request $request)
    {
      if (!$this->_checkNonce($request)) die();
		$id = $request->post->get_esc_html('id');
		$slug = $this->getEnvironment()->getMenu()->getMenuSlug();
        return $this->ajaxSuccess(array(
            'url' => admin_url(
                sprintf(
                    'admin.php?page=%s&module=migrationfree&migration-supsystic-table=true&id=%s',
                    $slug,
					$id
                )
            )
        ));
    }
    /**
     * @return SupsysticTables_Migrationfree_Module
     */
    protected function getModule()
    {
        $resolver = $this->getEnvironment()->getResolver();
        return $resolver->getModulesList()->get('migration');
    }
}
