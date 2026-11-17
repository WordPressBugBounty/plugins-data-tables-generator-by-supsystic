<?php

/**
 * Turns a table of Pricing Table by Supsystic into a pricing table of Data Tables.
 *
 * Only the content moves: plans with their names, prices and periods, feature rows with their icons,
 * pictures and links, buttons, badges, the featured plan and the description column. The table is built
 * from the same blocks as a table of our wizard and gets the standard theme of Data Tables; the markup
 * and the styles of the old plugin are not used. The pricing switcher and the loading by a header
 * (PRO features of Pricing Table) become the switcher and the server-side loading of Data Tables PRO.
 */
class SupsysticTables_Pricingimport_Model_Converter extends SupsysticTables_Core_BaseModel
{
  const TEMPLATE = 'classic';
  /** Inline tags kept in texts; everything else gives its text only. */
  const INLINE_TAGS = ['strong', 'b', 'em', 'i', 'u', 'del', 's', 'sup', 'sub', 'small', 'br', 'a'];
  const BLOCK_TAGS = ['p', 'div', 'li', 'ul', 'ol', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'tr', 'section', 'article', 'header', 'footer'];

  /**
   * @param array $table Loaded by the source model.
   * @param array $rendered html and css with the options of the table filled in.
   * @param array $capabilities Of the active Pricing module of Data Tables.
   * @return array builder (data for the builder model), report (items), assets (links to pictures of the old plugin), stats.
   */
  public function convert(array $table, array $rendered, array $capabilities, SupsysticTables_Pricingimport_Model_Source $source)
  {
    $param = function ($key, $default = '') use ($table, $source) {
      return $source->getParam($table, $key, $default);
    };
    $report = new SupsysticTables_Pricingimport_Model_Report($this->environment);
    $withDescription = (string) $param('enb_desc_col', '0') === '1';

    $toggle = $this->readToggle($table, $rendered['html'], $source);
    $variants = [];
    if ($toggle) {
      foreach ($toggle['options'] as $option) {
        $data = $this->readTable($this->applyToggleOption($rendered['html'], $option['source']), $withDescription);
        $variants[$option['key']] = $this->buildHtml($data);
      }
      $data = $this->readTable($this->applyToggleOption($rendered['html'], $toggle['defaultSource']), $withDescription);
    } else {
      $data = $this->readTable($rendered['html'], $withDescription);
    }
    $html = $this->buildHtml($data);
    if ($toggle && isset($variants[$toggle['default']])) {
      $html = $variants[$toggle['default']];
    }
    $ajax = $param('ajax') === 'toggle_by_header';
    $stats = $this->getStats($data);

    $builder = [
      'template' => self::TEMPLATE,
      'html' => $html,
      'css' => '',
      'fontFamily' => '',
      'fonts' => [],
      'maxWidth' => 100,
      'maxWidthUnit' => '%',
      'toggle' => (bool) $toggle,
      'toggleText' => $toggle ? $toggle['text'] : '',
      'toggleStyle' => $toggle ? $toggle['style'] : 'rounded',
      'toggleDefault' => $toggle ? $toggle['default'] : 'monthly',
      'toggleOptions' => $toggle ? array_map(function ($option) {
        return ['key' => $option['key'], 'label' => $option['label']];
      }, $toggle['options']) : [],
      'toggleVariants' => $variants,
      'toggleVariantStyles' => [],
      'ajaxToggle' => $ajax,
      'ajaxToggleText' => $table['label'] !== '' ? $table['label'] : 'Show pricing table',
      'descriptionColumn' => $data['description'] !== null,
      'showHeader' => true,
      'showDescription' => true,
      'showFooter' => true,
      'hoverAnimation' => (string) $param('enb_hover_animation') === '1',
    ];

    // What happens to each part of the table, for the summary of the move
    $report->moved('Plans', $stats['plans']);
    $report->moved('Prices');
    if ($stats['rows']) {
      $report->moved('Feature rows', $stats['rows']);
    }
    if ($stats['buttons']) {
      $report->moved('Buttons and links', $stats['buttons']);
    }
    if ($stats['badges']) {
      $report->moved('Badges', $stats['badges']);
    }
    if ($stats['featured']) {
      $report->moved('Featured plan');
    }
    if ($stats['icons']) {
      $report->moved('Icons', $stats['icons']);
    }
    if ($stats['images']) {
      $report->moved('Pictures', $stats['images']);
    }
    if ($stats['tooltips']) {
      $report->moved('Tooltips', $stats['tooltips']);
    }
    if ($data['description'] !== null) {
      $report->moved('Description column');
    }
    if ($builder['hoverAnimation']) {
      $report->moved('Hover effect');
    }
    $report->replaced('Design: the standard theme of Data Tables', '', 'Choose a theme in the table editor.');
    if ($param('is_horisontal_row_type') === '1') {
      $report->replaced('Plans as rows: shown as plan cards', '', 'Like every pricing table of Data Tables.');
    }
    if ($toggle) {
      if (!empty($capabilities['toggle'])) {
        $report->moved('Pricing switcher', count($toggle['options']));
      } else {
        $report->pro('Pricing switcher', sprintf($this->environment->translate('The table shows the option "%s".'), $toggle['defaultLabel']));
      }
    }
    if ($ajax) {
      if (!empty($capabilities['ajaxToggle'])) {
        $report->moved('Loading the table by a click on its header');
      } else {
        $report->pro('Loading the table by a click on its header', $this->environment->translate('The table is shown right away.'));
      }
    }
    if ($stats['scheduled']) {
      if (!empty($capabilities['schedule'])) {
        $report->moved('Column schedule', $stats['scheduled']);
      } else {
        $report->pro('Column schedule', $this->environment->translate('The columns are always shown.'));
      }
    }
    if (!empty($rendered['problems'])) {
      $report->skipped('Part of the template code', $this->environment->translate('The table was moved with its text, without that code.'));
    }

    return [
      'builder' => $builder,
      'report' => $report->getItems(),
      'assets' => $this->findAssetLinks($builder, $source),
      'stats' => $stats,
    ];
  }

