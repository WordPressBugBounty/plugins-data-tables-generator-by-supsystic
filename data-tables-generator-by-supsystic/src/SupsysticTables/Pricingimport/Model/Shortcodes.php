<?php

/**
 * Finds the shortcodes of Pricing Table on the site and, when asked, puts the shortcodes of the moved tables
 * in their place. Every replacement is written down, so it can be undone exactly; the old tables are never touched.
 *
 * Places: the content of posts and pages, the data of Elementor and text widgets.
 */
class SupsysticTables_Pricingimport_Model_Shortcodes extends SupsysticTables_Core_BaseModel
{
  const LOG = 'supsystic_tbl_pricing_import_shortcodes';
  const NEEDLE = '[supsystic-price-table';
  const WIDGET_OPTIONS = ['widget_text', 'widget_block', 'widget_custom_html'];

  private function getLog()
  {
    $log = get_option(self::LOG, []);

    return is_array($log) ? $log : [];
  }

  private function saveLog(array $log)
  {
    if ($log) {
      update_option(self::LOG, $log, false);
    } else {
      delete_option(self::LOG);
    }
  }

  /**
   * @return array Each shortcode in the text: the shortcode, the id of the old table.
   */
  private function parse($text)
  {
    $found = [];
    if (!is_string($text) || stripos($text, self::NEEDLE) === false) {
      return $found;
    }
    if (preg_match_all('/\[supsystic-price-table\b[^\]]*\]/i', $text, $matches)) {
      foreach ($matches[0] as $shortcode) {
        $id = preg_match('/\bid\s*=\s*\\\\?["\']?(\d+)/i', $shortcode, $idMatch) ? (int) $idMatch[1] : 0;
        $found[] = ['shortcode' => $shortcode, 'source' => $id];
      }
    }

    return $found;
  }

  private function newShortcode($id)
  {
    return '[' . $this->environment->getConfig()->get('shortcode_name') . ' id=' . (int) $id . ']';
  }

