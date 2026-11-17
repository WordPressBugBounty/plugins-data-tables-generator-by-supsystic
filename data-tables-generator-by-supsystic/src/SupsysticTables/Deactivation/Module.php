<?php

/**
 * Asks why Data Tables (or Data Tables PRO) is deactivated: registers the plugin with the deactivation dialog shared by
 * the Supsystic plugins (deactivation-v1/deactivation.php in this folder says what is sent and when).
 */
class SupsysticTables_Deactivation_Module extends SupsysticTables_Core_BaseModule
{
  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    if (!is_admin()) {
      return;
    }
    $environment = $this->getEnvironment();
    require_once __DIR__ . '/deactivation-v1/deactivation.php';

    Supsystic_Deactivation_V1::register([
      'slug' => 'data-tables-generator-by-supsystic',
      'name' => 'Data Tables Generator',
      'file' => $environment->getPluginPath() . '/index.php',
      'tables' => ['supsystic_tbl_tables'],
      // The start the review request counts from: the first table, or the first visit
      'since' => function () use ($environment) {
        $review = $environment->getModule('review');
        $state = $review && method_exists($review, 'getState') ? $review->getState() : [];
        return isset($state['since']) ? (int) $state['since'] : 0;
      },
      'pro' => [
        'slug' => 'tables-generator-pro',
        'name' => 'Data Tables Generator PRO',
        'email' => function () use ($environment) {
          $license = $environment->getModule('license');
          return $license && method_exists($license, 'getHelper') ? (string) $license->getHelper()->getEmail($license->getOptions()) : '';
        },
      ],
    ]);
  }
}
