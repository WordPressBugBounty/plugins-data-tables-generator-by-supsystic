<?php
class SupsysticTables_Promo_Model_Promo extends SupsysticTables_Core_BaseModel
{
  public function firstRun()
  {
    update_option($this->getPrefix() . 'plug_welcome_show', time()); // Remember this
  }
}
