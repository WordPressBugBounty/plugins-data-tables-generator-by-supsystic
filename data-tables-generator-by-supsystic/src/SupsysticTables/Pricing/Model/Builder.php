<?php

class SupsysticTables_Pricing_Model_Builder extends SupsysticTables_Core_BaseModel
{
  public function getBuilder($table)
  {
    $defaults = [
      'version' => 3,
      'template' => 'classic',
      'html' => $this->defaultHtml(),
      'css' => '',
      'fontFamily' => '',
      'fonts' => [],
      'maxWidth' => 100,
      'maxWidthUnit' => '%',
      'toggle' => false,
      'toggleText' => '',
      'toggleStyle' => 'rounded',
      'toggleDefault' => 'monthly',
      'toggleOptions' => [],
      'toggleVariants' => [],
      'toggleVariantStyles' => [],
      'ajaxToggle' => false,
      'ajaxToggleText' => 'Show pricing table',
      'descriptionColumn' => false,
      'showHeader' => true,
      'showDescription' => true,
      'showFooter' => true,
      'hoverAnimation' => false,
    ];
    $meta = is_array($table->meta) ? $table->meta : [];
    if (!empty($meta['pricingBuilder']) && is_array($meta['pricingBuilder'])) {
      $builder = array_merge($defaults, $meta['pricingBuilder']);
      $builder['html'] = $this->sanitizeWooRedirectAttributes($this->normalizeWooCartButtonText($this->restoreAttachmentImages($builder['html'])));
      $builder['css'] = $this->sanitizeCss($builder['css']);
      foreach ((array) $builder['toggleVariants'] as $key => $variantHtml) {
        $builder['toggleVariants'][$key] = $this->sanitizeWooRedirectAttributes($this->normalizeWooCartButtonText($this->restoreAttachmentImages($variantHtml)));
      }
      foreach ((array) $builder['toggleVariantStyles'] as $key => $variantCss) {
        $builder['toggleVariantStyles'][$key] = $this->sanitizeCss($variantCss);
      }
      return $builder;
    }
    return $defaults;
  }

