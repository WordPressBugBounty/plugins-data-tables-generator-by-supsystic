<?php

/**
 * GrapesJS pricing builder inside the Data Tables MVC environment.
 */
class SupsysticTables_Pricing_Module extends SupsysticTables_Core_BaseModule
{
  public function onInit()
  {
    parent::onInit();
    $this->ensureBuilderSchema();
    add_action('wp_enqueue_scripts', [$this, 'registerFrontendAssets']);
    add_action('wp_ajax_supsystic_pricing_render', [$this, 'ajaxRender']);
    add_action('wp_ajax_nopriv_supsystic_pricing_render', [$this, 'ajaxRender']);
    add_action('wp_ajax_supsystic_pricing_woo_cart', [$this, 'ajaxWooCart']);
    add_action('wp_ajax_nopriv_supsystic_pricing_woo_cart', [$this, 'ajaxWooCart']);
  }

  private function ensureBuilderSchema()
  {
    if (get_option('supsystic_tbl_pricing_meta_schema') === '1') {
      return;
    }
    global $wpdb;
    $name = $wpdb->prefix . $this->getConfig()->get('db_prefix') . 'tables';
    $column = $wpdb->get_row("SHOW COLUMNS FROM {$name} LIKE 'meta'");
    if (!$column) {
      return;
    }
    if (strtolower($column->Type) !== 'longtext') {
      $wpdb->query("ALTER TABLE {$name} MODIFY COLUMN `meta` LONGTEXT NULL");
      if ($wpdb->last_error) {
        return;
      }
    }
    update_option('supsystic_tbl_pricing_meta_schema', '1', false);
  }

  public function registerFrontendAssets()
  {
    $url = plugin_dir_url(__FILE__) . 'assets/';
    wp_register_style('supsystic-pricing-fonts', $url . 'css/pricing.fonts.css', [], '1.0.0');
    wp_register_style('supsystic-pricing-icons', $url . 'css/font-awesome.min.css', [], '4.7.0');
    wp_register_style('supsystic-pricing', $url . 'css/pricing.frontend.css', ['supsystic-pricing-fonts', 'supsystic-pricing-icons'], '1.6.4');
    wp_register_script('supsystic-pricing-base', $url . 'js/pricing.frontend.js', [], '1.2.5', true);
    wp_enqueue_style('supsystic-pricing');
  }

  public function getCanvasStyles()
  {
    $url = plugin_dir_url(__FILE__) . 'assets/';
    return [add_query_arg('ver', '1.0.0', $url . 'css/pricing.fonts.css'), add_query_arg('ver', '4.7.0', $url . 'css/font-awesome.min.css'), add_query_arg('ver', '1.6.4', $url . 'css/pricing.frontend.css')];
  }

  public function getFontLibrary()
  {
    static $library;
    if ($library === null) {
      $json = file_get_contents(__DIR__ . '/assets/data/font-library.json');
      $library = json_decode($json, true);
      if (!is_array($library)) {
        $library = ['local' => [], 'google' => [], 'system' => []];
      }
      $library['local'] = ['Lato', 'Libre Baskerville', 'Montserrat', 'Playfair Display SC', 'PT Sans', 'Raleway', 'Roboto'];
    }
    return $library;
  }

  public function getIconLibrary()
  {
    $icons = json_decode(file_get_contents(__DIR__ . '/assets/data/icon-library.json'), true);
    return is_array($icons) ? $icons : [];
  }

  public function getCapabilities()
  {
    return [
      'toggle' => false,
      'ajaxToggle' => false,
      'schedule' => false,
      'source' => true,
      'premiumTemplates' => false,
      'customCss' => true,
      'proInstalled' => class_exists('SupsysticTablesPro_Loader', false),
    ];
  }

  public function getTemplates()
  {
    return [
      'classic' => ['label' => 'Classic', 'accent' => '#2673d9', 'surface' => '#ffffff'],
      'minimal' => ['label' => 'Minimal', 'accent' => '#183153', 'surface' => '#f5f7fa'],
      'warm' => ['label' => 'Warm', 'accent' => '#bb573d', 'surface' => '#fff7f2'],
    ];
  }

