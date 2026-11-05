<?php

/** Opt-in Classic Table design tokens. Existing tables keep their appearance until enabled. */
class SupsysticTables_Tables_ClassicDesign
{
  public static function fields()
  {
    $groups = [
      'Rows and typography' => [
        'surfaceBg' => ['Table area background', '#ffffff'],
        'surfacePadding' => ['Table area padding', '16', 'number', 0, 40],
        'stripeBg' => ['Striped row background', '#f1f5f9'],
        'stripeText' => ['Striped row text', '#334155'],
        'hoverBg' => ['Hovered row background', '#e2e8f0'],
        'hoverText' => ['Hovered row text', '#0f172a'],
        'orderBg' => ['Sorted column background', '#e2e8f0'],
        'linkColor' => ['Links and responsive controls', '#1d4ed8'],
        'linkHover' => ['Link hover color', '#1e40af'],
        'cellPadding' => ['Cell padding', '12', 'number', 0, 40],
        'lineHeight' => ['Line height', '1.5', 'number', 1, 3],
        'headerWeight' => ['Header font weight', '600', 'weight'],
        'bodyWeight' => ['Body font weight', '400', 'weight'],
        'borderColor' => ['Table border color', '#cbd5e1'],
        'radius' => ['Table corner radius', '8', 'number', 0, 32],
      ],
      'Search, filters and fields' => [
        'inputText' => ['Input text', '#1e293b'],
        'inputBg' => ['Input background', '#ffffff'],
        'inputBorder' => ['Input border', '#cbd5e1'],
        'placeholder' => ['Placeholder text', '#64748b'],
        'focusColor' => ['Focus ring and selection', '#2563eb'],
        'selectText' => ['Select text', '#1e293b'],
        'selectBg' => ['Select background', '#ffffff'],
        'selectBorder' => ['Select border', '#cbd5e1'],
        'controlRadius' => ['Control corner radius', '6', 'number', 0, 24],
        'controlSize' => ['Control font size', '14', 'number', 10, 32],
        'controlPadding' => ['Control vertical padding', '8', 'number', 2, 24],
      ],
      'Buttons and pagination' => [
        'buttonText' => ['Button text', '#ffffff'],
        'buttonBg' => ['Button background', '#1d4ed8'],
        'buttonBorder' => ['Button border', '#1d4ed8'],
        'buttonHoverText' => ['Button hover text', '#ffffff'],
        'buttonHoverBg' => ['Button hover background', '#1e40af'],
        'buttonSize' => ['Button font size', '13', 'number', 10, 32],
        'buttonWeight' => ['Button font weight', '600', 'weight'],
        'pageText' => ['Page number text', '#334155'],
        'pageBg' => ['Page number background', '#ffffff'],
        'pageBorder' => ['Page number border', '#cbd5e1'],
        'pageActiveText' => ['Active page text', '#ffffff'],
        'pageActiveBg' => ['Active page background', '#1d4ed8'],
        'pageHoverText' => ['Page hover text', '#1e40af'],
        'pageHoverBg' => ['Page hover background', '#eff6ff'],
      ],
      'Sorting, labels and feedback' => [
        'sortColor' => ['Sorting arrows', '#cbd5e1'],
        'sortActive' => ['Active sorting arrow', '#ffffff'],
        'labelColor' => ['Search and length labels', '#475569'],
        'infoColor' => ['Table information', '#64748b'],
        'captionColor' => ['Caption text', '#0f172a'],
        'descriptionColor' => ['Description text', '#475569'],
        'signatureColor' => ['Signature text', '#64748b'],
        'metaSize' => ['Label and information font size', '13', 'number', 10, 28],
        'processingBg' => ['Loading overlay background', '#ffffff'],
        'processingColor' => ['Loading indicator', '#1d4ed8'],
        'emptyColor' => ['Empty result text', '#64748b'],
        'editedColor' => ['Edited cell marker', '#15803d'],
        'dangerColor' => ['Remove row and file controls', '#b91c1c'],
      ],
    ];
    $fields = [];
    foreach ($groups as $group => $items) {
      foreach ($items as $key => $item) {
        $fields[$key] = ['label' => $item[0], 'default' => $item[1], 'type' => isset($item[2]) ? $item[2] : 'color', 'min' => isset($item[3]) ? $item[3] : 0, 'max' => isset($item[4]) ? $item[4] : 0, 'group' => $group];
      }
    }
    return $fields;
  }

