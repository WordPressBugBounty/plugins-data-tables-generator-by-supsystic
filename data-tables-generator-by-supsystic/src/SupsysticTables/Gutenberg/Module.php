<?php

/**
 * Registers a Gutenberg block for Data Tables by Supsystic: pick a table
 * from a dropdown in the block editor, no live preview there (the block
 * editor's iframed canvas doesn't load this plugin's display JS/CSS, so a
 * preview would show unstyled/non-interactive at best - see git history for
 * what was tried). The picked table renders normally on the published page,
 * same as the shortcode and the classic widget.
 */
class SupsysticTables_Gutenberg_Module extends SupsysticTables_Core_BaseModule
{
  const BLOCK_NAME = 'supsystic-tables/data-table';

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    add_action('init', [$this, 'registerBlock']);
  }

  public function registerBlock()
  {
    if (!function_exists('register_block_type') || !function_exists('wp_register_script')) {
      return;
    }

    $blockJson = $this->getLocation() . '/block/block.json';
    if (!is_file($blockJson)) {
      return;
    }

    $scriptHandle = 'supsystic-tables-gutenberg-block';
    $config = $this->getEnvironment()->getConfig();

    wp_register_script(
      $scriptHandle,
      $this->getLocationUrl() . '/assets/js/block.js',
      ['wp-blocks', 'wp-element', 'wp-components', 'wp-i18n'],
      $config->get('plugin_version'),
      true
    );

    wp_localize_script($scriptHandle, 'SupsysticTablesBlockData', [
      'tables' => $this->getTableOptionsForEditor(),
    ]);

    register_block_type($blockJson, [
      'render_callback' => [$this, 'render'],
    ]);
  }

  /**
   * Block render_callback - only runs on the published page, reuses the
   * shortcode renderer, same as the Elementor widget and the classic widget.
   *
   * @param array $attributes
   * @return string
   */
  public function render($attributes)
  {
    $tableId = !empty($attributes['tableId']) ? (int) $attributes['tableId'] : 0;

    if (!$tableId) {
      return '';
    }

    return supsystic_tables_get($tableId);
  }

  /**
   * @return array [{label, value}, ...] for the block's SelectControl
   */
  private function getTableOptionsForEditor()
  {
    $model = new SupsysticTables_Tables_Model_Tables();
    $options = [];

    foreach ($model->getOptionsForSelect() as $id => $label) {
      $options[] = ['label' => $label, 'value' => (string) $id];
    }

    return $options;
  }
}