  /**
   * The premium catalog is kept in Free so the editor can show the same theme
   * gallery before an upgrade. PRO merges these definitions into getTemplates().
   */
  public function getPremiumTemplates()
  {
    return [
      'gradient' => ['label' => 'Gradient', 'accent' => '#8052d9', 'surface' => '#f8f3ff', 'premium' => true],
      'dark' => ['label' => 'Dark', 'accent' => '#5bd6b2', 'surface' => '#162336', 'premium' => true],
      'rainbow' => ['label' => 'Rainbow', 'accent' => '#7c3aed', 'surface' => '#fff7ed', 'premium' => true],
      'flat-table' => ['label' => 'Flat Table', 'accent' => '#2563eb', 'surface' => '#ffffff', 'premium' => true],
      'gradient-standard' => ['label' => 'Gradient Standard', 'accent' => '#ec4899', 'surface' => '#fff1f7', 'premium' => true],
      'okul' => ['label' => 'Okul', 'accent' => '#0f766e', 'surface' => '#f0fdfa', 'premium' => true],
      'botein' => ['label' => 'Botein', 'accent' => '#ea580c', 'surface' => '#fff7ed', 'premium' => true],
      'becrux' => ['label' => 'Becrux', 'accent' => '#0891b2', 'surface' => '#ecfeff', 'premium' => true],
      'packages-1' => ['label' => 'Packages 1', 'accent' => '#4f46e5', 'surface' => '#eef2ff', 'premium' => true],
      'izar' => ['label' => 'Izar', 'accent' => '#db2777', 'surface' => '#fdf2f8', 'premium' => true],
      'keid' => ['label' => 'Keid', 'accent' => '#16a34a', 'surface' => '#f0fdf4', 'premium' => true],
      'wezen' => ['label' => 'Wezen', 'accent' => '#d97706', 'surface' => '#fffbeb', 'premium' => true],
      'extended-table' => ['label' => 'Extended Table', 'accent' => '#334155', 'surface' => '#f8fafc', 'premium' => true],
      'arrakis' => ['label' => 'Arrakis', 'accent' => '#b45309', 'surface' => '#fef3c7', 'premium' => true],
      'toliman' => ['label' => 'Toliman', 'accent' => '#7e22ce', 'surface' => '#faf5ff', 'premium' => true],
      'startup' => ['label' => 'Startup', 'accent' => '#0284c7', 'surface' => '#f0f9ff', 'premium' => true],
      'christmas-classic' => ['label' => 'Classic Christmas', 'accent' => '#b91c1c', 'surface' => '#f7fff8', 'premium' => true],
      'christmas-minimal' => ['label' => 'Minimal Xmas', 'accent' => '#0f766e', 'surface' => '#f7fbff', 'premium' => true],
      'christmas-luxury' => ['label' => 'Luxury Christmas Sale', 'accent' => '#d4af37', 'surface' => '#10251c', 'premium' => true],
      'new-year-fireworks' => ['label' => 'Fireworks New Year', 'accent' => '#8b5cf6', 'surface' => '#101632', 'premium' => true],
      'new-year-minimal' => ['label' => 'Minimal New Year', 'accent' => '#2563eb', 'surface' => '#f8fbff', 'premium' => true],
      'new-year-gold' => ['label' => 'Gold New Year Offer', 'accent' => '#eab308', 'surface' => '#17130b', 'premium' => true],
      'black-friday-neon' => ['label' => 'Dark Neon Black Friday', 'accent' => '#f6ff00', 'surface' => '#090909', 'premium' => true],
      'black-friday-minimal' => ['label' => 'Minimal Black Friday', 'accent' => '#111827', 'surface' => '#ffffff', 'premium' => true],
      'black-friday-aggressive' => ['label' => 'Aggressive Sale Black Friday', 'accent' => '#ef1d2d', 'surface' => '#fff200', 'premium' => true],
      'cyber-monday-tech' => ['label' => 'Tech Blue Cyber Monday', 'accent' => '#06b6d4', 'surface' => '#07182e', 'premium' => true],
      'cyber-monday-neon' => ['label' => 'Neon Digital Cyber Monday', 'accent' => '#a3ff12', 'surface' => '#130626', 'premium' => true],
      'cyber-monday-minimal' => ['label' => 'Minimal Cyber Monday', 'accent' => '#2563eb', 'surface' => '#f4f8ff', 'premium' => true],
      'womens-day-floral' => ['label' => 'Elegant Floral Women’s Day', 'accent' => '#c026d3', 'surface' => '#fff7fd', 'premium' => true],
      'womens-day-minimal' => ['label' => 'Minimal Women’s Day', 'accent' => '#9d174d', 'surface' => '#fffdfb', 'premium' => true],
      'womens-day-soft' => ['label' => 'Soft Pink Promo Women’s Day', 'accent' => '#ec4899', 'surface' => '#fff1f7', 'premium' => true],
      'valentine-romantic' => ['label' => 'Romantic Valentine', 'accent' => '#e11d48', 'surface' => '#fff5f7', 'premium' => true],
      'valentine-minimal' => ['label' => 'Minimal Valentine', 'accent' => '#be123c', 'surface' => '#ffffff', 'premium' => true],
      'valentine-luxury' => ['label' => 'Luxury Red Valentine', 'accent' => '#d4af37', 'surface' => '#4a0b19', 'premium' => true],
      'easter-pastel' => ['label' => 'Pastel Easter', 'accent' => '#8b5cf6', 'surface' => '#fffaf2', 'premium' => true],
      'easter-cute' => ['label' => 'Cute Easter', 'accent' => '#f472b6', 'surface' => '#f5fff7', 'premium' => true],
      'easter-spring' => ['label' => 'Elegant Spring Easter', 'accent' => '#3f8f6b', 'surface' => '#fffdf6', 'premium' => true],
      'halloween-dark' => ['label' => 'Dark Halloween', 'accent' => '#f97316', 'surface' => '#120d19', 'premium' => true],
      'halloween-fun' => ['label' => 'Fun Halloween', 'accent' => '#7c3aed', 'surface' => '#fff7e8', 'premium' => true],
      'halloween-neon' => ['label' => 'Neon Halloween Sale', 'accent' => '#a3ff12', 'surface' => '#10051d', 'premium' => true],
      'ramadan-elegant' => ['label' => 'Elegant Ramadan', 'accent' => '#0f766e', 'surface' => '#f7f3e8', 'premium' => true],
      'eid-lantern-moon' => ['label' => 'Lantern & Moon Eid', 'accent' => '#f2c14e', 'surface' => '#102b46', 'premium' => true],
      'ramadan-luxury' => ['label' => 'Luxury Gold Ramadan Offer', 'accent' => '#d6b25e', 'surface' => '#16130d', 'premium' => true],
      'gaming-cyberpunk' => ['label' => 'Cyberpunk Neon', 'accent' => '#fcee09', 'surface' => '#12031f', 'premium' => true],
      'gaming-witch-hunter' => ['label' => 'Witch Hunter', 'accent' => '#c8a96b', 'surface' => '#171714', 'premium' => true],
      'gaming-epic-mmo' => ['label' => 'Epic MMO', 'accent' => '#f2c94c', 'surface' => '#102a4c', 'premium' => true],
      'gaming-orc-warfront' => ['label' => 'Orcish Warfront', 'accent' => '#8abf39', 'surface' => '#2a1b12', 'premium' => true],
      'gaming-arctic-storm' => ['label' => 'Arctic Storm', 'accent' => '#68d7ff', 'surface' => '#0b1c35', 'premium' => true],
      'gaming-galactic-command' => ['label' => 'Galactic Command', 'accent' => '#22d3ee', 'surface' => '#07111f', 'premium' => true],
      'gaming-tactical-ops' => ['label' => 'Tactical Ops', 'accent' => '#d6a84f', 'surface' => '#20251f', 'premium' => true],
      'gaming-elven-chronicle' => ['label' => 'Elven Chronicle', 'accent' => '#b7d6ff', 'surface' => '#20223a', 'premium' => true],
      'gaming-post-apocalypse' => ['label' => 'Post-Apocalypse', 'accent' => '#c6ff35', 'surface' => '#242416', 'premium' => true],
      'gaming-viking-saga' => ['label' => 'Viking Saga', 'accent' => '#9ed3e8', 'surface' => '#1b2930', 'premium' => true],
      'gaming-space-guardian' => ['label' => 'Space Guardian', 'accent' => '#9c7cff', 'surface' => '#14132c', 'premium' => true],
      'gaming-battle-royale' => ['label' => 'Battle Royale', 'accent' => '#ff4fb2', 'surface' => '#172263', 'premium' => true],
      'gaming-gothic-souls' => ['label' => 'Gothic Souls', 'accent' => '#c8a367', 'surface' => '#1a171c', 'premium' => true],
      'gaming-retro-arcade' => ['label' => 'Retro Arcade', 'accent' => '#00ffd5', 'surface' => '#18062e', 'premium' => true],
      'gaming-voxel-craft' => ['label' => 'Voxel Craft', 'accent' => '#6fbd46', 'surface' => '#dff4c9', 'premium' => true],
      'gaming-blockverse' => ['label' => 'Blockverse', 'accent' => '#00a2ff', 'surface' => '#f2f7fb', 'premium' => true],
      'collection-glass-saas' => ['label' => 'Glass SaaS', 'accent' => '#6d5dfc', 'surface' => '#f5f7ff', 'premium' => true],
      'collection-bento-pricing' => ['label' => 'Bento Pricing', 'accent' => '#ff6b35', 'surface' => '#fffaf4', 'premium' => true],
      'collection-swiss-editorial' => ['label' => 'Swiss Editorial', 'accent' => '#e31b23', 'surface' => '#ffffff', 'premium' => true],
      'collection-corporate-navy' => ['label' => 'Corporate Navy', 'accent' => '#174ea6', 'surface' => '#f5f8fc', 'premium' => true],
      'collection-aurora-saas' => ['label' => 'Aurora SaaS', 'accent' => '#16b8a6', 'surface' => '#f4fffc', 'premium' => true],
      'collection-monochrome-studio' => ['label' => 'Monochrome Studio', 'accent' => '#171717', 'surface' => '#f7f7f5', 'premium' => true],
      'collection-ai-neural' => ['label' => 'AI Neural Network', 'accent' => '#7c3aed', 'surface' => '#110b24', 'premium' => true],
      'collection-holographic' => ['label' => 'Holographic Interface', 'accent' => '#705cff', 'surface' => '#f7f8ff', 'premium' => true],
      'collection-terminal-matrix' => ['label' => 'Terminal Matrix', 'accent' => '#39ff88', 'surface' => '#07130d', 'premium' => true],
      'collection-cloud-infrastructure' => ['label' => 'Cloud Infrastructure', 'accent' => '#3182f6', 'surface' => '#f3f8ff', 'premium' => true],
      'collection-fintech-dashboard' => ['label' => 'Fintech Dashboard', 'accent' => '#18a572', 'surface' => '#f3faf7', 'premium' => true],
      'collection-developer-api' => ['label' => 'Developer API', 'accent' => '#ffb020', 'surface' => '#15171d', 'premium' => true],
      'collection-art-deco-gold' => ['label' => 'Art Deco Gold', 'accent' => '#c9a34e', 'surface' => '#17130f', 'premium' => true],
      'collection-luxury-marble' => ['label' => 'Luxury Marble', 'accent' => '#97795c', 'surface' => '#fbfaf7', 'premium' => true],
      'collection-jewelry-noir' => ['label' => 'Jewelry Noir', 'accent' => '#d7b977', 'surface' => '#111014', 'premium' => true],
      'collection-fashion-editorial' => ['label' => 'Fashion Editorial', 'accent' => '#c2185b', 'surface' => '#fffafb', 'premium' => true],
      'collection-beauty-cosmetics' => ['label' => 'Beauty Cosmetics', 'accent' => '#d97683', 'surface' => '#fff5f3', 'premium' => true],
      'collection-coffee-house' => ['label' => 'Coffee House', 'accent' => '#9a5b36', 'surface' => '#fff8ed', 'premium' => true],
      'collection-organic-farm' => ['label' => 'Organic Farm', 'accent' => '#4d8b41', 'surface' => '#f7f9ee', 'premium' => true],
      'collection-restaurant-menu' => ['label' => 'Restaurant Menu', 'accent' => '#a33b2b', 'surface' => '#fffaf0', 'premium' => true],
      'collection-tropical-travel' => ['label' => 'Tropical Travel', 'accent' => '#00a89d', 'surface' => '#f1fffb', 'premium' => true],
      'collection-airline-sky' => ['label' => 'Airline Sky', 'accent' => '#2176d2', 'surface' => '#f3f9ff', 'premium' => true],
      'collection-medical-clean' => ['label' => 'Medical Clean', 'accent' => '#168aad', 'surface' => '#f4fbfc', 'premium' => true],
      'collection-fitness-energy' => ['label' => 'Fitness Energy', 'accent' => '#ff4d19', 'surface' => '#17191b', 'premium' => true],
      'collection-yoga-wellness' => ['label' => 'Yoga Wellness', 'accent' => '#8d6cab', 'surface' => '#fbf8ff', 'premium' => true],
      'collection-online-academy' => ['label' => 'Online Academy', 'accent' => '#3157d5', 'surface' => '#f6f8ff', 'premium' => true],
      'collection-kids-learning' => ['label' => 'Kids Learning', 'accent' => '#ff6f61', 'surface' => '#fffaf2', 'premium' => true],
      'collection-memphis-pop' => ['label' => 'Memphis Pop', 'accent' => '#ff3d81', 'surface' => '#fff9e8', 'premium' => true],
      'collection-bauhaus-grid' => ['label' => 'Bauhaus Grid', 'accent' => '#e53935', 'surface' => '#fffdf5', 'premium' => true],
      'collection-brutalist-web' => ['label' => 'Brutalist Web', 'accent' => '#1947ff', 'surface' => '#ffffff', 'premium' => true],
      'collection-paper-cut' => ['label' => 'Paper Cut', 'accent' => '#7457d9', 'surface' => '#f7f1ff', 'premium' => true],
      'collection-claymorphism' => ['label' => 'Claymorphism', 'accent' => '#7865d6', 'surface' => '#edeaff', 'premium' => true],
      'collection-automotive-racing' => ['label' => 'Automotive Racing', 'accent' => '#e51b23', 'surface' => '#171719', 'premium' => true],
      'collection-real-estate' => ['label' => 'Real Estate Premium', 'accent' => '#a88644', 'surface' => '#f9f7f1', 'premium' => true],
      'collection-industrial' => ['label' => 'Industrial Construction', 'accent' => '#e6a619', 'surface' => '#202326', 'premium' => true],
      'collection-eco-renewable' => ['label' => 'Eco Renewable', 'accent' => '#2d9d5b', 'surface' => '#f1fbf3', 'premium' => true],
      'collection-marketplace' => ['label' => 'Marketplace Commerce', 'accent' => '#ff5c35', 'surface' => '#fff8f5', 'premium' => true],
      'collection-japanese-zen' => ['label' => 'Japanese Zen', 'accent' => '#b33a3a', 'surface' => '#faf7f0', 'premium' => true],
      'collection-scandinavian' => ['label' => 'Scandinavian Hygge', 'accent' => '#557c7a', 'surface' => '#f8f6ef', 'premium' => true],
      'collection-mediterranean' => ['label' => 'Mediterranean Coast', 'accent' => '#1676b8', 'surface' => '#fffdf5', 'premium' => true],
      'collection-arabian-mosaic' => ['label' => 'Arabian Mosaic', 'accent' => '#16796f', 'surface' => '#f9f4e7', 'premium' => true],
      'collection-solarpunk-garden' => ['label' => 'Solarpunk Garden', 'accent' => '#32a852', 'surface' => '#f5fbed', 'premium' => true],
      'collection-y2k-chrome' => ['label' => 'Y2K Chrome', 'accent' => '#7c5cff', 'surface' => '#f2f1fa', 'premium' => true],
    ];
  }