  /* ---- Reading the old table ---- */

  /**
   * @return array description (null or header, labels), plans (name, note, price, period, images, features, buttons, badge, featured, schedule).
   */
  public function readTable($html, $withDescription)
  {
    $doc = $this->loadHtml($html);
    $root = $doc->getElementById('stp-import-root');
    $xpath = new DOMXPath($doc);
    $wrapper = $this->byClass($xpath, 'ptsColsWrapper', $root);
    $wrapper = $wrapper ? $wrapper[0] : $root;
    $description = null;
    $plans = [];
    foreach ($this->byClass($xpath, 'ptsCol', $wrapper) as $column) {
      if ($this->hasClass($column, 'ptsTableDescCol')) {
        if ($withDescription && $description === null) {
          $description = $this->readDescription($xpath, $column);
        }
        continue;
      }
      $plans[] = $this->readPlan($xpath, $column);
    }
    // Rows that every plan gave to its price or footer leave the description column too; extra empty labels go
    if ($description !== null && $plans) {
      $removed = null;
      foreach ($plans as $plan) {
        $removed = $removed === null ? $plan['removedRows'] : array_intersect($removed, $plan['removedRows']);
      }
      foreach ((array) $removed as $index) {
        unset($description['labels'][$index]);
      }
      $labels = array_values($description['labels']);
      $rows = max(array_map(function ($plan) {
        return count($plan['features']);
      }, $plans));
      while (count($labels) > $rows && ($index = array_search('', $labels, true)) !== false) {
        array_splice($labels, $index, 1);
      }
      $description['labels'] = $labels;
    }
    // A badge marks the featured plan, unless every plan has one (then they are numbers or labels)
    $badges = array_filter(array_column($plans, 'badge'));
    if ($badges && count($badges) < count($plans)) {
      foreach ($plans as $index => $plan) {
        if ($plan['badge']) {
          $plans[$index]['featured'] = true;
          break;
        }
      }
    }

    return ['description' => $description, 'plans' => $plans];
  }

