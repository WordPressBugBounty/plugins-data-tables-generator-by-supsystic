<?php

/**
 * The plugins we can import tables from, what was offered to the user and what was imported already.
 * A new source is one more model with the same methods as SupsysticTables_Importcompetitor_Model_NinjaTables.
 */
class SupsysticTables_Importcompetitor_Model_Manager extends SupsysticTables_Core_BaseModel
{
  const OPTION = 'supsystic_tbl_import_competitor';
  const REMIND_AFTER = 86400;
  const DISMISS_FOR = 2592000;

  public function getSourceModels()
  {
    return ['ninjaTables'];
  }

  /**
   * @return SupsysticTables_Importcompetitor_Model_NinjaTables[] By the key of each source.
   */
  public function getSources()
  {
    $sources = [];
    $factory = $this->environment->getModule('core')->getModelsFactory();
    foreach ($this->getSourceModels() as $name) {
      $source = $factory->get($name, 'importcompetitor');
      $sources[$source->getKey()] = $source;
    }

    return $sources;
  }

  public function getSource($key)
  {
    $sources = $this->getSources();
    if (!isset($sources[$key])) {
      throw new InvalidArgumentException($this->environment->translate('This plugin is not supported.'));
    }

    return $sources[$key];
  }

  private function getState()
  {
    $state = get_option(self::OPTION, []);

    return is_array($state) ? $state : [];
  }

  private function saveState(array $state)
  {
    update_option(self::OPTION, $state, false);
  }

  /**
   * @return array Id of a table of the source => id of our table.
   */
  public function getImported($key)
  {
    $state = $this->getState();

    return isset($state[$key]['imported']) && is_array($state[$key]['imported']) ? $state[$key]['imported'] : [];
  }

  public function markImported($key, $sourceId, $newId)
  {
    $state = $this->getState();
    $state[$key]['imported'][(int) $sourceId] = (int) $newId;
    $this->saveState($state);
  }

  /**
   * @param string $mode "later" comes back tomorrow, "never" comes back after 30 days.
   */
  public function postpone($key, $mode)
  {
    $state = $this->getState();
    $state[$key]['until'] = time() + ($mode === 'never' ? self::DISMISS_FOR : self::REMIND_AFTER);
    $state[$key]['mode'] = $mode === 'never' ? 'never' : 'later';
    $this->saveState($state);
  }

  /**
   * @return array|null The source to offer now: it has tables that were not imported yet, and it is not postponed.
   */
  public function getOffer()
  {
    $state = $this->getState();
    foreach ($this->getSources() as $key => $source) {
      if (isset($state[$key]['until']) && (int) $state[$key]['until'] > time()) {
        continue;
      }
      $count = $this->countNew($key, $source);
      if ($count > 0) {
        return ['source' => $key, 'name' => $source->getName(), 'count' => $count];
      }
    }

    return null;
  }

  private function countNew($key, $source)
  {
    return count(array_diff($source->getImportableIds(), array_map('intval', array_keys($this->getImported($key)))));
  }

  /**
   * @return array Per source: its tables with what will happen to each of their options.
   */
  public function scan()
  {
    $factory = $this->environment->getModule('core')->getModelsFactory();
    $importer = $factory->get('importer', 'importcompetitor');
    $result = [];
    foreach ($this->getSources() as $key => $source) {
      $imported = $this->getImported($key);
      $usage = $source->getUsage();
      $tables = [];
      foreach ($source->getIds() as $id) {
        try {
          $table = $source->load($id);
          // A remote file is opened only by the import itself
          $rows = in_array($table['provider'], ['csv', 'google-csv'], true) ? -1 : $source->countRows($table);
          $tables[] = [
            'id' => $id,
            'title' => $table['title'] !== '' ? $table['title'] : '#' . $id,
            'rows' => $rows,
            'columns' => count($table['columns']),
            'provider' => $table['provider'],
            'supported' => $table['supported'],
            'reason' => $table['reason'],
            'importedAs' => isset($imported[$id]) ? (int) $imported[$id] : 0,
            'usedOn' => isset($usage[$id]) ? $usage[$id] : [],
            'report' => $importer->getReport($table, max(0, $rows)),
          ];
        } catch (Throwable $e) {
          $tables[] = ['id' => $id, 'title' => '#' . $id, 'rows' => 0, 'columns' => 0, 'provider' => '', 'supported' => false, 'reason' => $e->getMessage(), 'importedAs' => 0, 'usedOn' => [], 'report' => []];
        }
      }
      $result[] = ['key' => $key, 'name' => $source->getName(), 'tables' => $tables];
    }

    return $result;
  }

