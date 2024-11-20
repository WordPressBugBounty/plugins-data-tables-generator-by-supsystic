<?php


class SupsysticTables_Migrationfree_Model_Exporter extends SupsysticTables_Core_BaseModel
{
	public function export($ids)
	{
		$tables = array('columns', 'conditions', 'diagrams', 'rows', 'rows_history');

		// Begin export
		if(ob_get_contents()) ob_end_clean();
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="migration.sql"');
		if(ob_get_contents()) ob_end_clean();
		$delim = '----------------------------------';
		$delimEOL = PHP_EOL.$delim.PHP_EOL;

		foreach($ids as $i => $id) {
			$tableName = $this->getTable('tables');
			$fields = $this->selectFields($tableName);

			print 'INSERT INTO `%prefix%tables` ('.$fields.')'.PHP_EOL.
				'VALUES'.$this->selectValues($tableName, $fields, 'id', $id).';'.$delimEOL.
				'SET @table_id = (SELECT last_insert_id());'.$delimEOL;

			$fieldWhere = 'table_id';
			$limit = 400;
			foreach($tables as $t => $table) {
				$tableName = $this->getTable($table);
				$countRows = $this->getCountRows($tableName, $fieldWhere, $id);
				if($countRows > 0) {
					$fields = $this->selectFields($tableName);
					$offset = 0;
					$diag = ($table == 'diagrams');
					do {
						$values = $this->selectValues($tableName, $fields, $fieldWhere, $id, '@table_id,', $limit, $offset);

						if(!empty($values)) {
							print 'INSERT INTO `%prefix%'.$table.'` (`table_id`,'.$fields.')'.PHP_EOL.'VALUES';
							if($diag) {
								print str_replace('"table_id":"'.$id.'"', '"table_id":"%table_id%"', $values);
							} else {
								print $values;
							}
							print ';'.$delimEOL;
						}
						$offset += $limit;
					} while ($offset < $countRows);
					if($diag) {
						print 'UPDATE `%prefix%'.$table.'` SET `data`=REPLACE(`data`, "%table_id%", @table_id) WHERE `table_id`=@table_id;'.$delimEOL;
					}
				}
			}
			print PHP_EOL;
		}
	}

	private function selectValues($table, $fields, $where, $id, $pre = '', $limit = 0, $offset = 0)
	{
		$query = $this->getQueryBuilder()
			->select($fields)
			->from($table)
			->where($where, '=', $id);
		if($limit > 0) {
			$query->limit($limit)->offset($offset);
		}

		$results = $this->db->get_results($query->build(), ARRAY_N);

		if(sizeof($results) == 0) {
			return '';
		}

		$values = '';
		foreach($results as $i => $row) {
			$value = $pre;
			foreach($row as $f => $val) {
				$value .= "'".$val."',";
			}
			$values .= '('.substr($value, 0, -1).'),';
		}
		return substr($values, 0, -1);
	}

	private function getCountRows($table, $where, $id)
	{
		$query = $this->getQueryBuilder()
			->select('count(*)')
			->from($table)
			->where($where, '=', $id);
		$results = $this->db->get_row($query->build(), ARRAY_N);
		return $results[0];
	}

	private function selectFields($table)
	{
		$columns = $this->db->get_results('SHOW COLUMNS FROM '.$table, ARRAY_N);
		$fields = '';
		foreach ($columns as $i => $field) {
			$name = $field[0];
         if (class_exists('SupsysticTablesWooPro_Woocommerce_Module')) {
            if($name != 'id' && $name != 'table_id') {
   				$fields .= '`'.$field[0].'`,';
   			}
         } else {
            if($name != 'id' && $name != 'table_id' && $name != 'woo_settings') {
   				$fields .= '`'.$field[0].'`,';
   			}
         }
		}
		return substr($fields, 0, -1);
	}
}