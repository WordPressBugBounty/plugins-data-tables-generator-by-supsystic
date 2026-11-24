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

    // The editor's preview iframe shows the widget's HTML, but the shortcode never runs in that request, so the
    // table scripts and styles it would enqueue are missing there and the table stays hidden (B011).
    add_action('elementor/preview/enqueue_scripts', [$this, 'enqueuePreviewAssets']);
    // The Supsystic widget icon and the Edit link of the panel (plugins/.claude/fix/shared/builder-blocks-branding.md)
    add_action('elementor/editor/after_enqueue_styles', [$this, 'addEditorIconStyle']);
    add_action('elementor/editor/after_enqueue_scripts', [$this, 'addEditorLinkScript']);
    // The shared "Supsystic" category at the top of the panel (plugins/.claude/fix/shared/gutenberg-supsystic-category.md)
    add_action('elementor/elements/categories_registered', [$this, 'addSupsysticCategory'], 5);

    if (defined('ELEMENTOR_VERSION') && version_compare(ELEMENTOR_VERSION, '3.5.0', '<')) {
      add_action('elementor/widgets/widgets_registered', [$this, 'registerWidget']);
    } else {
      add_action('elementor/widgets/register', [$this, 'registerWidget']);
    }
  }

  /**
   * Adds the "Supsystic" panel category once (another Supsystic plugin may have added it) and moves it to the top.
   * Elementor has no public API for the order, so this uses reflection, guarded: if anything differs in this Elementor
   * version, the category simply stays where add_category() put it.
   *
   * @param object $elementsManager \Elementor\Elements_Manager
   */
  public function addSupsysticCategory($elementsManager)
  {
    if (!is_object($elementsManager) || !method_exists($elementsManager, 'add_category')) {
      return;
    }
    $categories = method_exists($elementsManager, 'get_categories') ? $elementsManager->get_categories() : [];
    if (isset($categories['supsystic'])) {
      return;
    }
    $elementsManager->add_category('supsystic', ['title' => 'Supsystic', 'icon' => 'supsystic-el-icon']);
    try {
      $property = new \ReflectionProperty($elementsManager, 'categories');
      if (PHP_VERSION_ID < 80100) {
        $property->setAccessible(true);
      }
      $all = $property->getValue($elementsManager);
      if (is_array($all) && isset($all['supsystic'])) {
        $property->setValue($elementsManager, ['supsystic' => $all['supsystic']] + $all);
      }
    } catch (\Throwable $e) {
    }
  }

  /**
   * Elementor panel icons are font glyphs: the Supsystic PNG as a mask in the colour of the other widget icons.
   */
  public function addEditorIconStyle()
  {
    $icon = esc_url($this->getEnvironment()->getConfig()->get('plugin_url') . '/app/assets/img/supsystic-adminbar.png');
    $css = '.elementor-element .icon .supsystic-el-icon::before, .supsystic-el-icon::before { content: ""; display: inline-block; width: 1em; height: 1em; vertical-align: middle; background-color: currentColor;'
      . ' -webkit-mask: url("' . $icon . '") center / contain no-repeat; mask: url("' . $icon . '") center / contain no-repeat; }';
    if (wp_style_is('elementor-editor', 'enqueued') || wp_style_is('elementor-editor', 'registered')) {
      wp_add_inline_style('elementor-editor', $css);
    }
  }

  /**
   * "Edit this table in Data Tables" in the widget panel opens the table picked in that panel.
   */
  public function addEditorLinkScript()
  {
    $js = "jQuery(document).on('click', '.supsystic-tables-el-edit', function (event) {"
      . " var panel = jQuery(this).closest('#elementor-panel, .elementor-panel'), id = panel.find('.elementor-control-table_id select').val();"
      . " if (!id) { event.preventDefault(); return; }"
      . " this.href = this.getAttribute('data-base') + encodeURIComponent(id); });";
    if (wp_script_is('elementor-editor', 'enqueued') || wp_script_is('elementor-editor', 'registered')) {
      wp_add_inline_script('elementor-editor', $js);
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

  /**
   * Elementor editor preview only (fired inside wp_enqueue_scripts, after the plugin registered its assets):
   * enqueues what a page with the shortcode gets, so tables in the preview initialise like on the published page.
   */
  public function enqueuePreviewAssets()
  {
    $environment = $this->getEnvironment();
    $tables = $environment->getModule('tables');
    $ui = $environment->getModule('ui');

    if (!$tables || !$ui || !method_exists($tables, 'filterAssets')) {
      return;
    }

    // The display scripts and styles every table needs, loaded the same way doShortcode() loads them.
    foreach (array_filter($ui->getAssets(), [$tables, 'filterAssets']) as $asset) {
      $asset->load();
    }

    // A pricing table dropped into the preview comes by ajax, so its styles must already be on the page.
    $pricing = $environment->getModule('pricing');
    if ($pricing && method_exists($pricing, 'enqueueFrontendAssets')) {
      $pricing->registerFrontendAssets();
      $pricing->enqueueFrontendAssets();
      // PRO's pricing script (billing switch, AJAX toggle, schedule; registered by PRO only): a pricing table rendered
      // here comes by ajax and cannot enqueue it, so the switch of a widget dropped later would not click
      if (wp_script_is('supsystic-pricing', 'registered')) {
        wp_enqueue_script('supsystic-pricing');
      }
    }

    // Tables already placed on this page: render each once (output discarded) so their own assets are enqueued
    // too - PRO features, pricing themes, fonts, language data.
    foreach ($this->getPreviewTableIds() as $tableId) {
      $tables->doShortcode(['id' => $tableId]);
    }
  }

  /**
   * IDs of the tables used by this widget on the page shown in the Elementor preview.
   *
   * @return int[]
   */
  private function getPreviewTableIds()
  {
    $ids = [];

    try {
      $elementor = \Elementor\Plugin::$instance;
      if (empty($elementor->preview) || empty($elementor->documents) || !method_exists($elementor->preview, 'get_post_id')) {
        return $ids;
      }
      $postId = (int) $elementor->preview->get_post_id();
      $document = $postId ? (method_exists($elementor->documents, 'get_doc_or_auto_save') ? $elementor->documents->get_doc_or_auto_save($postId) : $elementor->documents->get($postId)) : null;
      if (!$document || !method_exists($document, 'get_elements_data')) {
        return $ids;
      }

      $walk = function ($elements) use (&$walk, &$ids) {
        foreach ((array) $elements as $element) {
          if (isset($element['widgetType']) && $element['widgetType'] === 'supsystic_data_tables' && !empty($element['settings']['table_id'])) {
            $ids[] = (int) $element['settings']['table_id'];
          }
          if (!empty($element['elements'])) {
            $walk($element['elements']);
          }
        }
      };
      $walk($document->get_elements_data());
    } catch (\Throwable $e) {
      return [];
    }

    return array_values(array_unique(array_filter($ids)));
  }
}
