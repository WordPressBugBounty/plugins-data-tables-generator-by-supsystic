<?php

class SupsysticTables_Core_Model_Core extends SupsysticTables_Core_BaseModel
{
  /**
   * Prepares the data before run queries.
   * Replaces the '%prefix%' placeholder to the valid database prefix.
   * @param string $data
   * @return string
   */
  public function prepare($data)
  {
    return str_replace('%prefix%', $this->getPrefix(), $data);
  }

  /**
   * Updates the database.
   * @param string $data
   */
  public function update($data)
  {
    $data = $this->prepare($data);
    $queries = explode(';', $data);
    $queries = array_map('trim', $queries);
    $queries = array_filter($queries);

    foreach ($queries as $q) {
      try {
        if ('alter' === substr(strtolower($q), 0, 5)) {
          if ($this->checkQueryOnColumnNotExists($q)) {
            $this->safeQuery($q);
          }
        } elseif ('delete' === substr(strtolower($q), 0, 6)) {
          $this->safeQuery($q);
        } elseif (preg_match('/^CREATE\s+TABLE\s+`?([^`\s(]+)/i', $q, $m) && $this->isTableExists(trim($m[1], '`'))) {
          // The table already exists, so this CREATE TABLE has already done its
          // one-time job. Do not hand it to dbDelta for column diffing: dbDelta's
          // parser treats a column literally named `index` (among other reserved
          // words) as an index definition, which produces a malformed
          // "ALTER TABLE ... ADD `` (``)" query. Any future column additions to
          // an existing table should go through an explicit ALTER TABLE with an
          // existence check instead, like ensureTablesSchema() already does.
          continue;
        } else {
          $this->delta($q);
        }
      } catch (Throwable $e) {
        // One malformed/unsupported statement (e.g. under the SQLite
        // compatibility layer) must not abort the rest of the update.
        $this->logDbError($e->getMessage(), $q);
      }
    }
  }

  public function checkQueryOnColumnNotExists($queryString)
  {
    $patternTableName = '/^[\W ]*alter[\W ]*table[\W ]*([-\._`\w\d]*)[ ]*/iu';
    $pmaTableRes = preg_match_all($patternTableName, $queryString, $matches);

    if ($pmaTableRes && count($matches) > 1 && count($matches[1]) > 0 && strlen($matches[1][0]) > 0) {
      $patternAddColumn = '/ADD[\W ]*COLUMN[\W ]*([-\._`\w\d]*)[\W ]*/iu';
      $pmaColumnsRes = preg_match_all($patternAddColumn, $queryString, $columnMatches);

      if ($pmaColumnsRes && count($columnMatches) > 1 && count($columnMatches[1]) > 0 && strlen($columnMatches[1][0]) > 0) {
        // check columns exists at table
        $tableName = trim($matches[1][0], ' `');
        $checkTableQuery = "SHOW TABLES LIKE '" . $tableName . "';";
        $checkTableRes = $this->db->get_results($checkTableQuery);

        if (count($checkTableRes) > 0) {
          $columnExists = true;
          // check few "Add Columns"
          for ($ind = 0; $ind < count($columnMatches[1]); $ind++) {
            $columnName = trim($columnMatches[1][$ind], ' `');
            $columnExistsQuery = 'SHOW COLUMNS FROM ' . $tableName . " LIKE '" . $columnName . "';";
            $columnExistsRes = $this->db->get_results($columnExistsQuery);
            if (is_array($columnExistsRes) && count($columnExistsRes) > 0) {
              $columnExists = false;
            }
          }
          return $columnExists;
        }
      }
    }
    return true;
  }

  /**
   * Loads updates from the file and update the database.
   * @param string $file Path to updates file.
   */
  public function updateFromFile($file)
  {
    if (!is_readable($file)) {
      throw new RuntimeException(sprintf('File "%s" is not readable.', $file));
    }

    if (false === ($content = file_get_contents($file))) {
      throw new RuntimeException(sprintf('Failed to read file "%s".', $file));
    }

    $this->update($content);
  }
}