  private function readDescription(DOMXPath $xpath, DOMElement $column)
  {
    $header = $this->first($xpath, 'ptsColHeader', $column);
    $lines = $header ? $this->lines($header) : [];
    $labels = [];
    foreach ($this->byClass($xpath, 'ptsCell', $column) as $cell) {
      $labels[] = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags(implode(' ', $this->lines($cell))), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    return ['title' => $lines ? wp_strip_all_tags($lines[0]) : '', 'labels' => $labels];
  }

  private function readPlan(DOMXPath $xpath, DOMElement $column)
  {
    $plan = ['name' => '', 'note' => '', 'price' => '', 'period' => '', 'images' => [], 'features' => [], 'buttons' => [], 'badge' => '', 'badgePosition' => '', 'featured' => false, 'from' => '', 'until' => ''];

    // Buttons outside the rows go to the footer, in their order
    foreach ($this->byClass($xpath, 'ptsActBtn', $column) as $button) {
      if ($this->closestClass($button, 'ptsCell', $column)) {
        continue;
      }
      $link = $button->tagName === 'a' ? $button : $xpath->query('.//a', $button)->item(0);
      if ($link) {
        $plan['buttons'][] = $this->readLink($link);
      }
      $button->parentNode->removeChild($button);
    }

    // Badge
    $badgeText = '';
    $badge = $this->first($xpath, 'ptsColBadge', $column);
    if ($badge) {
      $content = $this->first($xpath, 'ptsColBadgeContent', $badge);
      $badgeText = trim(preg_replace('/\s+/u', ' ', ($content ?: $badge)->textContent));
      foreach (['left-top' => 'left', 'left' => 'left', 'right-top' => 'right', 'right' => 'right', 'top' => 'center'] as $position => $align) {
        if ($this->hasClass($badge, 'ptsColBadge-' . $position)) {
          $plan['badgePosition'] = $align;
          break;
        }
      }
      $badge->parentNode->removeChild($badge);
    }
    if ($badgeText === '' && $column->getAttribute('data-enb-badge') === '1') {
      $badgeText = trim($column->getAttribute('data-badge-badge_name'));
    }
    if ($plan['badgePosition'] === '' && $column->getAttribute('data-badge-badge_pos') !== '') {
      $position = $column->getAttribute('data-badge-badge_pos');
      $plan['badgePosition'] = strpos($position, 'left') === 0 ? 'left' : (strpos($position, 'right') === 0 ? 'right' : 'center');
    }
    $plan['badge'] = $badgeText;

    // Schedule (PRO of Pricing Table)
    if ($column->getAttribute('data-enb-schedule') === '1') {
      $plan['from'] = $this->toDate($column->getAttribute('data-schedule-from'));
      $plan['until'] = $this->toDate($column->getAttribute('data-schedule-to'));
    }

    // Header: name and the lines under it; pictures stay on top of the plan
    $header = $this->first($xpath, 'ptsColHeader', $column);
    $headerLines = [];
    if ($header) {
      $plan['images'] = $this->takeImages($xpath, $header);
      $headerLines = $this->lines($header);
    }
    $plan['name'] = $headerLines ? $this->plainText(array_shift($headerLines)) : '';
    $desc = $this->first($xpath, 'ptsColDesc', $column);
    $descLines = [];
    if ($desc) {
      $plan['images'] = array_merge($plan['images'], $this->takeImages($xpath, $desc));
      $descLines = $this->lines($desc);
    }

    $features = [];
    foreach ($this->byClass($xpath, 'ptsCell', $column) as $cell) {
      $features[] = $this->readFeature($xpath, $cell);
    }
    $plan['removedRows'] = [];
    // A row with a button only: the button goes to the footer
    foreach ($features as $index => $feature) {
      if (!empty($feature['link']) && $feature['html'] === $feature['linkHtml'] && !$feature['images'] && !$feature['icons']) {
        $plan['buttons'][] = $feature['link'];
        $plan['removedRows'][] = $index;
      }
    }

    // The price: a line with a currency in the header or the price area, then a line with a number there,
    // then one of the first rows with a currency (some templates keep the price in a row)
    $currency = '/[$€£¥₽₴₹]|\b(?:USD|EUR|GBP|RUB|UAH)\b/u';
    $candidates = [];
    foreach ($headerLines as $index => $line) {
      $candidates[] = ['header', $index, $line];
    }
    foreach ($descLines as $index => $line) {
      $candidates[] = ['desc', $index, $line];
    }
    $pick = null;
    foreach ($candidates as $candidate) {
      if (preg_match($currency, $this->plainText($candidate[2]))) {
        $pick = $candidate;
        break;
      }
    }
    if (!$pick) {
      foreach (array_slice($features, 0, 3, true) as $index => $feature) {
        if (!in_array($index, $plan['removedRows'], true) && preg_match($currency, $this->plainText($feature['html']))) {
          $pick = ['row', $index, str_replace('<br>', ' ', $feature['html'])];
          $plan['removedRows'][] = $index;
          break;
        }
      }
    }
    if (!$pick) {
      foreach ($candidates as $candidate) {
        if ($candidate[0] === 'desc' && preg_match('/\d/u', $this->plainText($candidate[2]))) {
          $pick = $candidate;
          break;
        }
      }
    }
    if ($pick) {
      if ($pick[0] === 'header') {
        unset($headerLines[$pick[1]]);
      } elseif ($pick[0] === 'desc') {
        unset($descLines[$pick[1]]);
        // A short line right after the price is its period
        if (isset($descLines[$pick[1] + 1]) && mb_strlen($this->plainText($descLines[$pick[1] + 1])) <= 40) {
          $plan['period'] = $this->plainText($descLines[$pick[1] + 1]);
          unset($descLines[$pick[1] + 1]);
        }
      }
      $price = $pick[2];
      // Only sup and sub matter in a price; "($149/mo)" loses its brackets
      if (!preg_match('/<su[pb]\b/i', $price)) {
        $plain = $this->plainText($price);
        if (preg_match('/^\((.+)\)$/u', $plain, $inner)) {
          $plain = trim($inner[1]);
        }
        $price = esc_html($plain);
      }
      // "$29/month" and "29$ one time fee": the amount is the price, the rest is the period
      if (strpos($price, '<') === false && preg_match('/^\s*([^\d\s]{0,3}\s?\d[\d.,]*(?:\s?[$€£¥₽₴₹])?)(?=[\s\/])\s*(.+)$/u', $this->plainText($price), $parts) && $plan['period'] === '') {
        $price = esc_html($parts[1]);
        $plan['period'] = $parts[2];
      }
      // "<sup>$</sup>29/one-time": the tail after the slash is the period
      if ($plan['period'] === '' && preg_match('~^(.*\d(?:</su[pb]>)?)\s*(/[^<]+)$~u', $price, $parts)) {
        $price = $parts[1];
        $plan['period'] = trim($parts[2]);
      }
      $plan['price'] = $price;
    }
    $notes = array_merge(array_map([$this, 'plainText'], array_values($headerLines)), array_map([$this, 'plainText'], array_values($descLines)));
    if ($plan['name'] === '' && $notes) {
      $plan['name'] = array_shift($notes);
    }
    $plan['note'] = implode(' ', array_filter($notes));

    // Some templates repeat the name of the plan in the first row
    if ($features && $plan['name'] !== '') {
      $first = strtolower(preg_replace('/\s+/u', '', $this->plainText($features[0]['html'])));
      $name = strtolower(preg_replace('/\s+/u', '', $plan['name']));
      if ($first !== '' && strpos($first, $name) === 0) {
        $plan['removedRows'][] = 0;
      }
    }
    foreach ($features as $index => $feature) {
      if (!in_array($index, $plan['removedRows'], true)) {
        $plan['features'][] = $feature;
      }
    }

    $footer = $this->first($xpath, 'ptsColFooter', $column);
    if ($footer && !$plan['buttons']) {
      // A footer with a plain link instead of a button
      foreach ($xpath->query('.//a', $footer) as $link) {
        $plan['buttons'][] = $this->readLink($link);
      }
    }

    return $plan;
  }

  private function readFeature(DOMXPath $xpath, DOMElement $cell)
  {
    $feature = ['icons' => [], 'images' => [], 'html' => '', 'title' => trim($cell->getAttribute('title'))];
    foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " fa ")]', $cell) as $icon) {
      $name = $this->iconName($icon->getAttribute('class'));
      if ($name !== '') {
        $feature['icons'][] = $name;
      }
    }
    foreach ($this->byClass($xpath, 'ptsActBtn', $cell) as $button) {
      $link = $xpath->query('.//a', $button)->item(0);
      if ($link) {
        $data = $this->readLink($link);
        $feature['link'] = $data;
      }
      $button->parentNode->removeChild($button);
    }
    $feature['images'] = $this->takeImages($xpath, $cell);
    $feature['html'] = implode('<br>', $this->lines($cell));
    $feature['linkHtml'] = null;
    if (!empty($feature['link'])) {
      $text = $feature['link']['text'] !== '' ? esc_html($feature['link']['text']) : esc_html($feature['link']['href']);
      $feature['linkHtml'] = '<a href="' . esc_url($feature['link']['href']) . '"' . ($feature['link']['target'] ? ' target="_blank" rel="noopener"' : '') . '>' . $text . '</a>';
      $feature['html'] .= ($feature['html'] !== '' ? ' ' : '') . $feature['linkHtml'];
    }

    return $feature;
  }

  /**
   * A link of the content of a table. Links to supsystic.com came with the templates of Pricing Table:
   * tables of our users never link back to us, so they become "#", as Pricing Table does on update.
   */
  private function contentHref($href)
  {
    $href = trim((string) $href);
    if ($href === '') {
      return '#';
    }
    $host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
    if ($host === '' && strpos($href, '//') === 0) {
      $host = strtolower((string) wp_parse_url('https:' . $href, PHP_URL_HOST));
    }

    return $host === 'supsystic.com' || substr($host, -14) === '.supsystic.com' ? '#' : $href;
  }

  private function readLink(DOMElement $link)
  {
    $icons = [];
    foreach ($link->getElementsByTagName('i') as $icon) {
      $name = $this->iconName($icon->getAttribute('class'));
      if ($name !== '') {
        $icons[] = $name;
      }
    }

    return [
      'href' => $this->contentHref($link->getAttribute('href')),
      'target' => $link->getAttribute('target') === '_blank' ? '_blank' : '',
      'text' => $this->plainText($this->inlineHtml($link)),
      'icons' => $icons,
    ];
  }

  /**
   * @return string[] Pictures inside a node, taken out of it: src, alt and the link around them.
   */
  private function takeImages(DOMXPath $xpath, DOMElement $node)
  {
    $images = [];
    foreach (iterator_to_array($xpath->query('.//img', $node)) as $image) {
      $src = trim($image->getAttribute('src'));
      if ($src === '' || strpos($src, 'data:') === 0) {
        $image->parentNode->removeChild($image);
        continue;
      }
      $link = $image->parentNode && $image->parentNode->nodeName === 'a' ? $this->contentHref($image->parentNode->getAttribute('href')) : '';
      $images[] = ['src' => $src, 'alt' => trim($image->getAttribute('alt')), 'href' => $link];
      $image->parentNode->removeChild($image);
    }

    return $images;
  }

  /**
   * The text of a node by lines: blocks and line breaks start new lines. Only simple inline tags stay.
   * @return string[] HTML of each line that is not empty.
   */
  private function lines(DOMNode $node)
  {
    $lines = [''];
    $walk = function (DOMNode $node) use (&$walk, &$lines) {
      foreach ($node->childNodes as $child) {
        if ($child->nodeType === XML_TEXT_NODE) {
          $lines[count($lines) - 1] .= esc_html(preg_replace('/\s+/u', ' ', $child->nodeValue));
          continue;
        }
        if ($child->nodeType !== XML_ELEMENT_NODE) {
          continue;
        }
        $tag = strtolower($child->nodeName);
        if ($tag === 'br') {
          $lines[] = '';
          continue;
        }
        if (in_array($tag, ['script', 'style', 'select', 'option', 'img'], true) || $this->iconName($child->getAttribute('class')) !== '') {
          continue;
        }
        if (in_array($tag, self::BLOCK_TAGS, true)) {
          $lines[] = '';
          $walk($child);
          $lines[] = '';
          continue;
        }
        $lines[count($lines) - 1] .= $this->inlineHtml($child);
      }
    };
    $walk($node);
    $clean = [];
    foreach ($lines as $line) {
      $line = trim(preg_replace('/\s+/u', ' ', $line));
      if (trim(html_entity_decode(wp_strip_all_tags($line), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0") !== '') {
        $clean[] = $line;
      }
    }

    return $clean;
  }

  /**
   * An element as simple inline HTML: strong, em, del, sup and links keep their tag, others give their text.
   */
  private function inlineHtml(DOMNode $node)
  {
    if ($node->nodeType === XML_TEXT_NODE) {
      return esc_html(preg_replace('/\s+/u', ' ', $node->nodeValue));
    }
    if ($node->nodeType !== XML_ELEMENT_NODE) {
      return '';
    }
    $tag = strtolower($node->nodeName);
    if (in_array($tag, ['script', 'style', 'img', 'select', 'option'], true) || $this->iconName($node->getAttribute('class')) !== '') {
      return '';
    }
    if ($tag === 'br') {
      return '<br>';
    }
    $inner = '';
    foreach ($node->childNodes as $child) {
      $inner .= $this->inlineHtml($child);
    }
    if (!in_array($tag, self::INLINE_TAGS, true)) {
      return $inner;
    }
    if ($tag === 'b') {
      $tag = 'strong';
    }
    if ($tag === 'i') {
      $tag = 'em';
    }
    if ($tag === 'a') {
      $href = $this->contentHref($node->getAttribute('href'));
      if ($href === '#') {
        return $inner;
      }

      return '<a href="' . esc_url($href) . '"' . ($node->getAttribute('target') === '_blank' ? ' target="_blank" rel="noopener"' : '') . '>' . $inner . '</a>';
    }

    return trim(wp_strip_all_tags($inner)) === '' ? $inner : '<' . $tag . '>' . $inner . '</' . $tag . '>';
  }

  private function plainText($html)
  {
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
  }

  /**
   * @return string The Font Awesome name of an icon (fa-check), without the size classes.
   */
  private function iconName($classes)
  {
    $list = preg_split('/\s+/', trim((string) $classes));
    if (!in_array('fa', $list, true)) {
      return '';
    }
    foreach ($list as $class) {
      if (strpos($class, 'fa-') === 0 && !preg_match('/^fa-(?:\dx|lg|fw|spin|pulse|border|pull-left|pull-right|inverse|stack(?:-\dx)?|rotate-\d+|flip-\w+)$/', $class)) {
        return sanitize_html_class($class);
      }
    }

    return '';
  }

  /* ---- Building our table ---- */

  /**
   * The table as our builder makes it: the same blocks as the wizard and the editor.
   */
  public function buildHtml(array $data)
  {
    $html = '<section class="st-pricing-grid st-pricing-' . self::TEMPLATE . '">';
    if ($data['description'] !== null) {
      $title = $data['description']['title'] !== '' ? $data['description']['title'] : $this->environment->translate('Features');
      $html .= '<article class="st-pricing-card st-pricing-description-card"><header class="st-pricing-header"><h3>' . esc_html($title) . '</h3></header><div class="st-pricing-price"></div><ul class="st-pricing-features">';
      foreach ($data['description']['labels'] as $label) {
        $html .= '<li>' . esc_html($label) . '</li>';
      }
      $html .= '</ul><footer class="st-pricing-footer"></footer></article>';
    }
    foreach ($data['plans'] as $plan) {
      $attributes = '';
      if ($plan['from']) {
        $attributes .= ' data-visible-from="' . esc_attr($plan['from']) . '"';
      }
      if ($plan['until']) {
        $attributes .= ' data-visible-until="' . esc_attr($plan['until']) . '"';
      }
      $html .= '<article class="st-pricing-card' . ($plan['featured'] ? ' st-pricing-featured' : '') . '"' . $attributes . '>';
      if ($plan['badge'] !== '') {
        $align = $plan['badgePosition'] && $plan['badgePosition'] !== 'right' ? ' data-horizontal-align="' . esc_attr($plan['badgePosition']) . '" data-vertical-align="top"' : '';
        $html .= '<span class="st-pricing-badge"' . $align . '>' . esc_html($plan['badge']) . '</span>';
      }
      $html .= '<header class="st-pricing-header">';
      foreach ($plan['images'] as $image) {
        $html .= $this->imageHtml($image);
      }
      $html .= '<h3>' . esc_html($plan['name']) . '</h3>';
      if ($plan['note'] !== '') {
        $html .= '<p>' . esc_html($plan['note']) . '</p>';
      }
      $html .= '</header>';
      $html .= $plan['price'] === '' ? '<div class="st-pricing-price"></div>' : '<div class="st-pricing-price"><strong>' . $plan['price'] . '</strong>' . ($plan['period'] !== '' ? '<small>' . esc_html($plan['period']) . '</small>' : '') . '</div>';
      $html .= '<ul class="st-pricing-features">';
      foreach ($plan['features'] as $feature) {
        $html .= '<li class="st-pricing-feature"' . ($feature['title'] !== '' ? ' title="' . esc_attr($feature['title']) . '"' : '') . '><span class="st-pricing-feature-content">';
        foreach ($feature['icons'] as $icon) {
          $html .= '<span class="st-pricing-icon fa ' . esc_attr($icon) . '" data-icon-scale="1" aria-hidden="true"></span>';
        }
        foreach ($feature['images'] as $image) {
          $html .= $this->imageHtml($image);
        }
        $html .= '<span class="st-pricing-feature-text">' . $feature['html'] . '</span></span></li>';
      }
      $html .= '</ul><footer class="st-pricing-footer">';
      foreach ($plan['buttons'] as $button) {
        $icons = '';
        foreach ($button['icons'] as $icon) {
          $icons .= ' <i class="fa ' . esc_attr($icon) . '" aria-hidden="true"></i>';
        }
        $text = $button['text'] !== '' ? esc_html($button['text']) : esc_html($this->environment->translate('Choose plan'));
        $html .= '<a class="st-pricing-button" href="' . esc_url($button['href']) . '"' . ($button['target'] ? ' target="_blank" rel="noopener"' : '') . '>' . $text . $icons . '</a>';
      }
      $html .= '</footer></article>';
    }

    return $html . '</section>';
  }

  private function imageHtml(array $image)
  {
    $html = '<img src="' . esc_url($image['src']) . '" alt="' . esc_attr($image['alt']) . '">';
    if ($image['href'] !== '' && $image['href'] !== '#') {
      $html = '<a href="' . esc_url($image['href']) . '">' . $html . '</a>';
    }

    return $html;
  }

  private function getStats(array $data)
  {
    $stats = ['plans' => count($data['plans']), 'rows' => 0, 'buttons' => 0, 'badges' => 0, 'featured' => 0, 'icons' => 0, 'images' => 0, 'tooltips' => 0, 'scheduled' => 0];
    foreach ($data['plans'] as $plan) {
      $stats['rows'] = max($stats['rows'], count($plan['features']));
      $stats['buttons'] += count($plan['buttons']);
      $stats['badges'] += $plan['badge'] !== '' ? 1 : 0;
      $stats['featured'] += $plan['featured'] ? 1 : 0;
      $stats['images'] += count($plan['images']);
      $stats['scheduled'] += $plan['from'] || $plan['until'] ? 1 : 0;
      foreach ($plan['features'] as $feature) {
        $stats['icons'] += count($feature['icons']);
        $stats['images'] += count($feature['images']);
        $stats['tooltips'] += $feature['title'] !== '' ? 1 : 0;
        $stats['buttons'] += empty($feature['link']) ? 0 : 1;
      }
    }

    return $stats;
  }

  /* ---- The switcher (PRO of Pricing Table) ---- */

  /**
   * @return array|null options (key, source, label), default, defaultSource, defaultLabel, style, text.
   */
  private function readToggle(array $table, $html, SupsysticTables_Pricingimport_Model_Source $source)
  {
    if ((string) $source->getParam($table, 'enable_switch_toggle') !== '1') {
      return null;
    }
    $encoded = $source->getParam($table, 'option_name_input');
    $decoded = $encoded !== '' ? json_decode((string) base64_decode($encoded, true), true) : null;
    $raw = is_array($decoded) && !empty($decoded['options']) && is_array($decoded['options']) ? $decoded['options'] : [];
    $options = [];
    foreach ($raw as $index => $option) {
      if (is_array($option)) {
        $key = isset($option['key']) ? (string) $option['key'] : (string) $index;
        $label = isset($option['name']) ? (string) $option['name'] : $key;
      } else {
        // The old format: a list of names, and the cells are bound to their numbers
        $key = (string) $index;
        $label = (string) $option;
      }
      $clean = sanitize_key($key);
      if ($clean === '' || $label === '') {
        continue;
      }
      $options[] = ['key' => $clean, 'source' => $key, 'label' => sanitize_text_field($label)];
    }
    $options = array_slice($options, 0, 8);
    if (count($options) < 2) {
      return null;
    }
    $doc = $this->loadHtml($html);
    $xpath = new DOMXPath($doc);
    $switch = $this->byClass($xpath, 'ptsSwitchWrapper', $doc->getElementById('stp-import-root'));
    $switch = $switch ? $switch[0] : null;
    $selected = isset($decoded['selected_options']) ? (string) $decoded['selected_options'] : '';
    if ($switch && $switch->getAttribute('data-selected-key') !== '') {
      $selected = $switch->getAttribute('data-selected-key');
    }
    $default = $options[0];
    foreach ($options as $option) {
      if ($option['source'] === $selected || $option['label'] === $selected) {
        $default = $option;
      }
    }
    $type = $switch ? $switch->getAttribute('data-switch-type') : $source->getParam($table, 'switch_type');
    $style = 'rounded';
    if (in_array($type, ['dropdown', 'select'], true)) {
      $style = 'select';
    } elseif (in_array($type, ['radio', 'radiobutton', 'radio_button'], true)) {
      $style = 'checkbox';
    }

    return [
      'options' => $options,
      'default' => $default['key'],
      'defaultSource' => $default['source'],
      'defaultLabel' => $default['label'],
      'style' => $style,
      'text' => sanitize_text_field($source->getParam($table, 'switch_text')),
    ];
  }

  /**
   * The markup of the old table with the cells of one option of the switcher, as the script of Pricing Table PRO makes it.
   */
  private function applyToggleOption($html, $sourceKey)
  {
    $doc = $this->loadHtml($html);
    $root = $doc->getElementById('stp-import-root');
    $xpath = new DOMXPath($doc);
    foreach ($this->byClass($xpath, 'ptsToggle', $root) as $toggle) {
      foreach ($this->byClass($xpath, 'ptsTog', $toggle) as $tog) {
        $data = $this->decodeToggleData($tog->getAttribute('data-toggle-' . $sourceKey));
        if (!$data) {
          continue;
        }
        foreach ($this->byClass($xpath, 'ptsEl', $tog) as $element) {
          $this->fillToggleElement($element, $data, $doc);
        }
      }
    }

    return $this->innerHtml($root);
  }

  private function decodeToggleData($value)
  {
    $value = trim((string) $value);
    if ($value === '') {
      return null;
    }
    $decoded = base64_decode($value, true);
    $data = $decoded !== false ? json_decode($decoded, true) : null;
    if (!is_array($data)) {
      $data = json_decode($value, true);
    }

    return is_array($data) ? $data : null;
  }

  private function fillToggleElement(DOMElement $element, array $data, DOMDocument $doc)
  {
    $type = isset($data['d_type']) ? (string) $data['d_type'] : (isset($data['type']) ? (string) $data['type'] : 'txt');
    while ($element->firstChild) {
      $element->removeChild($element->firstChild);
    }
    switch ($type) {
      case 'btn':
        $element->setAttribute('class', 'ptsEl ptsElInput ptsActBtn');
        $link = $doc->createElement('a');
        $link->setAttribute('href', isset($data['href']) ? (string) $data['href'] : '#');
        $this->appendHtml($link, isset($data['text']) ? (string) $data['text'] : '', $doc);
        $element->appendChild($link);
        break;
      case 'table_cell_img':
      case 'img':
        $element->setAttribute('class', 'ptsEl ptsElImg');
        $image = $doc->createElement('img');
        $image->setAttribute('src', isset($data['src']) ? (string) $data['src'] : '');
        $image->setAttribute('alt', '');
        if (!empty($data['link']['href'])) {
          $link = $doc->createElement('a');
          $link->setAttribute('href', (string) $data['link']['href']);
          $link->appendChild($image);
          $element->appendChild($link);
        } else {
          $element->appendChild($image);
        }
        break;
      default:
        $element->setAttribute('class', $type === 'icon' || $type === 'table_cell_icon' ? 'ptsIcon ptsEl' : 'ptsEl');
        $this->appendHtml($element, isset($data['html']) ? (string) $data['html'] : '', $doc);
        break;
    }
  }

  /* ---- Helpers ---- */

  private function loadHtml($html)
  {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?><div id="stp-import-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $doc;
  }

  private function innerHtml(DOMNode $node)
  {
    $html = '';
    foreach ($node->childNodes as $child) {
      $html .= $node->ownerDocument->saveHTML($child);
    }

    return trim($html);
  }

  private function appendHtml(DOMElement $parent, $html, DOMDocument $doc)
  {
    if ($html === '') {
      return;
    }
    $fragment = $this->loadHtml($html);
    foreach ($fragment->getElementById('stp-import-root')->childNodes as $child) {
      $parent->appendChild($doc->importNode($child, true));
    }
  }

  /**
   * @return DOMElement[]
   */
  private function byClass(DOMXPath $xpath, $class, DOMNode $context = null)
  {
    $list = [];
    foreach ($xpath->query(".//*[contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')]", $context) as $node) {
      $list[] = $node;
    }

    return $list;
  }

  private function first(DOMXPath $xpath, $class, DOMNode $context)
  {
    $list = $this->byClass($xpath, $class, $context);

    return $list ? $list[0] : null;
  }

  private function hasClass(DOMElement $node, $class)
  {
    return in_array($class, preg_split('/\s+/', trim($node->getAttribute('class'))), true);
  }

  private function closestClass(DOMNode $node, $class, DOMNode $stop)
  {
    for ($parent = $node->parentNode; $parent && $parent !== $stop; $parent = $parent->parentNode) {
      if ($parent instanceof DOMElement && $this->hasClass($parent, $class)) {
        return $parent;
      }
    }

    return null;
  }

  private function toDate($value)
  {
    $value = trim((string) $value);
    if ($value === '') {
      return '';
    }
    $time = strtotime($value);

    return $time ? gmdate('Y-m-d', $time) : '';
  }

  /**
   * @return string[] Links to pictures inside the folder of Pricing Table: they are copied, so they stay after it is removed.
   */
  private function findAssetLinks(array $builder, SupsysticTables_Pricingimport_Model_Source $source)
  {
    $base = $source->getAssetsUrl();
    $text = $builder['html'] . implode('', (array) $builder['toggleVariants']);
    $pattern = '~' . preg_quote($base, '~') . '[A-Za-z0-9_\-./]+\.(?:png|jpe?g|gif|svg|webp)~i';

    return preg_match_all($pattern, $text, $matches) ? array_values(array_unique($matches[0])) : [];
  }
}
