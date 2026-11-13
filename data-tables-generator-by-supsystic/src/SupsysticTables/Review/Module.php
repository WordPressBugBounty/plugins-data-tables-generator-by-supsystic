<?php

/**
 * Asks for a review on WordPress.org once the plugin has done something useful for the site: a table went live on a
 * published page, several tables were built, PRO reported its own moment (an import, a Google Sheets sync, a visitor's
 * edit) or, as a fallback, two weeks of use. The card is shown inside the plugin's own pages only, to the users allowed
 * to use the plugin, and it is not shown again once it is answered.
 *
 * PRO (or any add-on) reports its moments with do_action('supsystic_tables_review_moment', $type, ['table' => $id]).
 */
class SupsysticTables_Review_Module extends SupsysticTables_Core_BaseModule
{
  const REVIEW_URL = 'https://wordpress.org/support/plugin/data-tables-generator-by-supsystic/reviews/#new-post';
  const SUPPORT_URL = 'https://supsystic.com/contact-us/';

  // No card in the first days after install, whatever happens
  const MIN_DAYS = 3;
  // Without any moment the card comes after two weeks of use
  const FALLBACK_DAYS = 14;
  const TABLES_MILESTONE = 3;
  const SNOOZE_DAYS = 30;
  const MAX_SNOOZES = 3;

  /**
   * Moments in the order the card picks them: the first one recorded wins.
   * @var string[]
   */
  private $momentTypes = ['live', 'google_sync', 'frontend_edit', 'import'];

  /** @var array|null */
  private $state;

  /** @var array|null|false False until the card is built for the current request */
  private $card = false;

  /**
   * {@inheritdoc}
   */
  public function onInit()
  {
    parent::onInit();

    add_filter('do_shortcode_tag', [$this, 'onShortcodeOutput'], 10, 3);
    add_action('wp_after_insert_post', [$this, 'onPostSaved'], 10, 2);
    add_action('supsystic_tables_review_moment', [$this, 'recordMoment'], 10, 2);

    if (is_admin()) {
      add_filter('plugin_row_meta', [$this, 'addPluginRowLink'], 10, 2);

      if ($this->getEnvironment()->isPluginPage()) {
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        // The plugin layout dispatches "notices" at the top of its content area
        $this->getEnvironment()->getDispatcher()->on('notices', [$this, 'printCard']);
      }
    }
  }

  /**
   * A table is rendered on a published page that a visitor (or the site owner) opens: the table is live.
   * Covers the shortcode, the block, the Elementor widget, the classic widget and the PHP code, as they all render the shortcode.
   * @param string|false $output
   * @param string $tag
   * @param array|string $attr
   * @return string|false
   */
  public function onShortcodeOutput($output, $tag, $attr)
  {
    $config = $this->getEnvironment()->getConfig();
    if ($tag !== $config->get('shortcode_name') && $tag !== $config->get('shortcode_part_name')) {
      return $output;
    }
    if (!is_string($output) || !is_array($attr) || empty($attr['id']) || $this->hasMoment('live')) {
      return $output;
    }
    if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || is_preview() || is_feed() || is_customize_preview() || isset($_GET['elementor-preview'])) {
      return $output;
    }
    if (!is_singular() || !preg_match('/supsystic-table|st-pricing-table/', $output)) {
      return $output;
    }
    $post = get_queried_object();
    if ($post instanceof WP_Post && $post->post_status === 'publish' && !post_password_required($post)) {
      $this->addMoment('live', ['table' => (int) $attr['id'], 'post' => (int) $post->ID]);
    }

