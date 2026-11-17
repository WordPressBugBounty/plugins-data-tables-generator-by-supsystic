<?php

/**
 * Reads the tables of Pricing Table by Supsystic straight from the database, so the move works also when
 * that plugin is switched off or removed. Nothing here writes to its data.
 *
 * A table of Pricing Table is a Twig template: its HTML and CSS are filled with the options of the table
 * when the table is shown. This model prepares a table the same way the plugin does, in a sandbox that
 * allows only what the templates of the plugin use.
 */
class SupsysticTables_Pricingimport_Model_Source extends SupsysticTables_Core_BaseModel
{
  const PLUGIN_SLUG = 'pricing-table-by-supsystic';
  const PRO_SLUG = 'price-table-supsystic-pro';
  const SHORTCODE = 'supsystic-price-table';
  /** The id the templates get for the block of the table; the converter replaces it. */
  const VIEW_ID = 'ptsBlockImport';

  /** @var Twig_SupTwgDtgs_Environment|null */
  private $twig;

  public function getName()
  {
    return 'Pricing Table by Supsystic';
  }

  public function getTableName()
  {
    return $this->db->prefix . 'pts_tables';
  }

  public function exists()
  {
    $name = $this->getTableName();

    return $this->db->get_var($this->db->prepare('SHOW TABLES LIKE %s', $this->db->esc_like($name))) === $name;
  }

  /**
   * The tables people made. Rows with original_id = 0 are the templates that come with the plugin.
   * @param bool $withTemplates Also the templates (for tests of the converter on every design).
   * @return array[] id, label, original_id, created, template.
   */
  public function getList($withTemplates = false)
  {
    if (!$this->exists()) {
      return [];
    }
    $where = $withTemplates ? '' : 'WHERE original_id <> 0';
    $rows = $this->db->get_results("SELECT id, label, original_id, date_created FROM {$this->getTableName()} {$where} ORDER BY id ASC", ARRAY_A);
    $list = [];
    foreach ((array) $rows as $row) {
      $id = (int) $row['id'];
      $list[$id] = [
        'id' => $id,
        'label' => (string) $row['label'],
        'original_id' => (int) $row['original_id'],
        'created' => (string) $row['date_created'],
        'template' => (int) $row['original_id'] === 0,
      ];
    }

    return $list;
  }

  /**
   * @return string The name of the template a table was made from, when it is known.
   */
  public function getTemplateLabel($originalId)
  {
    if (!$originalId || !$this->exists()) {
      return '';
    }

    return (string) $this->db->get_var($this->db->prepare("SELECT label FROM {$this->getTableName()} WHERE id = %d", (int) $originalId));
  }

  /**
   * @return array The table with its options decoded and its links resolved, as Pricing Table has them before rendering.
   */
  public function load($id)
  {
    if (!$this->exists()) {
      throw new RuntimeException($this->environment->translate('Pricing Table by Supsystic has no tables on this site.'));
    }
    $row = $this->db->get_row($this->db->prepare("SELECT * FROM {$this->getTableName()} WHERE id = %d", (int) $id), ARRAY_A);
    if (!$row) {
      throw new InvalidArgumentException($this->environment->translate('This table no longer exists.'));
    }
    // The same steps as the model of Pricing Table: the CSS is stored with slashes, the options are serialized and slashed
    $css = (string) $row['css'];
    if ($css !== '') {
      $css = stripcslashes($css);
    }
    $params = [];
    if (!empty($row['params'])) {
      $decoded = base64_decode((string) $row['params'], true);
      $params = $decoded !== false ? @unserialize($decoded, ['allowed_classes' => false]) : false;
      $params = is_array($params) ? $this->unslashParams($params) : [];
    }
    $table = [
      'id' => (int) $row['id'],
      'label' => (string) $row['label'],
      'original_id' => (int) $row['original_id'],
      'html' => $this->replaceLinks((string) $row['html']),
      'css' => htmlspecialchars_decode($this->replaceLinks($css)),
      'params' => $this->replaceLinks($params),
    ];
    $table['suspicious'] = $this->isSuspicious($row);

    return $table;
  }

  /**
   * @return string The value of an option of the table: options are saved as ['val' => value], a few as plain values.
   */
  public function getParam(array $table, $key, $default = '')
  {
    if (!isset($table['params'][$key])) {
      return $default;
    }
    $value = $table['params'][$key];
    if (is_array($value)) {
      return array_key_exists('val', $value) && is_scalar($value['val']) ? (string) $value['val'] : $default;
    }

    return is_scalar($value) ? (string) $value : $default;
  }

  private function unslashParams($params)
  {
    if (is_array($params)) {
      foreach ($params as $key => $value) {
        $params[$key] = $this->unslashParams($value);
      }

      return $params;
    }

    return is_string($params) ? stripslashes($params) : $params;
  }

  /**
   * Pricing Table stores its own links with placeholders, so a table keeps working after the site moves.
   */
  private function replaceLinks($data)
  {
    if (is_array($data)) {
      foreach ($data as $key => $value) {
        $data[$key] = $this->replaceLinks($value);
      }

      return $data;
    }
    if (!is_string($data) || strpos($data, '[PTS_') === false && strpos($data, 'supsystic.com/_assets/tables/') === false) {
      return $data;
    }
    $assets = $this->getAssetsUrl();

    return str_replace(
      ['[PTS_MOD_URL]', 'https://supsystic.com/_assets/tables/', '[PTS_ASSETS_URL]', '[PTS_SITE_URL]', '[PTS_OLD_ASSETS_URL]'],
      [$assets, $assets, $assets, get_bloginfo('wpurl') . '/', $assets],
      $data
    );
  }