  /**
   * @param array $map Id of an old table => id of our table.
   * @return array The places with old shortcodes, and the places changed before (they can be undone).
   */
  public function find(array $map)
  {
    $places = [];
    $like = '%' . $this->db->esc_like(self::NEEDLE) . '%';
    $posts = $this->db->get_results($this->db->prepare(
      "SELECT ID, post_title, post_type, post_status, post_content FROM {$this->db->posts} WHERE post_type NOT IN ('revision', 'nav_menu_item') AND post_status NOT IN ('trash', 'auto-draft', 'inherit') AND post_content LIKE %s ORDER BY post_type, ID LIMIT 500",
      $like
    ));
    foreach ((array) $posts as $post) {
      $this->addPlace($places, 'post:' . $post->ID, $this->describePost($post), $this->parse($post->post_content), $map);
    }
    $elementor = $this->db->get_results($this->db->prepare(
      "SELECT m.post_id, m.meta_value, p.post_title, p.post_type, p.post_status FROM {$this->db->postmeta} m JOIN {$this->db->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_elementor_data' AND m.meta_value LIKE %s AND p.post_status NOT IN ('trash', 'auto-draft', 'inherit') LIMIT 500",
      $like
    ));
    foreach ((array) $elementor as $row) {
      $post = (object) ['ID' => $row->post_id, 'post_title' => $row->post_title, 'post_type' => $row->post_type, 'post_status' => $row->post_status];
      $place = $this->describePost($post);
      $place['where'] = 'Elementor';
      $this->addPlace($places, 'elementor:' . $row->post_id, $place, $this->parse($row->meta_value), $map);
    }
    foreach (self::WIDGET_OPTIONS as $option) {
      $value = get_option($option);
      $text = is_array($value) ? wp_json_encode($value) : (string) $value;
      if (stripos($text, self::NEEDLE) === false) {
        continue;
      }
      $this->addPlace($places, 'option:' . $option, [
        'title' => $this->environment->translate('Widgets'),
        'type' => $this->environment->translate('Widget'),
        'where' => $option === 'widget_block' ? 'Block widgets' : 'Text widgets',
        'edit' => admin_url('widgets.php'),
        'view' => '',
        'status' => '',
      ], $this->parse(stripslashes($text)), $map);
    }

    $replaced = [];
    foreach ($this->getLog() as $key => $entry) {
      $replaced[] = array_merge(['key' => $key], $this->describeKey($key), [
        'count' => count($entry['pairs']),
        'time' => (int) $entry['time'],
        'date' => function_exists('wp_date') ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) $entry['time']) : date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $entry['time']),
      ]);
    }

    return ['places' => array_values($places), 'replaced' => $replaced];
  }

  private function addPlace(array &$places, $key, array $place, array $found, array $map)
  {
    if (!$found) {
      return;
    }
    $items = [];
    $ready = 0;
    foreach ($found as $item) {
      $target = isset($map[$item['source']]) ? (int) $map[$item['source']] : 0;
      $items[] = [
        'shortcode' => $item['shortcode'],
        'source' => $item['source'],
        'target' => $target,
        'replacement' => $target ? $this->newShortcode($target) : '',
      ];
      if ($target) {
        $ready++;
      }
    }
    $places[$key] = array_merge(['key' => $key], $place, ['items' => $items, 'ready' => $ready]);
  }

  private function describePost($post)
  {
    $type = get_post_type_object($post->post_type);
    $title = trim((string) $post->post_title);

    return [
      'title' => $title !== '' ? $title : sprintf($this->environment->translate('(no title) #%d'), $post->ID),
      'type' => $type ? $type->labels->singular_name : $post->post_type,
      'where' => '',
      'edit' => (string) get_edit_post_link($post->ID, 'raw'),
      'view' => $post->post_status === 'publish' || $post->post_status === 'private' ? (string) get_permalink($post->ID) : '',
      'status' => $post->post_status === 'publish' ? '' : $post->post_status,
    ];
  }

  private function describeKey($key)
  {
    list($kind, $id) = array_pad(explode(':', $key, 2), 2, '');
    if ($kind === 'option') {
      return ['title' => $this->environment->translate('Widgets'), 'type' => $this->environment->translate('Widget'), 'where' => '', 'edit' => admin_url('widgets.php'), 'view' => '', 'status' => ''];
    }
    $post = get_post((int) $id);
    if (!$post) {
      return ['title' => sprintf('#%d', (int) $id), 'type' => '', 'where' => '', 'edit' => '', 'view' => '', 'status' => ''];
    }
    $place = $this->describePost($post);
    if ($kind === 'elementor') {
      $place['where'] = 'Elementor';
    }

    return $place;
  }

  /**
   * Puts the new shortcodes in the given places. Shortcodes of tables that were not moved stay as they are.
   * @param string[] $keys
   * @return array Per place: key, changed (number of shortcodes).
   */
  public function replace(array $keys, array $map)
  {
    $log = $this->getLog();
    $result = [];
    foreach (array_unique(array_map('strval', $keys)) as $key) {
      $pairs = [];
      $done = $this->transform($key, function ($text) use ($map, &$pairs) {
        foreach ($this->parse($text) as $item) {
          if (empty($map[$item['source']])) {
            continue;
          }
          $from = $item['shortcode'];
          $count = substr_count($text, $from);
          if (!$count) {
            continue;
          }
          $to = $this->newShortcode($map[$item['source']]);
          $text = str_replace($from, $to, $text);
          $pairs[] = ['from' => $from, 'to' => $to, 'count' => $count];
        }

        return $text;
      });
      if (!$done || !$pairs) {
        $result[] = ['key' => $key, 'changed' => 0];
        continue;
      }
      if (!isset($log[$key])) {
        $log[$key] = ['pairs' => [], 'time' => time()];
      }
      $log[$key]['pairs'] = array_merge($log[$key]['pairs'], $pairs);
      $log[$key]['time'] = time();
      $result[] = ['key' => $key, 'changed' => array_sum(array_column($pairs, 'count'))];
    }
    $this->saveLog($log);

    return $result;
  }

  /**
   * Puts the old shortcodes back where they were replaced. A place edited since keeps its other changes.
   * @param string[]|null $keys Null for all places.
   * @return array Per place: key, restored (number of shortcodes).
   */
  public function restore($keys = null)
  {
    $log = $this->getLog();
    $keys = $keys === null ? array_keys($log) : array_values(array_intersect(array_map('strval', (array) $keys), array_keys($log)));
    $result = [];
    foreach ($keys as $key) {
      $restored = 0;
      $pairs = array_reverse($log[$key]['pairs']);
      // Only as many as were put in, also when the place is split into several strings
      $left = array_map(function ($pair) {
        return (int) $pair['count'];
      }, $pairs);
      $this->transform($key, function ($text) use ($pairs, &$left, &$restored) {
        foreach ($pairs as $index => $pair) {
          if ($left[$index] <= 0) {
            continue;
          }
          $replacement = addcslashes($pair['from'], '\\$');
          $text = preg_replace('/' . preg_quote($pair['to'], '/') . '/', $replacement, $text, $left[$index], $done);
          $left[$index] -= $done;
          $restored += $done;
        }

        return $text;
      });
      unset($log[$key]);
      $result[] = ['key' => $key, 'restored' => $restored];
    }
    $this->saveLog($log);

    return $result;
  }

  /**
   * Changes the stored text of a place with the callback. Options are changed string by string, so serialized
   * data stays valid. Writes without the filters of saving a post, so nothing else in it changes.
   * @return bool Whether the place exists and was written.
   */
  private function transform($key, callable $callback)
  {
    list($kind, $id) = array_pad(explode(':', (string) $key, 2), 2, '');
    if ($kind === 'post') {
      $text = $this->db->get_var($this->db->prepare("SELECT post_content FROM {$this->db->posts} WHERE ID = %d", (int) $id));
      if ($text === null) {
        return false;
      }
      $new = $callback((string) $text);
      if ($new !== $text) {
        $this->db->update($this->db->posts, ['post_content' => $new], ['ID' => (int) $id]);
        clean_post_cache((int) $id);
      }

      return true;
    }
    if ($kind === 'elementor') {
      $text = $this->db->get_var($this->db->prepare("SELECT meta_value FROM {$this->db->postmeta} WHERE post_id = %d AND meta_key = '_elementor_data' LIMIT 1", (int) $id));
      if ($text === null) {
        return false;
      }
      $new = $callback((string) $text);
      if ($new !== $text) {
        $this->db->update($this->db->postmeta, ['meta_value' => $new], ['post_id' => (int) $id, 'meta_key' => '_elementor_data']);
        // Elementor keeps the output of shortcode widgets for a while
        delete_post_meta((int) $id, '_elementor_element_cache');
        wp_cache_delete((int) $id, 'post_meta');
      }

      return true;
    }
    if ($kind === 'option' && in_array($id, self::WIDGET_OPTIONS, true)) {
      $value = get_option($id);
      if (!is_array($value)) {
        return false;
      }
      array_walk_recursive($value, function (&$item) use ($callback) {
        if (is_string($item) && stripos($item, '[supsystic-') !== false) {
          $item = $callback($item);
        }
      });
      update_option($id, $value);

      return true;
    }

    return false;
  }
}
