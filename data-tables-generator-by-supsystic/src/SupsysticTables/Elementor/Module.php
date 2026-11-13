<?php

/**
 * Registers the "Data Tables by Supsystic" Elementor widget, so a table can
 * be picked from a dropdown and previewed live in the Elementor editor
 * instead of only being insertable as a raw shortcode.
 */
class SupsysticTables_Elementor_Module extends SupsysticTables_Core_BaseModule
{
  private $widgetRegistered = false;

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    if ($this->isElementorRenderContext()) {
      // See the matching comment in SupsysticTables_Tables_Module::loadDataTables():
      // Elementor's editor ajax round-trip is is_admin()=true but not our
      // plugin's own page, so the DataTables assets our widget's preview
      // needs would otherwise be skipped.
      add_filter('supsystic_tables_force_load_display_assets', '__return_true');
    }

    if (defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, '3.5.0', '<')) {
      add_action('elementor/widgets/widgets_registered', [$this, 'registerWidget']);
    } else {
      add_action('elementor/widgets/register', [$this, 'registerWidget']);
    }
  }

  /**
   * Whether the current request is Elementor rendering/editing a page
   * (the editor screen itself, or its ajax widget-render round-trip).
   *
   * @return bool
   */
  private function isElementorRenderContext()
  {
    $ajaxAction = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';
    $pageAction = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';

    return $ajaxAction === 'elementor_ajax' || $pageAction === 'elementor';
  }

  /**
   * @param \Elementor\Widgets_Manager|null $widgetsManager
   */
  public function registerWidget($widgetsManager = null)
  {
    if ($this->widgetRegistered) {
      return;
    }

    if (!did_action('elementor/loaded') || !class_exists('\Elementor\Widget_Base')) {
      return;
    }

    $widgetFile = $this->getLocation() . '/elementor/widget.php';
    if (!class_exists('\Elementor\Widget_Supsystic_Data_Tables') && is_file($widgetFile)) {
      require_once $widgetFile;
    }

    if (!class_exists('\Elementor\Widget_Supsystic_Data_Tables')) {
      return;
    }

    $widget = new \Elementor\Widget_Supsystic_Data_Tables();
    $this->widgetRegistered = true;

    if ($widgetsManager && method_exists($widgetsManager, 'register')) {
      $widgetsManager->register($widget);
    } elseif ($widgetsManager && method_exists($widgetsManager, 'register_widget_type')) {
      $widgetsManager->register_widget_type($widget);
    } elseif (isset(\Elementor\Plugin::$instance->widgets_manager)) {
      $manager = \Elementor\Plugin::$instance->widgets_manager;
      method_exists($manager, 'register') ? $manager->register($widget) : $manager->register_widget_type($widget);
    }
  }
}