  /**
   * @return string The folder with the pictures of the templates of Pricing Table, also when the plugin is off.
   */
  public function getAssetsUrl()
  {
    return trailingslashit(plugins_url(self::PLUGIN_SLUG . '/assets/_assets/tables'));
  }

  public function getAssetsDir()
  {
    return trailingslashit(WP_PLUGIN_DIR) . self::PLUGIN_SLUG . '/assets/_assets/tables/';
  }

  /**
   * Pricing Table shows nothing for a table with these scripts in it: such a table was most likely broken into.
   */
  private function isSuspicious(array $row)
  {
    foreach ($row as $value) {
      if (!is_string($value) || $value === '' || strpos($value, 'script') === false) {
        continue;
      }
      foreach (['getscript', 'pastebin', 'document.createElement', 'document.location.href', 'String.fromCharCode', 'window.location.replace', 'window.'] as $needle) {
        if (strpos($value, $needle) !== false) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Fills the templates of the table with its options, as Pricing Table does on a page.
   * @return array html, css and the problems met (strings).
   */
  public function render(array $table)
  {
    $html = htmlspecialchars_decode($table['html']);
    $css = $table['css'];
    $context = [
      'table' => [
        'id' => $table['id'],
        'label' => $table['label'],
        'original_id' => $table['original_id'],
        'view_id' => self::VIEW_ID,
        'params' => $table['params'],
        'session_id' => 0,
        'cat_code' => 'price_table',
      ],
      'isEditMode' => false,
    ];
    $problems = [];
    $result = [];
    foreach (['html' => $html, 'css' => $css] as $key => $template) {
      if (strpos($template, '{{') === false && strpos($template, '{%') === false) {
        $result[$key] = $template;
        continue;
      }
      try {
        $result[$key] = $this->getTwig()->createTemplate($template)->render($context);
      } catch (Throwable $e) {
        // A template the sandbox does not allow keeps its text without the template code
        $problems[] = $key;
        $result[$key] = preg_replace('/\{%.*?%\}|\{\{.*?\}\}|\{#.*?#\}/s', '', $template);
      }
    }
    $result['problems'] = $problems;

    return $result;
  }

  /**
   * @return Twig_SupTwgDtgs_Environment A sandboxed Twig: the templates are data from the database, not code we trust.
   */
  private function getTwig()
  {
    if ($this->twig) {
      return $this->twig;
    }
    $twig = new Twig_SupTwgDtgs_Environment(new Twig_SupTwgDtgs_Loader_Array([]), ['autoescape' => false, 'strict_variables' => false, 'cache' => false, 'debug' => false]);
    $twig->addFunction(new Twig_SupTwgDtgs_SimpleFunction('adjBs', [$this, 'adjustBrightness']));
    $policy = new Twig_SupTwgDtgs_Sandbox_SecurityPolicy(
      ['if', 'for', 'set'],
      ['escape', 'e', 'raw', 'default', 'length', 'lower', 'upper', 'trim', 'replace', 'round', 'abs', 'number_format', 'join', 'first', 'last', 'keys', 'striptags', 'nl2br', 'capitalize', 'title', 'format', 'split', 'slice', 'url_encode', 'json_encode', 'merge'],
      [],
      [],
      ['adjBs', 'range', 'max', 'min', 'cycle', 'random']
    );
    $twig->addExtension(new Twig_SupTwgDtgs_Extension_Sandbox($policy, true));
    $this->twig = $twig;

    return $twig;
  }

  /**
   * The colour helper of the templates: makes a colour lighter or darker by a number of steps.
   * The same arithmetic as in Pricing Table, so the colours stay exactly the same.
   */
  public function adjustBrightness($hex, $steps)
  {
    $hex = trim((string) $hex);
    $steps = max(-255, min(255, (int) $steps));
    $rgba = null;
    if (stripos($hex, 'rgb') !== false) {
      if (!preg_match_all('/[\d.]+/', $hex, $parts) || count($parts[0]) < 3) {
        return $hex;
      }
      $values = $parts[0];
      $rgba = isset($values[3]) ? $values[3] : null;
      $hex = sprintf('%02x%02x%02x', min(255, (int) $values[0]), min(255, (int) $values[1]), min(255, (int) $values[2]));
      $isRgb = true;
    } else {
      $isRgb = false;
    }
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) === 3) {
      $hex = str_repeat($hex[0], 2) . str_repeat($hex[1], 2) . str_repeat($hex[2], 2);
    }
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
      return '#' . $hex;
    }
    $channels = [];
    foreach (str_split($hex, 2) as $color) {
      $channels[] = max(0, min(255, hexdec($color) + $steps));
    }
    if ($isRgb) {
      if ($rgba !== null) {
        $channels[] = $rgba;
      }

      return ($rgba !== null ? 'rgba' : 'rgb') . '(' . implode(',', $channels) . ')';
    }

    return '#' . implode('', array_map(function ($channel) {
      return str_pad(dechex($channel), 2, '0', STR_PAD_LEFT);
    }, $channels));
  }
}
