<?php

/**
 * Finds the PRO settings of a table that a save in the free version would reset,
 * so the editor can ask before that happens.
 */
class SupsysticTables_Tables_Model_ProGuard extends SupsysticTables_Core_BaseModel
{
  /**
   * @param object $table
   * @return array|null Data of the warning, null when a save loses nothing.
   */
  public function getWarning($table)
  {
    if ($this->environment->isPro() || !is_object($table)) {
      return null;
    }
    $labels = $this->getLabels($table);
    if (empty($labels)) {
      return null;
    }
    $this->enqueueAssets();

    return array_merge(['labels' => $labels], $this->getProState());
  }

  /**
   * @param object $table
   * @return string[] Names of the saved PRO settings, for people.
   */
  public function getLabels($table)
  {
    $type = isset($table->table_type) ? $table->table_type : '';

    if ($type === 'pricing_table') {
      return $this->environment->getModule('core')->getModelsFactory()->get('builder', 'pricing')->getProSettingsLabels($table);
    }
    if ($type === 'woo_product_table') {
      $woocommerce = $this->environment->getModule('woocommerce');

      return $woocommerce ? $woocommerce->getController()->getProSettingsLabels($table) : [];
    }

    return $this->getClassicLabels(isset($table->settings) && is_array($table->settings) ? $table->settings : []);
  }

  /**
   * The saved values of the fields that the free form does not send: a save replaces all settings.
   */
  private function getClassicLabels(array $settings)
  {
    return array_merge($this->getColumnFilterLabels($settings), $this->getOtherClassicLabels($settings));
  }

  private function getColumnFilterLabels(array $settings)
  {
    $searching = isset($settings['searching']) && is_array($settings['searching']) ? $settings['searching'] : [];
    if (!empty($searching['columnFilters']) || (isset($searching['columnSearchPosition']) && $searching['columnSearchPosition'] === 'panel')) {
      return [$this->environment->translate('Column filters')];
    }

    return [];
  }

  private function getOtherClassicLabels(array $settings)
  {
    $labels = [];
    $source = isset($settings['source']) && is_array($settings['source']) ? $settings['source'] : [];
    $import = isset($settings['features']['import']) && is_array($settings['features']['import']) ? $settings['features']['import'] : [];
    $exportFormats = !empty($settings['features']['export']) ? (array) $settings['features']['export'] : [];

    if (!empty($source['database']) || !empty($source['dbFields']) || !empty($source['dbSQL']) || !empty($source['dbExternalHost'])) {
      $labels[] = $this->environment->translate('Database source');
    }
    if (!empty($import['google']['link'])) {
      $labels[] = $this->environment->translate('Google Sheets sync');
    }
    if (!empty($import['ftp']['host'])) {
      $labels[] = $this->environment->translate('FTP source');
    }
    if ($this->hasAny($settings, ['saveEditableFields', 'markLastEditedCell', 'useEditableFieldsForLoggedInOnly', 'useEditableFieldsForCurRoles', 'allowedFileTypes', 'removeRows', 'colHeaders'])) {
      $labels[] = $this->environment->translate('Editing the table on the site');
    }
    if ($exportFormats) {
      $labels[] = sprintf($this->environment->translate('Export buttons: %s'), strtoupper(implode(', ', $exportFormats)));
    }
    if (
      !empty($settings['exportLogo']['src']) ||
      !empty($settings['exportOnlyVisible']) ||
      !empty($settings['pdfFullWidth']) ||
      (!empty($settings['exportLinksPosition']) && $settings['exportLinksPosition'] !== 'before_table') ||
      (!empty($settings['pdfPaperSize']) && $settings['pdfPaperSize'] !== 'auto') ||
      (!empty($settings['pdfOrientation']) && $settings['pdfOrientation'] !== 'portrait')
    ) {
      $labels[] = $this->environment->translate('Export and PDF options');
    }
    if (!empty($settings['styling']['lightboxImg'])) {
      $labels[] = $this->environment->translate('Image lightbox');
    }
    // The lists of fonts are filled only in PRO, so the free form sends no font
    foreach (['headerFontFamily', 'cellFontFamily'] as $font) {
      if (!empty($settings['styles'][$font]) && $settings['styles'][$font] !== 'default') {
        $labels[] = $this->environment->translate('Table fonts');
        break;
      }
    }

    return $labels;
  }

  private function hasAny(array $values, array $keys)
  {
    foreach ($keys as $key) {
      if (!empty($values[$key])) {
        return true;
      }
    }

    return false;
  }

  /**
   * Why PRO is off here, and where to bring it back.
   */
  private function getProState()
  {
    if (class_exists('SupsysticTablesPro_Loader', false)) {
      return ['state' => 'license', 'url' => admin_url('admin.php?page=supsystic-tables&module=license')];
    }
    if (file_exists(WP_PLUGIN_DIR . '/tables-generator-pro/index.php')) {
      return ['state' => 'plugin', 'url' => admin_url('plugins.php')];
    }

    return ['state' => 'buy', 'url' => ''];
  }

  /**
   * The editor page is being built: WordPress prints what is enqueued now, in the head or in the footer.
   */
  private function enqueueAssets()
  {
    $location = untrailingslashit(plugin_dir_url(dirname(__FILE__))) . '/assets';
    $version = $this->environment->getConfig()->get('plugin_version') . '-guard-2';

    wp_enqueue_style('supsystic-tables-pro-guard-css', $location . '/css/tables.pro-guard.css', [], $version);
    wp_enqueue_script('supsystic-tables-pro-guard-js', $location . '/js/tables.pro-guard.js', ['jquery'], $version, true);
  }
}