  public static function preset($name, $description, $colors, $options = [])
  {
    list($head, $headText, $body, $text, $stripe, $hover, $accent, $accentHover, $border) = $colors;
    $values = [];
    foreach (self::fields() as $key => $field) {
      $values[$key] = $field['default'];
    }
    $values = array_merge($values, [
      'surfaceBg' => $body,
      'stripeBg' => $stripe, 'stripeText' => $text, 'hoverBg' => $hover, 'hoverText' => $text, 'orderBg' => $hover,
      'linkColor' => $accent, 'linkHover' => $accentHover, 'borderColor' => $border,
      'inputText' => $text, 'inputBg' => $body, 'inputBorder' => $border, 'placeholder' => $text, 'focusColor' => $accent,
      'selectText' => $text, 'selectBg' => $body, 'selectBorder' => $border,
      'buttonText' => '#ffffff', 'buttonBg' => $accent, 'buttonBorder' => $accent, 'buttonHoverBg' => $accentHover,
      'pageText' => $text, 'pageBg' => $body, 'pageBorder' => $border, 'pageActiveBg' => $accent,
      'pageHoverText' => $text, 'pageHoverBg' => $hover, 'sortColor' => $headText, 'sortActive' => $headText,
      'labelColor' => $text, 'infoColor' => $text, 'captionColor' => $text, 'descriptionColor' => $text, 'signatureColor' => $text,
      'processingBg' => $body, 'processingColor' => $accent, 'emptyColor' => $text, 'editedColor' => $accent,
    ], isset($options['tokens']) ? $options['tokens'] : []);
    // Filled controls use white labels by default. Keep the palette's hue,
    // lowering only the fill brightness when white would be hard to read.
    $values['buttonBg'] = self::whiteTextBackground($values['buttonBg']);
    $values['buttonHoverBg'] = self::whiteTextBackground($values['buttonHoverBg']);
    $values['pageActiveBg'] = self::whiteTextBackground($values['pageActiveBg']);
    $values['buttonText'] = '#ffffff';
    $values['buttonHoverText'] = '#ffffff';
    $values['pageActiveText'] = '#ffffff';
    $styles = [
      'headerBackgroundColor' => $head, 'headerFontColor' => $headText, 'headerFontSize' => '14', 'headerFontFamily' => 'Arial',
      'cellBackgroundColor' => $body, 'cellFontColor' => $text, 'cellFontSize' => '14', 'cellFontFamily' => 'Arial',
      'externalBorderColor' => $border, 'externalBorderWidth' => '1', 'headerBorderColor' => $head, 'headerBorderWidth' => '0',
      'rowBorderColor' => $border, 'rowBorderWidth' => '1', 'columnBorderColor' => $border, 'columnBorderWidth' => '0',
      'searchBackgroundColor' => $body, 'searchFontColor' => $text, 'searchBorderColor' => $border,
    ];
    return ['name' => $name, 'description' => $description, 'collection' => isset($options['collection']) ? $options['collection'] : 'Essentials', 'tone' => self::luminance($body) < 0.18 ? 'dark' : 'light', 'pro' => !empty($options['pro']), 'values' => $values, 'styles' => array_merge($styles, isset($options['styles']) ? $options['styles'] : [])];
  }

  public static function luminance($hex)
  {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    $channels = [];
    for ($i = 0; $i < 6; $i += 2) {
      $channel = hexdec(substr($hex, $i, 2)) / 255;
      $channels[] = $channel <= 0.04045 ? $channel / 12.92 : pow(($channel + 0.055) / 1.055, 2.4);
    }
    return $channels[0] * 0.2126 + $channels[1] * 0.7152 + $channels[2] * 0.0722;
  }