  public function saveBuilder($table, array $data, array $capabilities, array $templates = [])
  {
    $html = isset($data['html']) ? (string) $data['html'] : '';
    $css = isset($data['css']) ? (string) $data['css'] : '';
    if (strlen($html) > 1000000 || strlen($css) > 200000) {
      throw new InvalidArgumentException('Pricing table is too large.');
    }
    $allowed = wp_kses_allowed_html('post');
    foreach (['div', 'section', 'article', 'header', 'footer', 'span', 'p', 'h2', 'h3', 'h4', 'ul', 'li', 'strong', 'small', 'del', 'a', 'button', 'img', 'i', 'video', 'source', 'iframe'] as $tag) {
      if (!isset($allowed[$tag])) {
        $allowed[$tag] = [];
      }
      $allowed[$tag]['class'] = true;
      $allowed[$tag]['data-price-monthly'] = true;
      $allowed[$tag]['data-price-yearly'] = true;
      $allowed[$tag]['data-period-monthly'] = true;
      $allowed[$tag]['data-period-yearly'] = true;
      $allowed[$tag]['data-pricing-role'] = true;
      $allowed[$tag]['data-st-feature-label'] = true;
      $allowed[$tag]['data-horizontal-align'] = true;
      $allowed[$tag]['data-vertical-align'] = true;
      $allowed[$tag]['data-st-products'] = true;
      $allowed[$tag]['data-st-coupon'] = true;
      $allowed[$tag]['data-st-button-text'] = true;
      $allowed[$tag]['data-st-redirect-url'] = true;
      $allowed[$tag]['data-st-redirect-target'] = true;
      $allowed[$tag]['aria-label'] = true;
      $allowed[$tag]['data-st-countdown-mode'] = true;
      $allowed[$tag]['data-st-countdown-duration'] = true;
      $allowed[$tag]['data-st-countdown-target'] = true;
      $allowed[$tag]['data-st-countdown-label'] = true;
      $allowed[$tag]['data-st-countdown-expired'] = true;
      $allowed[$tag]['data-st-countdown-unit'] = true;
      if ($tag === 'img') {
        $allowed[$tag]['data-attachment-id'] = true;
        $allowed[$tag]['data-attachment-size'] = true;
      }
      if ($tag === 'video' || $tag === 'iframe') { $allowed[$tag]['data-pause-out-of-view'] = true; }
      if (!empty($capabilities['schedule']) && $tag === 'article') {
        $allowed[$tag]['data-visible-from'] = true;
        $allowed[$tag]['data-visible-until'] = true;
      }
    }
    $allowed['i']['aria-hidden'] = true;
    $allowed['button']['type'] = true;
    foreach (['video', 'iframe'] as $tag) {
      foreach (['src', 'width', 'height', 'title', 'poster', 'controls', 'autoplay', 'loop', 'muted', 'playsinline', 'preload', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy'] as $attr) {
        $allowed[$tag][$attr] = true;
      }
    }
    $allowed['source']['src'] = true;
    $allowed['source']['type'] = true;
    $html = $this->sanitizeWooRedirectAttributes($this->normalizeWooCartButtonText($this->restoreAttachmentImages(wp_kses($html, $allowed))));
    if (!$capabilities['toggle']) {
      $html = preg_replace('/\sdata-(?:price|period)-(?:monthly|yearly)="[^"]*"/i', '', $html);
    }
    if (empty($capabilities['schedule'])) {
      $html = preg_replace('/\sdata-visible-(?:from|until)="[^"]*"/i', '', $html);
    } else {
      $html = preg_replace_callback('/\sdata-visible-(?:from|until)="([^"]*)"/i', function ($matches) {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $matches[1], $parts)) {
          return '';
        }
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $matches[0] : '';
      }, $html);
    }
    $css = $this->sanitizeCss($css);
    $meta = is_array($table->meta) ? $table->meta : [];
    $template = sanitize_key($data['template'] ?? 'classic');
    if ($templates && !isset($templates[$template])) {
      $template = 'classic';
    }
    $fontFamily = $this->sanitizeFont($data['fontFamily'] ?? '');
    $fonts = array_values(array_unique(array_filter(array_map([$this, 'sanitizeFont'], (array) ($data['fonts'] ?? [])))));
    if ($fontFamily && !in_array($fontFamily, $fonts, true)) {
      $fonts[] = $fontFamily;
    }
    $meta['pricingBuilder'] = [
      'version' => 3,
      'template' => $template,
      'html' => $html,
      'css' => $css,
      'fontFamily' => $fontFamily,
      'fonts' => array_slice($fonts, 0, 50),
      'maxWidth' => $this->sanitizeMaxWidth($data['maxWidth'] ?? 100, $data['maxWidthUnit'] ?? '%'),
      'maxWidthUnit' => ($data['maxWidthUnit'] ?? '%') === 'px' ? 'px' : '%',
      'toggle' => !empty($capabilities['toggle']) && !empty($data['toggle']),
      'toggleText' => sanitize_text_field($data['toggleText'] ?? ''),
      'toggleStyle' => in_array(($data['toggleStyle'] ?? ''), ['rounded', 'checkbox', 'select'], true) ? $data['toggleStyle'] : 'rounded',
      'toggleDefault' => sanitize_key($data['toggleDefault'] ?? 'monthly'),
      'toggleOptions' => !empty($capabilities['toggle']) ? $this->sanitizeToggleOptions($data['toggleOptions'] ?? []) : [],
      'toggleVariants' => !empty($capabilities['toggle']) ? $this->sanitizeToggleVariants($data['toggleVariants'] ?? [], $allowed) : [],
      'toggleVariantStyles' => !empty($capabilities['toggle']) ? $this->sanitizeToggleVariantStyles($data['toggleVariantStyles'] ?? []) : [],
      'ajaxToggle' => !empty($capabilities['ajaxToggle']) && !empty($data['ajaxToggle']),
      'ajaxToggleText' => sanitize_text_field($data['ajaxToggleText'] ?? 'Show pricing table'),
      'descriptionColumn' => !empty($data['descriptionColumn']),
      'showHeader' => !empty($data['showHeader']),
      'showDescription' => !empty($data['showDescription']),
      'showFooter' => !empty($data['showFooter']),
      'hoverAnimation' => !empty($data['hoverAnimation']),
    ];
    $this->environment->getModule('core')->getModelsFactory()->get('tables')->setMeta($table->id, $meta);
  }

  private function sanitizeFont($font)
  {
    $font = trim((string) $font);
    return $font !== '' && strlen($font) <= 80 && preg_match("/^[A-Za-z0-9 .'-]+$/", $font) ? $font : '';
  }

  private function sanitizeMaxWidth($value, $unit)
  {
    $value = absint($value);
    return $unit === 'px' ? min(5000, max(240, $value)) : min(100, max(10, $value));
  }

  private function sanitizeToggleOptions($options)
  {
    $clean = [];
    foreach (array_slice((array) $options, 0, 8) as $option) {
      if (!is_array($option)) { continue; }
      $key = sanitize_key($option['key'] ?? '');
      $label = sanitize_text_field($option['label'] ?? '');
      if (!$key || !$label) { continue; }
      $clean[] = ['key' => $key, 'label' => $label];
    }
    return $clean;
  }

  private function sanitizeToggleVariants($variants, array $allowed)
  {
    $clean = [];
    foreach (array_slice((array) $variants, 0, 8, true) as $key => $html) {
      $key = sanitize_key($key);
      if (!$key || !is_string($html) || strlen($html) > 1000000) { continue; }
      $clean[$key] = $this->sanitizeWooRedirectAttributes($this->normalizeWooCartButtonText($this->restoreAttachmentImages(wp_kses($html, $allowed))));
    }
    return $clean;
  }

  private function sanitizeToggleVariantStyles($styles)
  {
    $clean = [];
    foreach (array_slice((array) $styles, 0, 8, true) as $key => $css) {
      $key = sanitize_key($key);
      if (!$key || !is_string($css) || strlen($css) > 200000) { continue; }
      $clean[$key] = $this->sanitizeCss($css);
    }
    return $clean;
  }

  private function normalizeWooCartButtonText($html)
  {
    if (!is_string($html) || $html === '' || stripos($html, 'st-pricing-woo-cart-button') === false) {
      return $html;
    }
    return preg_replace_callback('/(<button\b[^>]*\bclass\s*=\s*(["\'])[^"\']*\bst-pricing-woo-cart-button\b[^"\']*\2[^>]*>)([\s\S]*?)(<\/button>)/i', function ($matches) {
      $openingTag = preg_replace_callback('/\bclass\s*=\s*(["\'])([^"\']*)\1/i', function ($classMatches) {
        $classes = preg_split('/\s+/', trim($classMatches[2]));
        if (!in_array('st-pricing-button', $classes, true)) { array_unshift($classes, 'st-pricing-button'); }
        return 'class=' . $classMatches[1] . implode(' ', array_filter($classes)) . $classMatches[1];
      }, $matches[1], 1);
      $text = '';
      if (preg_match('/\sdata-st-button-text\s*=\s*(["\'])(.*?)\1/i', $openingTag, $labelMatch)) {
        $text = trim(html_entity_decode($labelMatch[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      }
      if ($text === '') {
        $text = trim(html_entity_decode(wp_strip_all_tags($matches[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      }
      $length = strlen($text);
      for ($unitLength = 4; $unitLength <= (int) floor($length / 2); $unitLength++) {
        if ($length % $unitLength !== 0) { continue; }
        $unit = substr($text, 0, $unitLength);
        if (str_repeat($unit, (int) ($length / $unitLength)) === $text) {
          $text = trim($unit);
          break;
        }
      }
      $text = $text ?: 'Add package to cart';
      $isAtomic = preg_match('/\bclass\s*=\s*(["\'])([^"\']*)\1/i', $openingTag, $classMatch)
        && preg_match('/(?:^|\s)st-pricing-woo-cart(?:\s|$)/i', $classMatch[2]);
      if ($isAtomic) {
        $opening = substr($openingTag, 0, -1);
        $opening = preg_replace('/\sdata-st-button-text\s*=\s*(["\']).*?\1/i', '', $opening);
        $opening = preg_replace('/\saria-label\s*=\s*(["\']).*?\1/i', '', $opening);
        return $opening . ' data-st-button-text="' . esc_attr($text) . '" aria-label="' . esc_attr($text) . '"></button>';
      }
      return $openingTag . esc_html($text) . $matches[4];
    }, $html);
  }

  private function sanitizeWooRedirectAttributes($html)
  {
    if (!is_string($html) || $html === '' || stripos($html, 'data-st-redirect-') === false) { return $html; }
    $html = preg_replace_callback('/\sdata-st-redirect-url\s*=\s*(["\'])(.*?)\1/i', function ($matches) {
      $url = trim(html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
      if ($url === '') { return ' data-st-redirect-url=""'; }
      $url = esc_url_raw($url, ['http', 'https']);
      return $url ? ' data-st-redirect-url="' . esc_attr($url) . '"' : '';
    }, $html);
    return preg_replace_callback('/\sdata-st-redirect-target\s*=\s*(["\'])(.*?)\1/i', function ($matches) {
      return ' data-st-redirect-target="' . ($matches[2] === '_blank' ? '_blank' : '_self') . '"';
    }, $html);
  }

  /**
   * Resolve Media Library images from their attachment IDs.
   *
   * GrapesJS uses a data URI placeholder for an empty image component. Older
   * editor builds updated only the rendered attribute, so serialization could
   * put that placeholder back into src while retaining data-attachment-id.
   * Treat the WordPress attachment as the source of truth so existing tables
   * repair themselves when loaded and all newly saved markup is canonical.
   */
  private function restoreAttachmentImages($html)
  {
    if (!is_string($html) || $html === '' || stripos($html, 'data-attachment-id') === false) {
      return $html;
    }

    return preg_replace_callback('/<img\b[^>]*>/i', function ($matches) {
      $tag = $matches[0];
      if (!preg_match('/\sdata-attachment-id\s*=\s*(["\'])(\d+)\1/i', $tag, $idMatch)) {
        return $tag;
      }

      $attachmentId = absint($idMatch[2]);
      if (!$attachmentId || !wp_attachment_is_image($attachmentId)) {
        return $tag;
      }

      $sizeName = 'full';
      if (preg_match('/\sdata-attachment-size\s*=\s*(["\'])([^"\']+)\1/i', $tag, $sizeMatch)) {
        $candidate = sanitize_key($sizeMatch[2]);
        if ($candidate !== '') { $sizeName = $candidate; }
      }
      $image = wp_get_attachment_image_src($attachmentId, $sizeName);
      if (!$image && $sizeName !== 'full') {
        $sizeName = 'full';
        $image = wp_get_attachment_image_src($attachmentId, 'full');
      }
      if (!$image || empty($image[0])) {
        return $tag;
      }

      $tag = $this->setHtmlTagAttribute($tag, 'src', esc_url($image[0]));
      $tag = $this->setHtmlTagAttribute($tag, 'data-attachment-size', $sizeName);
      if (!empty($image[1])) { $tag = $this->setHtmlTagAttribute($tag, 'width', (string) absint($image[1])); }
      if (!empty($image[2])) { $tag = $this->setHtmlTagAttribute($tag, 'height', (string) absint($image[2])); }
      return $tag;
    }, $html);
  }

  private function setHtmlTagAttribute($tag, $name, $value)
  {
    $quoted = $name . '="' . esc_attr($value) . '"';
    $pattern = '/\s' . preg_quote($name, '/') . '\s*=\s*(["\']).*?\1/i';
    if (preg_match($pattern, $tag)) {
      return preg_replace($pattern, ' ' . $quoted, $tag, 1);
    }
    if (preg_match('/\s*\/>$/', $tag)) {
      return preg_replace('/\s*\/>$/', ' ' . $quoted . ' />', $tag, 1);
    }
    return preg_replace('/\s*>$/', ' ' . $quoted . '>', $tag, 1);
  }

  public function scopeCss($css, $id)
  {
    $scope = '#supsystic-pricing-' . (int) $id;
    return preg_replace_callback('/(^|(?<=[{}]))\s*([^{}]+)\s*\{/m', function ($matches) use ($scope) {
      $selector = trim($matches[2]);
      if ($selector === '' || $selector[0] === '@') {
        return $matches[1] . $selector . '{';
      }
      $selectors = array_map('trim', explode(',', $selector));
      return $matches[1] . implode(', ', array_map(function ($item) use ($scope) { return $scope . ' ' . $item; }, $selectors)) . '{';
    }, $css);
  }

  private function sanitizeCss($css)
  {
    $css = preg_replace('/url\s*\(\s*(?:javascript|vbscript)\s*:[^;{}]*\)/i', '', $css);
    $css = preg_replace('/@import[^;]*;|expression\s*\([^)]*\)|behavior\s*:[^;]*;|javascript\s*:/i', '', $css);
    $css = preg_replace_callback('/url\s*\(\s*([\'\"]?)(.*?)\1\s*\)/i', function ($matches) {
      $url = trim($matches[2]);
      if (!preg_match('~^(?:https?://|/(?!/))~i', $url) || preg_match('/[\s<>\'\"\\\\]/', $url)) {
        return '';
      }
      $safe = esc_url_raw($url, ['http', 'https']);
      return $safe ? 'url("' . $safe . '")' : '';
    }, $css);
    $css = preg_replace('/@font-face\s*\{[^}]*\}|@keyframes\s+[^{]+\{(?:[^{}]*\{[^}]*\})*\s*\}/i', '', $css);
    $css = $this->removeGrapesProtectedCss($css);
    return trim(str_replace(['<', '>'], '', $css));
  }

  private function removeGrapesProtectedCss($css)
  {
    return preg_replace_callback('/(^|(?<=}))\s*([^{}]+)\{([^{}]*)\}/m', function ($matches) {
      $selector = strtolower(trim($matches[2]));
      $declarations = [];
      $valid = true;
      $body = preg_replace('~/\*[\s\S]*?\*/~', '', $matches[3]);
      foreach (explode(';', $body) as $declaration) {
        $declaration = trim($declaration);
        if ($declaration === '') {
          continue;
        }
        $separator = strpos($declaration, ':');
        if ($separator === false || $separator < 1) {
          $valid = false;
          break;
        }
        $property = strtolower(trim(substr($declaration, 0, $separator)));
        $declarations[$property] = strtolower(trim(substr($declaration, $separator + 1)));
      }
      if (!$valid) {
        return $matches[0];
      }
      $properties = array_keys($declarations);
      $protectedBoxSizing = $selector === '*' && count($properties) === 1 && isset($declarations['box-sizing']) && $declarations['box-sizing'] === 'border-box';
      $marginProperties = ['margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left'];
      $onlyMargins = count($properties) > 0 && count(array_diff($properties, $marginProperties)) === 0;
      $allMarginsZero = $onlyMargins;
      foreach ($declarations as $value) {
        $parts = preg_split('/\s+/', trim($value));
        if (count($parts) < 1 || count($parts) > 4) {
          $allMarginsZero = false;
          break;
        }
        foreach ($parts as $part) {
          if (!preg_match('/^0(?:px|em|rem|%|vw|vh)?$/i', $part)) {
            $allMarginsZero = false;
            break 2;
          }
        }
      }
      $hasCompleteMargin = isset($declarations['margin']) ||
        (isset($declarations['margin-top']) && isset($declarations['margin-right']) && isset($declarations['margin-bottom']) && isset($declarations['margin-left']));
      $protectedBodyMargin = $selector === 'body' && $allMarginsZero && $hasCompleteMargin;
      return $protectedBoxSizing || $protectedBodyMargin ? '' : $matches[0];
    }, (string) $css);
  }

  private function defaultHtml()
  {
    $feature = function ($text) {
      return '<li class="st-pricing-feature"><span class="st-pricing-feature-content"><span class="st-pricing-icon fa fa-check" data-icon-scale="1" aria-hidden="true"></span><span class="st-pricing-feature-text">' . esc_html($text) . '</span></span></li>';
    };
    return '<section class="st-pricing-grid"><article class="st-pricing-card"><header class="st-pricing-header"><h3>Starter</h3><p>For individuals</p></header><div class="st-pricing-price"><strong>$19</strong><small>/ month</small></div><ul class="st-pricing-features">' . $feature('One project') . $feature('Email support') . $feature('Core features') . '</ul><footer class="st-pricing-footer"><a class="st-pricing-button" href="#">Get started</a></footer></article><article class="st-pricing-card st-pricing-featured"><header class="st-pricing-header"><h3>Growth</h3><p>For growing teams</p></header><div class="st-pricing-price"><strong>$49</strong><small>/ month</small></div><ul class="st-pricing-features">' . $feature('Unlimited projects') . $feature('Priority support') . $feature('Advanced features') . '</ul><footer class="st-pricing-footer"><a class="st-pricing-button" href="#">Choose Growth</a></footer></article><article class="st-pricing-card"><header class="st-pricing-header"><h3>Business</h3><p>For organizations</p></header><div class="st-pricing-price"><strong>$99</strong><small>/ month</small></div><ul class="st-pricing-features">' . $feature('Everything in Growth') . $feature('Dedicated support') . $feature('Team controls') . '</ul><footer class="st-pricing-footer"><a class="st-pricing-button" href="#">Contact us</a></footer></article></section>';
  }
}
