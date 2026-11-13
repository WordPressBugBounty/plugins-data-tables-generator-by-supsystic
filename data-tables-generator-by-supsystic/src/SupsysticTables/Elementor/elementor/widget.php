<?php

namespace Elementor;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Elementor widget that inserts a Data Tables by Supsystic table, picked
 * from a dropdown, and previews it live in the editor.
 */
class Widget_Supsystic_Data_Tables extends Widget_Base
{
  public function get_name()
  {
    return 'supsystic_data_tables';
  }

  public function get_title()
  {
    return esc_html__('Data Tables by Supsystic', 'supsystic_tables');
  }

  public function get_icon()
  {
    return 'eicon-table';
  }

  public function get_categories()
  {
    return ['general', 'basic'];
  }

  public function get_keywords()
  {
    return ['table', 'data table', 'pricing table', 'supsystic'];
  }

  /**
   * The widget has no content_template(), so Elementor re-renders it via a
   * PHP round-trip on every settings change; this tells the editor to
   * reload the whole preview iframe for that round-trip instead of trying
   * to patch the DOM in place (same approach Elementor's own core Shortcode
   * widget uses).
   */
  public function is_reload_preview_required()
  {
    return true;
  }

  protected function register_controls()
  {
    $this->start_controls_section('section_supsystic_data_table', [
      'label' => esc_html__('Table', 'supsystic_tables'),
    ]);

    $options = $this->getTableOptions();

    $this->add_control('table_id', [
      'label' => esc_html__('Select table', 'supsystic_tables'),
      'type' => Controls_Manager::SELECT,
      'options' => $options ?: ['' => esc_html__('No tables yet - create one first', 'supsystic_tables')],
      'default' => $options ? (string) array_key_first($options) : '',
    ]);

    $this->end_controls_section();
  }

  protected function render()
  {
    $settings = $this->get_settings_for_display();
    $tableId = !empty($settings['table_id']) ? (int) $settings['table_id'] : 0;

    if (!$tableId) {
      if (Plugin::$instance->editor->is_edit_mode()) {
        printf(
          '<div class="elementor-alert elementor-alert-info">%s</div>',
          esc_html__('Select a Data Table to display it here.', 'supsystic_tables')
        );
      }
      return;
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- table markup is built/escaped by the plugin's own shortcode renderer.
    echo supsystic_tables_get($tableId);
  }

  /**
   * @return array id => label
   */
  private function getTableOptions()
  {
    $model = new \SupsysticTables_Tables_Model_Tables();
    $options = [];

    foreach ($model->getOptionsForSelect() as $id => $label) {
      $options[(string) $id] = $label;
    }

    return $options;
  }
}