    return $output;
  }

  /**
   * A post with a table block or shortcode is published: the table is live even before anyone opens the page.
   * @param int $postId
   * @param WP_Post $post
   */
  public function onPostSaved($postId, $post)
  {
    if (!$post instanceof WP_Post || $post->post_status !== 'publish' || $this->hasMoment('live')) {
      return;
    }
    if (wp_is_post_revision($postId) || wp_is_post_autosave($postId) || !is_post_type_viewable($post->post_type)) {
      return;
    }
    $tableId = $this->findTableIn((string) $post->post_content);
    if ($tableId) {
      $this->addMoment('live', ['table' => $tableId, 'post' => (int) $postId]);
    }
  }

  /**
   * Handler of the "supsystic_tables_review_moment" action.
   * @param string $type One of the moment types (google_sync, frontend_edit, import)
   * @param array $data ['table' => id]
   */
  public function recordMoment($type, $data = [])
  {
    if (!is_string($type) || $type === 'live' || !in_array($type, $this->momentTypes, true) || $this->hasMoment($type)) {
      return;
    }
    $this->addMoment($type, ['table' => is_array($data) && isset($data['table']) ? (int) $data['table'] : 0]);
  }

  /**
   * The card for the current user on the current page, or null when there is nothing to show.
   * @return array|null
   */
  public function getCard()
  {
    if ($this->card !== false) {
      return $this->card;
    }
    $this->card = null;

    $environment = $this->getEnvironment();
    $onCardPage = $environment->isModule('overview') || $environment->isModule('tables') || $environment->isModule('settings');
    if (!$onCardPage || !apply_filters('supsystic_tables_review_request', true)) {
      return null;
    }
    $userId = get_current_user_id();
    if (!$userId || !$this->getController()->_currentUserCanUsePlugin()) {
      return null;
    }
    $answer = $this->getAnswer($userId);
    if ($answer['status'] === 'rated' || $answer['status'] === 'dismissed') {
      return null;
    }
    if ($answer['status'] === 'snoozed' && $answer['until'] > time()) {
      return null;
    }
    if ($this->isAiNoticeWaiting()) {
      return null;
    }
    $state = $this->getState();
    if (time() - $state['since'] < self::MIN_DAYS * DAY_IN_SECONDS) {
      return null;
    }
    $moment = $this->pickMoment($state);
    if (!$moment) {
      return null;
    }

    $this->card = array_merge($moment, [
      'reviewUrl' => self::REVIEW_URL,
      'supportUrl' => self::SUPPORT_URL,
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('dtgs_nonce'),
    ]);

    return $this->card;
  }

  public function enqueueAssets()
  {
    if (!$this->getCard()) {
      return;
    }
    $url = $this->getLocationUrl() . '/assets';
    $version = $this->getEnvironment()->getConfig()->get('plugin_version') . '-review-3';

    wp_enqueue_style('supsystic-tables-review', $url . '/css/review.css', [], $version);
    wp_enqueue_script('supsystic-tables-review', $url . '/js/review.js', [], $version, true);
  }

  public function printCard()
  {
    $card = $this->getCard();
    if (!$card) {
      return;
    }
    // Every value is escaped by the template
    echo $this->getTwig()->render('@review/card.twig', ['card' => $card]);
  }

  /**
   * Saves the answer of a user. "rate" and "done" end the requests; "later", "support" and "close" put them off for a
   * month, and the third time (or the first time on a site that answered the old review notice) for good.
   * @param int $userId
   * @param string $answer
   * @return bool
   */
  public function saveAnswer($userId, $answer)
  {
    $userId = (int) $userId;
    if (!$userId || !is_string($answer)) {
      return false;
    }
    $now = time();

    if ($answer === 'rate' || $answer === 'done') {
      update_user_meta($userId, $this->key(), ['status' => 'rated', 'answer' => $answer, 'time' => $now]);
      return true;
    }
    if ($answer === 'later' || $answer === 'support' || $answer === 'close') {
      $snoozes = $this->getAnswer($userId)['snoozes'] + 1;
      $state = $this->getState();
      $final = $snoozes >= self::MAX_SNOOZES || $state['legacy'];
      update_user_meta($userId, $this->key(), [
        'status' => $final ? 'dismissed' : 'snoozed',
        'answer' => $answer,
        'until' => $now + self::SNOOZE_DAYS * DAY_IN_SECONDS,
        'snoozes' => $snoozes,
        'time' => $now,
      ]);
      return true;
    }

    return false;
  }

  /**
   * "Rate" link in the plugin's row on the Plugins page.
   * @param array $links
   * @param string $file
   * @return array
   */
  public function addPluginRowLink($links, $file)
  {
    if (!is_array($links) || $file !== plugin_basename($this->getEnvironment()->getPluginPath() . '/index.php')) {
      return $links;
    }
    $links[] = sprintf(
      '<a href="%s" target="_blank" rel="noopener noreferrer" aria-label="%s">%s <span style="color:#ffb900;letter-spacing:1px;" aria-hidden="true">&#9733;&#9733;&#9733;&#9733;&#9733;</span></a>',
      esc_url(self::REVIEW_URL),
      esc_attr($this->getEnvironment()->translate('Rate Data Tables on WordPress.org')),
      esc_html($this->getEnvironment()->translate('Rate us'))
    );

    return $links;
  }

  /**
   * Site state: when the plugin started to be used, the recorded moments and whether the old review notice was answered.
   * @return array
   */
  public function getState()
  {
    if ($this->state !== null) {
      return $this->state;
    }
    $state = get_option($this->key());
    if (!is_array($state) || !isset($state['since']) || !is_int($state['since'])) {
      $state = $this->createState();
      update_option($this->key(), $state, true);
    }
    $state['legacy'] = !empty($state['legacy']);
    $state['moments'] = isset($state['moments']) && is_array($state['moments']) ? $state['moments'] : [];
    $this->state = $state;

    return $this->state;
  }

  /**
   * @param int $userId
   * @return array ['status' => ''|'snoozed'|'rated'|'dismissed', 'until' => int, 'snoozes' => int]
   */
  public function getAnswer($userId)
  {
    $answer = get_user_meta((int) $userId, $this->key(), true);
    $answer = is_array($answer) ? $answer : [];

    return [
      'status' => isset($answer['status']) && is_string($answer['status']) ? $answer['status'] : '',
      'until' => isset($answer['until']) ? (int) $answer['until'] : 0,
      'snoozes' => isset($answer['snoozes']) ? max(0, (int) $answer['snoozes']) : 0,
    ];
  }

  /**
   * The first state of a site. Existing sites keep their history: the start is the first table (or the date the old
   * review notice was planned from), and an answered old notice is remembered.
   * @return array
   */
  private function createState()
  {
    global $wpdb;

    $now = time();
    $since = $now;
    $legacy = false;

    $old = get_option($this->getEnvironment()->getConfig()->get('db_prefix') . 'reviewNotice');
    if (is_array($old)) {
      if (isset($old['shown']) && $old['shown'] === true) {
        $legacy = true;
      }
      if (!empty($old['time']) && is_numeric($old['time'])) {
        // The old notice was planned for a week after the first visit
        $since = min($since, (int) $old['time'] - 7 * DAY_IN_SECONDS);
      }
    }

    $suppress = $wpdb->suppress_errors(true);
    $firstTable = $wpdb->get_var("SELECT MIN(created_at) FROM {$wpdb->prefix}supsystic_tbl_tables");
    $wpdb->suppress_errors($suppress);
    if ($firstTable && ($firstTime = strtotime($firstTable . ' UTC')) && $firstTime > 0) {
      $since = min($since, $firstTime);
    }

    return ['since' => (int) $since, 'legacy' => $legacy, 'moments' => []];
  }

  /**
   * @param array $state
   * @return array|null
   */
  private function pickMoment(array $state)
  {
    $environment = $this->getEnvironment();

    foreach ($this->momentTypes as $type) {
      if (empty($state['moments'][$type]) || !is_array($state['moments'][$type])) {
        continue;
      }
      $moment = $state['moments'][$type];
      $tableId = isset($moment['table']) ? (int) $moment['table'] : 0;
      $table = $this->getTableTitle($tableId);
      if ($table === null) {
        // The table was deleted since then
        continue;
      }
      $table = trim($table) !== '' ? trim($table) : '#' . $tableId;

      if ($type === 'live') {
        $post = isset($moment['post']) ? get_post((int) $moment['post']) : null;
        if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
          continue;
        }
        $postTitle = get_the_title($post);

        return [
          'type' => 'live',
          'eyebrow' => $environment->translate('Table published'),
          'title' => $postTitle !== '' ? sprintf($environment->translate('“%1$s” is live on “%2$s”'), $table, $postTitle) : sprintf($environment->translate('“%s” is live on your site'), $table),
          'text' => $environment->translate('Visitors can now search, sort and filter it.'),
          'link' => get_permalink($post),
          'linkText' => $environment->translate('View page'),
        ];
      }

      $texts = [
        'google_sync' => [
          $environment->translate('Google Sheets sync'),
          $environment->translate('“%s” stays in sync with Google Sheets'),
          $environment->translate('Changes in your sheet now reach the table on their own.'),
        ],
        'frontend_edit' => [
          $environment->translate('Front-end editing'),
          $environment->translate('Visitors are editing “%s” on your site'),
          $environment->translate('Their changes are saved straight to the table.'),
        ],
        'import' => [
          $environment->translate('Import complete'),
          $environment->translate('Your data is in “%s”'),
          $environment->translate('The table is ready to go live with its shortcode or block.'),
        ],
      ];

      return [
        'type' => $type,
        'eyebrow' => $texts[$type][0],
        'title' => sprintf($texts[$type][1], $table),
        'text' => $texts[$type][2],
      ];
    }

    // A site that answered the old notice is asked once more only after a real success
    if ($state['legacy']) {
      return null;
    }

    $tables = $this->countTables();
    if ($tables >= self::TABLES_MILESTONE) {
      return [
        'type' => 'tables',
        'eyebrow' => $environment->translate('Milestone'),
        'title' => sprintf($environment->translate('You have built %d tables with Data Tables'), $tables),
        'text' => $environment->translate('Nice work. Your site is getting organized.'),
      ];
    }

    $days = (int) floor((time() - $state['since']) / DAY_IN_SECONDS);
    if ($tables >= 1 && $days >= self::FALLBACK_DAYS) {
      $weeks = (int) floor($days / 7);

      return [
        'type' => 'time',
        'eyebrow' => $environment->translate('Thank you for using Data Tables'),
        'title' => $weeks < 9 ? sprintf($environment->translate('Data Tables has been working on your site for %d weeks'), $weeks) : $environment->translate('Data Tables has been working on your site for a while now'),
        'text' => $environment->translate('We hope it saves you time every day.'),
      ];
    }

    return null;
  }

  /**
   * Lets the card wait while the AI notice is up, but not for longer than a week.
   * @return bool
   */
  private function isAiNoticeWaiting()
  {
    $notice = get_option($this->getEnvironment()->getConfig()->get('db_prefix') . 'aiNotice');
    if (!is_array($notice) || !isset($notice['shown'], $notice['time']) || $notice['shown'] !== false) {
      return false;
    }
    $dueSince = (int) $notice['time'];

    return time() > $dueSince && time() < $dueSince + 7 * DAY_IN_SECONDS;
  }

  /**
   * @param string $type
   * @return bool
   */
  private function hasMoment($type)
  {
    $state = $this->getState();

    return !empty($state['moments'][$type]);
  }

  /**
   * @param string $type
   * @param array $data
   */
  private function addMoment($type, array $data)
  {
    $state = $this->getState();
    $state['moments'][$type] = array_merge($data, ['time' => time()]);
    $this->state = $state;
    update_option($this->key(), $state, true);
  }

  /**
   * The first table in a block or in a table shortcode of the content.
   * @param string $content
   * @return int
   */
  private function findTableIn($content)
  {
    if ($content === '' || strpos($content, 'supsystic-tables') === false) {
      return 0;
    }
    if (preg_match('#<!--\s+wp:' . preg_quote(SupsysticTables_Gutenberg_Module::BLOCK_NAME, '#') . '\s+(\{.*?\})\s+/?-->#', $content, $match)) {
      $attributes = json_decode($match[1], true);
      if (is_array($attributes) && !empty($attributes['tableId']) && (int) $attributes['tableId'] > 0) {
        return (int) $attributes['tableId'];
      }
    }
    $config = $this->getEnvironment()->getConfig();
    if (preg_match_all('/' . get_shortcode_regex([$config->get('shortcode_name'), $config->get('shortcode_part_name')]) . '/', $content, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $shortcode) {
        $attributes = shortcode_parse_atts($shortcode[3]);
        if (is_array($attributes) && !empty($attributes['id']) && (int) $attributes['id'] > 0) {
          return (int) $attributes['id'];
        }
      }
    }

    return 0;
  }

  /**
   * @param int $id
   * @return string|null Null when there is no such table
   */
  private function getTableTitle($id)
  {
    global $wpdb;

    if ($id < 1) {
      return null;
    }
    $title = $wpdb->get_var($wpdb->prepare("SELECT title FROM {$wpdb->prefix}supsystic_tbl_tables WHERE id = %d", $id));

    return $title === null ? null : (string) $title;
  }

  /**
   * @return int
   */
  private function countTables()
  {
    global $wpdb;

    return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}supsystic_tbl_tables");
  }

  /**
   * Name of the site option and of the user meta.
   * @return string
   */
  private function key()
  {
    return $this->getEnvironment()->getConfig()->get('db_prefix') . 'review';
  }
}
