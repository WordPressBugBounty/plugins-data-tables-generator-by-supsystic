<?php
class SupsysticTables_Woocommerce_Model_Woocolumns extends SupsysticTables_Core_BaseModel
{
  /**
   * For this model is important to create the mirror functions in SupsysticTables_Tables_Model_Tables
   * @see SupsysticTables_Tables_Model_Tables::getAllTableHistory and ets.
   */

  public function getAllWooColumns()
  {
    $query = $this->getQueryBuilder()->select('*')->from($this->getTable('woo_columns'));

    $WooColumns = $this->db->get_results($query->build(), ARRAY_A);

    $productAttr = wc_get_attribute_taxonomies();
    $columns = [];
    foreach ($WooColumns as $column) {
      $slug = $column['columns_name'];
      $id = $column['id'];
      $columns[] = ['slug' => $slug, 'name' => $column['columns_nice_name'], 'sub' => 0, 'id' => $id];
      if ($slug == 'attribute') {
        foreach ($productAttr as $attr) {
          $columns[] = ['slug' => $slug . '-' . $attr->attribute_id, 'name' => $attr->attribute_label, 'sub' => 1, 'id' => $id . '-' . $attr->attribute_id];
        }
      }
    }
    return $columns;
  }
}