  public function render($table)
  {
    $builder = $this->getEnvironment()->getModule('core')->getModelsFactory()->get('builder', 'pricing');
    $data = $builder->getBuilder($table);
    if (!isset($this->getTemplates()[$data['template']])) {
      $data['html'] = str_replace('st-pricing-' . $data['template'], 'st-pricing-classic', $data['html']);
      $data['template'] = 'classic';
    }
    $this->registerFrontendAssets();
    wp_enqueue_style('supsystic-pricing');
    wp_enqueue_script('supsystic-pricing-base');
    foreach ((array) ($data['fonts'] ?? []) as $font) {
      if ($this->isGoogleFont($font)) {
        $handle = 'supsystic-pricing-font-' . sanitize_key($font);
        wp_enqueue_style($handle, 'https://fonts.googleapis.com/css2?family=' . str_replace('%20', '+', rawurlencode($font)) . ':wght@300;400;500;600;700;800&display=swap', [], null);
      }
    }
    if ((!empty($data['toggle']) && $this->getCapabilities()['toggle']) || (!empty($data['ajaxToggle']) && $this->getCapabilities()['ajaxToggle']) || ($this->getCapabilities()['schedule'] && strpos($data['html'], 'data-visible-') !== false)) {
      wp_enqueue_script('supsystic-pricing');
    }
    return $this->getEnvironment()->getTwig()->render('@pricing/frontend.twig', [
      'table' => $table,
      'builder' => $data,
      'scopedCss' => $builder->scopeCss($data['css'], $table->id),
      'capabilities' => $this->getCapabilities(),
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'ajaxNonce' => wp_create_nonce('supsystic_pricing_ajax'),
      'toggleVariantsJson' => wp_json_encode((object) ($data['toggleVariants'] ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
      'toggleVariantStylesJson' => $this->getVariantStylesJson($data, $builder, $table->id),
      'wooActive' => $this->isWooCommerceAvailable(),
      'wooNonce' => wp_create_nonce('supsystic_pricing_woo_cart'),
    ]);
  }

  public function ajaxRender()
  {
    if (!$this->getCapabilities()['ajaxToggle'] || !check_ajax_referer('supsystic_pricing_ajax', 'nonce', false)) {
      wp_send_json_error(['message' => 'Invalid request.'], 403);
    }
    $id = isset($_POST['id']) ? absint($_POST['id']) : 0;
    $table = $this->getEnvironment()->getModule('core')->getModelsFactory()->get('tables')->getById($id);
    if (!$table || $table->table_type !== 'pricing_table') {
      wp_send_json_error(['message' => 'Pricing table not found.'], 404);
    }
    $builder = $this->getEnvironment()->getModule('core')->getModelsFactory()->get('builder', 'pricing');
    $data = $builder->getBuilder($table);
    $html = $this->getEnvironment()->getTwig()->render('@pricing/content.twig', [
      'table' => $table,
      'builder' => $data,
      'capabilities' => $this->getCapabilities(),
      'scopedCss' => $builder->scopeCss($data['css'], $table->id),
      'toggleVariantsJson' => wp_json_encode((object) ($data['toggleVariants'] ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
      'toggleVariantStylesJson' => $this->getVariantStylesJson($data, $builder, $table->id),
    ]);
    wp_send_json_success(['html' => $html]);
  }

  private function getVariantStylesJson(array $data, $builder, $tableId)
  {
    $styles = [];
    foreach ((array) ($data['toggleVariantStyles'] ?? []) as $key => $css) {
      $styles[sanitize_key($key)] = $builder->scopeCss($css, $tableId);
    }
    return wp_json_encode((object) $styles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
  }

  public function isWooCommerceAvailable()
  {
    return class_exists('WooCommerce') && function_exists('wc_get_products');
  }

  public function getWooProducts()
  {
    if (!$this->isWooCommerceAvailable()) { return []; }
    $items = [];
    $products = wc_get_products([
      'status' => 'publish', 'limit' => 250, 'orderby' => 'name', 'order' => 'ASC', 'return' => 'objects',
    ]);
    foreach ($products as $product) {
      if (!$product) { continue; }
      if ($product->is_type('variable')) {
        foreach ($product->get_children() as $variationId) {
          $variation = wc_get_product($variationId);
          if (!$variation || !$variation->is_purchasable() || !$variation->is_in_stock()) { continue; }
          $items[] = [
            'id' => $variation->get_id(),
            'name' => wp_strip_all_tags($product->get_name() . ' — ' . wc_get_formatted_variation($variation, true, false, false)),
            'price' => $this->formatWooPrice($variation->get_price()),
          ];
        }
        continue;
      }
      if (!$product->is_purchasable() || !$product->is_in_stock()) { continue; }
      $items[] = [
        'id' => $product->get_id(),
        'name' => wp_strip_all_tags($product->get_name()),
        'price' => $this->formatWooPrice($product->get_price()),
      ];
    }
    return $items;
  }

  public function ajaxWooCart()
  {
    if (!check_ajax_referer('supsystic_pricing_woo_cart', 'nonce', false)) {
      wp_send_json_error(['message' => 'Invalid request.'], 403);
    }
    if (!$this->isWooCommerceAvailable()) {
      wp_send_json_error(['message' => 'WooCommerce is not available.'], 503);
    }
    if (function_exists('wc_load_cart') && (!function_exists('WC') || !WC()->cart)) { wc_load_cart(); }
    if (!function_exists('WC') || !WC()->cart) {
      wp_send_json_error(['message' => 'The cart is unavailable.'], 503);
    }
    $raw = isset($_POST['products']) ? wp_unslash($_POST['products']) : '';
    $lines = array_slice(array_filter(explode(',', (string) $raw)), 0, 20);
    $added = 0;
    foreach ($lines as $line) {
      $parts = array_map('trim', explode(':', $line, 2));
      $productId = absint($parts[0] ?? 0);
      $quantity = min(100, max(1, absint($parts[1] ?? 1)));
      $product = $productId ? wc_get_product($productId) : false;
      if (!$product || !$product->is_purchasable() || !$product->is_in_stock() || $product->is_type('variable')) { continue; }
      $variationId = $product->is_type('variation') ? $productId : 0;
      $parentId = $variationId ? $product->get_parent_id() : $productId;
      if (WC()->cart->add_to_cart($parentId, $quantity, $variationId)) { $added += $quantity; }
    }
    if (!$added) { wp_send_json_error(['message' => 'Choose an available product.'], 400); }
    $coupon = sanitize_text_field(isset($_POST['coupon']) ? wp_unslash($_POST['coupon']) : '');
    $couponApplied = true;
    if ($coupon && !WC()->cart->has_discount($coupon)) { $couponApplied = (bool) WC()->cart->apply_coupon($coupon); }
    WC()->cart->calculate_totals();
    $message = sprintf(_n('%d item added to cart.', '%d items added to cart.', $added), $added);
    if ($coupon && !$couponApplied) { $message .= ' ' . __('The coupon could not be applied.', 'supsystic_tables'); }
    wp_send_json_success([
      'message' => $message,
      'count' => WC()->cart->get_cart_contents_count(),
      'cartUrl' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
      'checkoutUrl' => function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : '',
    ]);
  }

  private function formatWooPrice($price)
  {
    $plain = html_entity_decode(wp_strip_all_tags(wc_price($price)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\x{00a0}+/u', ' ', $plain));
  }

  private function isGoogleFont($font)
  {
    $library = $this->getFontLibrary();
    return in_array($font, $library['google'], true);
  }

  public function afterUiLoaded(SupsysticTables_Ui_Module $ui)
  {
    parent::afterUiLoaded($ui);
    $environment = $this->getEnvironment();
    if (!$this->isPricingTableView()) {
      return;
    }
    add_action('admin_enqueue_scripts', [$this, 'enqueueEditorDependencies']);
    $version = $environment->getConfig()->get('plugin_version') . '-pricing-ui-92';
    $ui->add($ui->createStyle('supsystic-pricing-grapes-css')->setHookName('admin_enqueue_scripts')->setModuleSource($this, 'css/grapes.min.css')->setVersion($version));
    $ui->add($ui->createStyle('supsystic-pricing-icons-css')->setHookName('admin_enqueue_scripts')->setModuleSource($this, 'css/font-awesome.min.css')->setVersion($version));
    $ui->add($ui->createStyle('supsystic-pricing-editor-css')->setHookName('admin_enqueue_scripts')->setModuleSource($this, 'css/pricing.editor.css')->setVersion($version));
    $ui->add($ui->createScript('supsystic-pricing-grapes-js')->setHookName('admin_enqueue_scripts')->setModuleSource($this, 'js/grapes.js')->setVersion($version));
    $ui->add($ui->createScript('supsystic-pricing-style-bg-js')->setHookName('admin_enqueue_scripts')->setModuleSource($this, 'js/grapes.style.bg.min.js')->setVersion($version)->addDependency('supsystic-pricing-grapes-js'));
    $ui->add($ui->createScript('supsystic-pricing-editor-js')->setHookName('admin_enqueue_scripts')->setModuleSource($this, 'js/pricing.editor.js')->setVersion($version)->addDependency('jquery')->addDependency('tables-core')->addDependency('tables-chosen')->addDependency('supsystic-pricing-grapes-js')->addDependency('supsystic-pricing-style-bg-js'));
  }

  public function enqueueEditorDependencies()
  {
    wp_enqueue_media();
    wp_enqueue_style('tables-chosen');
    wp_enqueue_script('tables-chosen');
    if (function_exists('wp_enqueue_code_editor')) {
      wp_enqueue_code_editor(['type' => 'text/html']);
      wp_enqueue_code_editor(['type' => 'text/css']);
    }
  }

  public function isPricingTableView()
  {
    if (!is_admin() || !$this->getEnvironment()->isModule('tables', 'view')) {
      return false;
    }
    $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
    if (!$id) {
      return false;
    }
    global $wpdb;
    $name = $wpdb->prefix . $this->getConfig()->get('db_prefix') . 'tables';
    $type = $wpdb->get_var($wpdb->prepare("SELECT `table_type` FROM {$name} WHERE `id` = %d", $id));
    return $type === 'pricing_table';
  }
}
