<?php

/**
 * Class SupsysticTables_Migrationfree_Module
 */
class SupsysticTables_Migrationfree_Module extends SupsysticTables_Core_BaseModule
{
    /**
     * {@inheritdoc}
     */
   public function onInit()
   {
        $this->handleMigrationRequest();
   }

	private function handleMigrationRequest()
	{
      if(current_user_can('administrator')) {
   		if (!$this->getRequest()->query->has('migration-supsystic-table')) {
   			return;
   		}
   		$config = $this->getEnvironment()->getConfig();
         $id = $this->getRequest()->query->get_esc_html('id');
         $ids = explode(';', $id);
   		if(!is_array($ids)) {
        $text = sprintf($this->translate('The table IDs %s not found.'), $id);
   			wp_die(esc_html($text));
   		}
         $core = $this->getEnvironment()->getModule('core');
         $tables = $core->getModelsFactory()->get('tables');
   		foreach($ids as $i => $id) {
   			$table = $tables->getById((int)$id);
   			if (null === $table) {
          $text = sprintf($this->translate('The table ID %s not found.'), $id);
     			wp_die(esc_html($text));
   			}
   		}
         $exporter = $core->getModelsFactory()->get('exporter', 'migrationfree');
         $exporter->export($ids);
         die();
      }
      return;
	}
}
