<?php

/**
 * Moves the tables of Pricing Table by Supsystic into pricing tables of Data Tables, in one click.
 * Pricing Table is a classic plugin now: its plans, prices and rows live on here, with a theme of Data Tables.
 * The tables are read right from the database, so the move works also when Pricing Table is switched off.
 *
 * The page: admin.php?page=supsystic-tables&module=pricingimport (from=pricing-table when the user comes
 * from that plugin, ids=1,2 to tick only these tables). See supsystic_tables_pricing_import_url().
 */
class SupsysticTables_Pricingimport_Module extends SupsysticTables_Core_BaseModule
{
  const COUPON = 'PTFREE20';
  const COUPON_SOURCE_PRO = 'PTPRO50';
  // The plan of Data Tables PRO on supsystic.com, the same one Pricing Table sells.
  const PRO_PID = 3443;
  const SOURCE_PRO_FILE = 'price-table-supsystic-pro/price-table-supsystic-pro.php';

  public function onInit()
  {
    parent::onInit();
    if (!is_admin()) {
      return;
    }
    $environment = $this->getEnvironment();
    $environment->getDispatcher()->on('settings_cards', [$this, 'renderSettingsCard']);
    if ($environment->isModule('pricingimport')) {
      add_action('admin_enqueue_scripts', [$this, 'addAssets'], 999);
    }
    if ($environment->isModule('settings')) {
      add_action('admin_enqueue_scripts', [$this, 'addSettingsAssets'], 999);
    }
  }

  public function addAssets()
  {
    $url = untrailingslashit(plugin_dir_url(__FILE__)) . '/assets';
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-pti-4';
    wp_enqueue_style('supsystic-tables-pricing-import', $url . '/css/pricingimport.css', [], $version);
    wp_enqueue_script('supsystic-tables-pricing-import', $url . '/js/pricingimport.js', ['jquery', 'tables-core'], $version, true);
  }

  public function addSettingsAssets()
  {
    $url = untrailingslashit(plugin_dir_url(__FILE__)) . '/assets';
    wp_enqueue_style('supsystic-tables-pricing-import-card', $url . '/css/settings-card.css', [], $this->getEnvironment()->getConfig()->get('plugin_version') . '-pti-1');
  }

  /**
   * @return SupsysticTables_Pricingimport_Model_Migrator
   */
  public function getMigrator()
  {
    return $this->getEnvironment()->getModule('core')->getModelsFactory()->get('migrator', 'pricingimport');
  }

  /**
   * @return bool Also the templates of Pricing Table, for tests of the converter on every design.
   */
  public function withTemplates()
  {
    return (bool) apply_filters('supsystic_tables_pricing_import_templates', false);
  }

  /**
   * The coupon for people of Pricing Table, offered while Data Tables PRO is not active:
   * PTPRO50 for the owners of Pricing Table PRO, PTFREE20 for everybody else.
   *
   * @return array|null code and discount in percent.
   */
  public function getCoupon()
  {
    if ($this->getEnvironment()->isPro()) {
      return null;
    }

    return $this->hasSourcePro()
      ? ['code' => self::COUPON_SOURCE_PRO, 'discount' => 50]
      : ['code' => self::COUPON, 'discount' => 20];
  }

  /**
   * Pricing Table PRO is activated and its license is active. Installed but not active, or active without
   * a license, does not count. Read from the database, without the code of Pricing Table: an active license
   * turns on the PRO module "tablepro" in the table of modules of Pricing Table.
   */
  public function hasSourcePro()
  {
    $file = self::SOURCE_PRO_FILE;
    $found = in_array($file, (array) get_option('active_plugins', []), true);
    if (!$found && is_multisite()) {
      $network = (array) get_site_option('active_sitewide_plugins', []);
      $found = isset($network[$file]);
    }
    if ($found) {
      $found = $this->hasSourceLicense();
    }

    return (bool) apply_filters('supsystic_tables_pricing_import_source_pro', $found);
  }

  private function hasSourceLicense()
  {
    global $wpdb;
    $table = $wpdb->prefix . 'pts_modules';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) !== $table) {
      return false;
    }

    return (int) $wpdb->get_var($wpdb->prepare("SELECT active FROM `{$table}` WHERE code = %s LIMIT 1", 'tablepro')) === 1;
  }

  /**
   * With a coupon: Data Tables PRO goes right into the cart on supsystic.com, together with the coupon.
   */
  public function getProUrl()
  {
    $coupon = $this->getCoupon();
    if (!$coupon) {
      return $this->getEnvironment()->getModule('core')->buildProUrl(['utm_medium' => 'pricing_table_import']);
    }

    return add_query_arg([
      'mod' => 'user',
      'addQty' => 1,
      'goto' => 'checkout',
      'lang' => 'en',
      'action' => 'addToCart',
      'pid' => self::PRO_PID,
      'coupon' => rawurlencode($coupon['code']),
      'utm_source' => 'plugin',
      'utm_medium' => 'pricing_table_import',
      'utm_campaign' => strtolower($coupon['code']),
    ], 'https://supsystic.com/');
  }

  public function renderSettingsCard()
  {
    if (!current_user_can('manage_options')) {
      return;
    }
    try {
      $status = $this->getMigrator()->getStatus();
    } catch (Throwable $e) {
      return;
    }
    if (!$status['total']) {
      return;
    }
    $this->getEnvironment()->getTwig()->display('@pricingimport/settingsCard.twig', [
      'status' => $status,
      'url' => $this->getEnvironment()->generateUrl('pricingimport'),
    ]);
  }
}