  /**
   * @return array The designs that can be chosen in this version.
   */
  public function getPalettes()
  {
    $palettes = [];
    $isPro = (bool) $this->environment->isPro();
    foreach ($this->environment->getModule('tables')->getClassicDesignPaletteLibrary() as $key => $palette) {
      if (empty($palette['pro']) || $isPro) {
        $palettes[] = ['key' => $key, 'name' => $palette['name']];
      }
    }

    return $palettes;
  }

  public function getStrings()
  {
    $strings = [];
    foreach ([
      'offerTitle' => 'We found %d tables from %s',
      'offerTitleOne' => 'We found a table from %s',
      'offerText' => 'Move them here in a few clicks, with their rows, filters and settings. Your original tables stay as they are.',
      'offerGo' => 'Review and import',
      'offerLater' => 'Remind me tomorrow',
      'offerNever' => 'No, thanks',
      'close' => 'Close',
      'pickTitle' => 'Import tables from %s',
      'pickText' => 'Choose the tables to move. Nothing changes in the original tables.',
      'selectAll' => 'Select all',
      'rows' => '%d rows',
      'rowsRemote' => 'From a remote file',
      'columns' => '%d columns',
      'imported' => 'Imported as table #%d',
      'importAgain' => 'Importing it again makes one more copy.',
      'notAvailable' => 'Not available',
      'noTables' => 'No tables from other plugins were found on this site.',
      'noTablesHint' => 'Tables are found right in your database, even when the other plugin is switched off. For now tables can be imported from Ninja Tables.',
      'design' => 'Design of the imported tables',
      'designProNote' => 'More than 100 other designs are available in PRO.',
      'next' => 'Continue',
      'back' => 'Back',
      'reviewTitle' => 'What will happen',
      'reviewText' => 'Your tables will look like tables of this plugin. Here is what happens to their options.',
      'kindMoved' => 'Moved as they are',
      'kindReplaced' => 'Replaced with our options',
      'kindPro' => 'Only in PRO',
      'kindSkipped' => 'Not moved',
      'proTitle' => 'These options are not in the free version',
      'proText' => 'They are available in PRO. You can import without them now, and import the tables again after upgrading.',
      'getPro' => 'Get PRO',
      'tablesCount' => 'in %d tables',
      'tablesCountOne' => 'in 1 table',
      'importWithout' => 'Import without these options',
      'importTables' => 'Import %d tables',
      'importTable' => 'Import the table',
      'workingTitle' => 'Importing your tables',
      'workingText' => 'Keep this page open until it finishes.',
      'stop' => 'Stop',
      'waiting' => 'Waiting',
      'working' => 'Importing, %d of %d rows',
      'preparing' => 'Preparing',
      'stopped' => 'Stopped',
      'leave' => 'The import is still running. Leave this page?',
      'failed' => 'The table could not be imported.',
      'doneTitle' => 'Your tables are ready',
      'doneTitleSome' => 'The import has finished',
      'doneText' => 'Pages still show the old tables until you replace their shortcodes with the new ones.',
      'open' => 'Open',
      'copy' => 'Copy',
      'copied' => 'Copied',
      'usedOn' => 'Replace the old table on:',
      'allTables' => 'Go to tables',
      'loadError' => 'The list of tables could not be loaded. Please try again.',
    ] as $key => $text) {
      $strings[$key] = $this->environment->translate($text);
    }

    return $strings;
  }
}