  public static function contrast($first, $second)
  {
    $a = self::luminance($first);
    $b = self::luminance($second);
    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
  }

  public static function readableText($background)
  {
    $dark = '#0f172a';
    if (self::contrast($background, $dark) >= 4.5) return $dark;
    return self::contrast($background, '#ffffff') >= 4.5 ? '#ffffff' : '#000000';
  }

  public static function whiteTextBackground($background)
  {
    $original = ltrim($background, '#');
    $factor = 1;
    while (self::contrast($background, '#ffffff') < 4.5) {
      $factor *= 0.95;
      $background = '#';
      for ($i = 0; $i < 6; $i += 2) {
        $background .= str_pad(dechex((int) round(hexdec(substr($original, $i, 2)) * $factor)), 2, '0', STR_PAD_LEFT);
      }
    }
    return $background;
  }

  public static function presets()
  {
    return [
      'paper' => self::preset('Paper', 'Quiet, crisp and effortless.', ['#f1f5f9', '#0f172a', '#ffffff', '#334155', '#f8fafc', '#e2e8f0', '#334155', '#0f172a', '#e2e8f0'], ['tokens' => ['radius' => '4', 'controlRadius' => '4', 'sortActive' => '#0f172a']]),
      'ocean' => self::preset('Ocean', 'A clear blue for everyday data.', ['#0c4a6e', '#ffffff', '#ffffff', '#164e63', '#f0f9ff', '#e0f2fe', '#0369a1', '#075985', '#bae6fd']),
      'botanical' => self::preset('Botanical', 'Natural greens with room to breathe.', ['#14532d', '#f0fdf4', '#fcfefb', '#254034', '#f0f7ef', '#dcf0dc', '#166534', '#14532d', '#c4dcc5'], ['tokens' => ['cellPadding' => '14', 'radius' => '12', 'controlRadius' => '10']]),
      'sandstone' => self::preset('Sandstone', 'Warm neutrals, editorial character.', ['#78350f', '#fffbeb', '#fffdf8', '#573d2a', '#faf3e4', '#f3e5c8', '#92400e', '#78350f', '#e7d6b5'], ['styles' => ['headerFontFamily' => 'Georgia', 'headerFontSize' => '16'], 'tokens' => ['radius' => '2', 'controlRadius' => '2']]),
      'graphite' => self::preset('Graphite', 'Compact precision for dense reports.', ['#27272a', '#fafafa', '#ffffff', '#3f3f46', '#f4f4f5', '#e4e4e7', '#3f3f46', '#18181b', '#d4d4d8'], ['tokens' => ['cellPadding' => '8', 'radius' => '0', 'controlRadius' => '0', 'controlPadding' => '6'], 'styles' => ['cellFontSize' => '13']]),
    ];
  }

  public static function variables($settings)
  {
    if (empty($settings['classicDesign']['enabled']) || $settings['classicDesign']['enabled'] !== 'on') {
      return '';
    }
    $css = '';
    foreach (self::fields() as $key => $field) {
      $value = isset($settings['classicDesign'][$key]) ? $settings['classicDesign'][$key] : $field['default'];
      if (!is_scalar($value)) {
        $value = $field['default'];
      }
      if ($field['type'] === 'color') {
        $value = preg_match('/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $value) ? $value : $field['default'];
      } elseif ($field['type'] === 'weight') {
        $value = in_array((string) $value, ['400', '500', '600', '700'], true) ? $value : $field['default'];
      } else {
        $value = is_numeric($value) ? max($field['min'], min($field['max'], (float) $value)) : $field['default'];
        if ($key !== 'lineHeight') {
          $value .= 'px';
        }
      }
      $css .= '--stb-' . $key . ':' . $value . ';';
    }
    return $css;
  }
}
