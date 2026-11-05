<?php

/** Curated PRO palette collections, layered over the shared Classic design fields. */
class SupsysticTables_Tables_ClassicDesignLibrary
{
  /** Public preview catalog; applying PRO cards still requires PRO. */
  public static function presets()
  {
    return array_merge([
      'midnight' => SupsysticTables_Tables_ClassicDesign::preset('Midnight', 'Deep navy. Electric cyan. After hours.', ['#0f172a', '#e0f2fe', '#111c30', '#e2e8f0', '#17243a', '#243b53', '#67e8f9', '#22d3ee', '#334764'], ['pro' => true, 'tokens' => ['radius' => '12', 'placeholder' => '#94a3b8', 'dangerColor' => '#fda4af']]),
      'plum' => SupsysticTables_Tables_ClassicDesign::preset('Plum Editorial', 'A rich masthead with a literary touch.', ['#581c53', '#fdf4ff', '#fffbff', '#513451', '#faeffa', '#f3dff3', '#86198f', '#701a75', '#e8cbe9'], ['pro' => true, 'styles' => ['headerFontFamily' => 'Georgia', 'headerFontSize' => '17'], 'tokens' => ['cellPadding' => '16', 'radius' => '0', 'controlRadius' => '3']]),
      'terracotta' => SupsysticTables_Tables_ClassicDesign::preset('Terracotta', 'Sun baked color and generous curves.', ['#7c2d12', '#fff7ed', '#fffaf6', '#643a2d', '#ffeddc', '#fed7b5', '#c2410c', '#9a3412', '#efc5a8'], ['pro' => true, 'tokens' => ['radius' => '16', 'controlRadius' => '16', 'cellPadding' => '15']]),
      'arctic' => SupsysticTables_Tables_ClassicDesign::preset('Arctic', 'Cool mint for a fresh, open workspace.', ['#ccfbf1', '#134e4a', '#ffffff', '#285350', '#f0fdfa', '#d7f6ee', '#0f766e', '#115e59', '#b7e4d9'], ['pro' => true, 'tokens' => ['radius' => '14', 'controlRadius' => '10', 'sortActive' => '#134e4a', 'cellPadding' => '16']]),
      'executive' => SupsysticTables_Tables_ClassicDesign::preset('Executive Gold', 'Ink, ivory and restrained gold.', ['#222326', '#f7e6b5', '#fffdf7', '#45423a', '#f8f2e4', '#efe4c9', '#806013', '#63480c', '#dfd1aa'], ['pro' => true, 'styles' => ['headerFontFamily' => 'Georgia', 'headerFontSize' => '16'], 'tokens' => ['radius' => '3', 'controlRadius' => '3', 'headerWeight' => '500']]),
    ], self::collectionPresets());
  }

  private static function collectionPresets()
  {
    static $presets;
    if ($presets !== null) return $presets;
    $presets = [];
    $descriptions = [
      'Studio' => 'Precise type and clean geometry for focused work.',
      'Nature' => 'Botanical color, gentle contrast and organic spacing.',
      'Ocean' => 'Coastal light, marine ink and clear blue accents.',
      'Editorial' => 'Literary typography with the presence of a magazine masthead.',
      'Pastel' => 'Soft color, generous curves and a quiet, bright surface.',
      'Night' => 'Deep surfaces, luminous accents and carefully balanced contrast.',
      'Earth' => 'Clay, coffee and sun-warmed paper with tactile typography.',
      'Atelier' => 'Restrained luxury, elegant headings and considered details.',
      'Pop' => 'Confident color and playful controls with a strong graphic voice.',
      'Seasonal' => 'A changing year of blossoms, sunshine, autumn ink and winter light.',
    ];
    foreach (self::definitions() as $collection => $items) {
      foreach ($items as $id => $definition) {
        list($name, $profile, $colors) = $definition;
        $options = self::profile($profile);
        $options['pro'] = true;
        $options['collection'] = $collection;
        $dark = SupsysticTables_Tables_ClassicDesign::luminance($colors[2]) < 0.18;
        if (SupsysticTables_Tables_ClassicDesign::contrast($colors[0], $colors[1]) < 4.5) $colors[1] = SupsysticTables_Tables_ClassicDesign::readableText($colors[0]);
        $input = $dark ? $colors[4] : '#ffffff';
        $muted = self::mix($colors[3], $colors[2], 0.78);
        if (SupsysticTables_Tables_ClassicDesign::contrast($muted, $colors[2]) < 4.5) $muted = $colors[3];
        $link = SupsysticTables_Tables_ClassicDesign::contrast($colors[6], $colors[2]) >= 4.5 ? $colors[6] : $colors[3];
        $focus = SupsysticTables_Tables_ClassicDesign::contrast($colors[6], $input) >= 3 ? $colors[6] : $colors[7];
        $options['tokens'] = array_merge($options['tokens'], [
          'inputBg' => $input, 'selectBg' => $input, 'placeholder' => $colors[3],
          'stripeText' => self::legible($colors[3], $colors[4]), 'hoverText' => self::legible($colors[3], $colors[5]),
          'linkColor' => $link, 'linkHover' => SupsysticTables_Tables_ClassicDesign::contrast($colors[7], $colors[2]) >= 4.5 ? $colors[7] : $link,
          'infoColor' => $muted, 'signatureColor' => $muted, 'labelColor' => $muted,
          'focusColor' => $focus, 'dangerColor' => $dark ? '#fda4af' : '#b91c1c',
        ]);
        $options['styles']['searchBackgroundColor'] = $input;
        $presets['pro_' . $id] = SupsysticTables_Tables_ClassicDesign::preset($name, $descriptions[$collection], $colors, $options);
      }
    }
    return $presets;
  }

  private static function legible($text, $background)
  {
    return SupsysticTables_Tables_ClassicDesign::contrast($text, $background) >= 4.5 ? $text : SupsysticTables_Tables_ClassicDesign::readableText($background);
  }

  private static function mix($foreground, $background, $amount)
  {
    $result = '#';
    for ($i = 1; $i < 7; $i += 2) {
      $channel = round(hexdec(substr($foreground, $i, 2)) * $amount + hexdec(substr($background, $i, 2)) * (1 - $amount));
      $result .= str_pad(dechex((int) $channel), 2, '0', STR_PAD_LEFT);
    }
    return $result;
  }

  private static function profile($profile)
  {
    $profiles = [
      'airy' => ['Helvetica', 'Arial', '15', '14', '16', '20', '12', '8', '9', '14', '600', '1.6', '0'],
      'compact' => ['Arial', 'Arial', '13', '13', '8', '12', '4', '3', '6', '12', '700', '1.4', '0'],
      'sharp' => ['Arial', 'Arial', '15', '14', '12', '16', '0', '0', '8', '13', '700', '1.5', '0'],
      'soft' => ['Trebuchet MS', 'Arial', '15', '14', '14', '18', '16', '12', '9', '14', '600', '1.6', '0'],
      'serif' => ['Georgia', 'Georgia', '17', '14', '14', '20', '2', '3', '8', '13', '600', '1.7', '0'],
      'luxe' => ['Georgia', 'Arial', '18', '14', '16', '22', '4', '4', '9', '13', '500', '1.6', '0'],
      'earth' => ['Georgia', 'Tahoma', '16', '14', '14', '18', '6', '5', '8', '13', '600', '1.6', '0'],
      'pop' => ['Arial', 'Verdana', '16', '14', '14', '18', '18', '18', '9', '14', '700', '1.5', '0'],
      'night' => ['Arial', 'Arial', '14', '14', '12', '16', '10', '6', '8', '14', '600', '1.6', '0'],
      'mono' => ['Courier New', 'Courier New', '14', '13', '10', '14', '5', '4', '7', '13', '700', '1.6', '0'],
      'blueprint' => ['Courier New', 'Courier New', '14', '13', '10', '16', '0', '0', '7', '13', '700', '1.5', '1'],
      'technical' => ['Verdana', 'Verdana', '13', '13', '9', '14', '4', '4', '7', '12', '600', '1.5', '1'],
    ];
    list($headFont, $bodyFont, $headSize, $bodySize, $padding, $surface, $radius, $controlRadius, $controlPadding, $controlSize, $weight, $lineHeight, $grid) = $profiles[$profile];
    return [
      'styles' => ['headerFontFamily' => $headFont, 'cellFontFamily' => $bodyFont, 'headerFontSize' => $headSize, 'cellFontSize' => $bodySize, 'columnBorderWidth' => $grid],
      'tokens' => ['cellPadding' => $padding, 'surfacePadding' => $surface, 'radius' => $radius, 'controlRadius' => $controlRadius, 'controlPadding' => $controlPadding, 'controlSize' => $controlSize, 'headerWeight' => $weight, 'lineHeight' => $lineHeight, 'buttonWeight' => $profile === 'pop' || $profile === 'sharp' ? '700' : '600', 'buttonSize' => $controlSize === '12' ? '12' : '13', 'metaSize' => $controlSize === '12' ? '12' : '13'],
    ];
  }

  /** Explicitly curated palettes: header, header text, body, body text, stripe, hover, accent, accent hover, border. */
  private static function definitions()
  {
    return [
      'Studio' => [
        'porcelain' => ['Porcelain', 'airy', ['#eef0f2', '#17212b', '#ffffff', '#334155', '#f8fafc', '#e8eef4', '#475569', '#1e293b', '#d8e0e8']],
        'carbon_ledger' => ['Carbon Ledger', 'technical', ['#20252b', '#f8fafc', '#fafbfc', '#313b48', '#edf0f4', '#dfe6ee', '#3b4654', '#1e293b', '#ccd4dd']],
        'swiss_red' => ['Swiss Red', 'sharp', ['#dc2626', '#ffffff', '#ffffff', '#262626', '#f5f5f5', '#fee2e2', '#dc2626', '#991b1b', '#e5e5e5']],
        'indigo_ink' => ['Indigo Ink', 'compact', ['#312e81', '#eef2ff', '#fdfdff', '#37365a', '#eef2ff', '#e0e7ff', '#4338ca', '#3730a3', '#c7d2fe']],
        'blueprint' => ['Blueprint', 'blueprint', ['#12366b', '#eaf4ff', '#f7fbff', '#244466', '#eaf3fd', '#d5e8fb', '#1d4ed8', '#1e3a8a', '#b8cfea']],
        'gallery_white' => ['Gallery White', 'luxe', ['#ffffff', '#18181b', '#ffffff', '#3f3f46', '#fafafa', '#eeeeef', '#18181b', '#52525b', '#d4d4d8']],
        'graphite_grid' => ['Graphite Grid', 'technical', ['#3f3f46', '#fafafa', '#ffffff', '#3f3f46', '#f4f4f5', '#e4e4e7', '#52525b', '#27272a', '#bfc1c7']],
        'cobalt_studio' => ['Cobalt Studio', 'airy', ['#1e40af', '#ffffff', '#ffffff', '#25344e', '#eff6ff', '#dbeafe', '#2563eb', '#1e40af', '#bfdbfe']],
        'typewriter' => ['Typewriter', 'mono', ['#f2eee5', '#34312c', '#fffdf7', '#454139', '#f8f3e9', '#eae3d5', '#57534e', '#292524', '#d6cfc1']],
        'signal_yellow' => ['Signal Yellow', 'sharp', ['#fde047', '#171717', '#fffef6', '#303030', '#fefce8', '#fef08a', '#eab308', '#a16207', '#e8dc9b']],
      ],
      'Nature' => [
        'moss_linen' => ['Moss & Linen', 'earth', ['#3f5235', '#f7f9ef', '#fffef7', '#374333', '#f0f2e7', '#e2e8d3', '#526b3d', '#354c29', '#ccd4bb']],
        'eucalyptus' => ['Eucalyptus', 'soft', ['#335f57', '#f0fdf8', '#fbfefb', '#2c4e46', '#edf7f0', '#d9eee3', '#357566', '#24594d', '#bbd8cb']],
        'pine_snow' => ['Pine & Snow', 'sharp', ['#123d30', '#ecfdf5', '#ffffff', '#254b3d', '#f0f8f3', '#d4eadc', '#166534', '#14532d', '#b8d6c1']],
        'fern' => ['Fern', 'airy', ['#2f653c', '#f0fdf4', '#fcfef8', '#31513a', '#eef7e6', '#ddefcf', '#3f7d3b', '#2c5b2a', '#c5dfb6']],
        'olive_grove' => ['Olive Grove', 'earth', ['#4d5431', '#fbfbea', '#fffef7', '#4a4c35', '#f3f3e4', '#e8e9cb', '#697238', '#4b5325', '#d4d8b1']],
        'meadow' => ['Meadow', 'soft', ['#dcfce7', '#14532d', '#ffffff', '#2f513b', '#f0fdf4', '#d1fae5', '#15803d', '#166534', '#b7e3c5']],
        'juniper' => ['Juniper', 'night', ['#142c29', '#d7f1e8', '#172f2b', '#d6e8df', '#1d3a33', '#2b4b40', '#86c5a7', '#b1dfc6', '#365a4a']],
        'wildflower' => ['Wildflower', 'serif', ['#654d82', '#faf5ff', '#fffdf8', '#51445b', '#f7f0fa', '#ece0f4', '#8455a6', '#643b85', '#dbcae6']],
        'sequoia' => ['Sequoia', 'earth', ['#6b3b2a', '#fff3e8', '#fffaf2', '#5a3f30', '#f6ecdc', '#ebdac2', '#875236', '#633922', '#d9bfa2']],
        'desert_sage' => ['Desert Sage', 'airy', ['#728068', '#ffffff', '#fffef9', '#424f3c', '#f1f3e9', '#e0e7d4', '#5d7251', '#42563a', '#c9d2bb']],
      ],
      'Ocean' => [
        'atlantic' => ['Atlantic', 'airy', ['#123c58', '#effaff', '#fbfdff', '#25485b', '#edf6fb', '#d9edf7', '#176b91', '#10506f', '#bbd8e8']],
        'tidal' => ['Tidal', 'soft', ['#0f766e', '#f0fdfa', '#fbfffe', '#285651', '#eafaf5', '#ccf3e7', '#0d9488', '#0f766e', '#a8ded2']],
        'deep_sea' => ['Deep Sea', 'night', ['#061f33', '#d8f3ff', '#09283d', '#d9e9f2', '#0f3349', '#1a465e', '#5ed4ef', '#9ae9fa', '#28566c']],
        'coastal_glass' => ['Coastal Glass', 'soft', ['#d4f0ef', '#174d51', '#ffffff', '#33585b', '#f0fbfa', '#d9f1ee', '#197b80', '#145d63', '#b9dedc']],
        'pacific_mist' => ['Pacific Mist', 'airy', ['#456a83', '#f4fbff', '#fbfdff', '#3b5365', '#eff5f9', '#dfeaf2', '#426f90', '#2d526e', '#c7d8e4']],
        'marine_blue' => ['Marine Blue', 'technical', ['#173b80', '#eff6ff', '#fcfdff', '#2c4266', '#edf2fc', '#d9e5fb', '#1d4ed8', '#1e3a8a', '#bbccef']],
        'driftwood' => ['Driftwood', 'earth', ['#536b70', '#f1faf9', '#fffdf7', '#4b5553', '#f0f2ec', '#dfe9e2', '#466e6f', '#315254', '#c6d5ce']],
        'lagoon' => ['Lagoon', 'pop', ['#0e7490', '#ecfeff', '#fbffff', '#235561', '#ecfeff', '#cffafe', '#0891b2', '#0e7490', '#a5dfe8']],
        'fjord' => ['Fjord', 'sharp', ['#31495f', '#edf5fc', '#fbfcfe', '#3e5266', '#edf2f7', '#dce6f0', '#4a6c8e', '#34516e', '#bfcedc']],
        'harbor' => ['Harbor', 'serif', ['#1d4054', '#f6f5eb', '#fffdf5', '#3c5260', '#f3f1e7', '#e3e9e4', '#366b7a', '#244d5a', '#c4d2ce']],
      ],
      'Editorial' => [
        'crimson_press' => ['Crimson Press', 'serif', ['#7f1d1d', '#fff7ed', '#fffdf8', '#4f3230', '#fbefea', '#f2d9d0', '#a02727', '#7f1d1d', '#e7c1b8']],
        'aubergine' => ['Aubergine', 'luxe', ['#4a244b', '#fff4ff', '#fffbfd', '#513d52', '#f5edf6', '#eaddf0', '#7a3d7c', '#582659', '#d9c2dd']],
        'old_library' => ['Old Library', 'serif', ['#344436', '#fbf5df', '#fffaf0', '#4b4838', '#f4ecdc', '#e5dfc8', '#556b43', '#3c4f2e', '#d2c6ac']],
        'wine_paper' => ['Wine & Paper', 'serif', ['#641f3a', '#fff1f2', '#fffaf5', '#553641', '#f9e9e8', '#f0d4d9', '#972d51', '#731b3c', '#dfb7c1']],
        'clay_journal' => ['Clay Journal', 'earth', ['#945341', '#fff7ed', '#fffaf4', '#5f4137', '#faeee4', '#f1dac9', '#a65c43', '#81412d', '#e1baa1']],
        'sepia_notes' => ['Sepia Notes', 'mono', ['#5d4938', '#fff6e7', '#fffaf0', '#5a493a', '#f5ecdc', '#e9dcc7', '#806044', '#5d432e', '#d7c0a5']],
        'atlantic_review' => ['Atlantic Review', 'serif', ['#153d5a', '#f8f4ea', '#fffdf7', '#394d5b', '#eef2f2', '#dce7e9', '#286383', '#194761', '#bfd1da']],
        'rose_chronicle' => ['Rose Chronicle', 'serif', ['#8a3d56', '#fff4f6', '#fffbfc', '#5a3d48', '#faedf1', '#f1d7e1', '#a84b6c', '#81334f', '#e3bfcd']],
        'copper_folio' => ['Copper Folio', 'luxe', ['#5b3528', '#fce8cf', '#fffaf2', '#594337', '#f7ecdd', '#eddbc4', '#a55f35', '#7e4325', '#d9b58f']],
        'midnight_gazette' => ['Midnight Gazette', 'serif', ['#191c28', '#f6e9d5', '#1e2230', '#eee4d7', '#272c3b', '#373d50', '#d9af74', '#f0d0a1', '#4b4a50']],
      ],
      'Pastel' => [
        'lavender_milk' => ['Lavender Milk', 'soft', ['#ede9fe', '#4c1d95', '#fffefe', '#514668', '#f7f3ff', '#e9defb', '#7c3aed', '#6d28d9', '#d9cbef']],
        'peach_sorbet' => ['Peach Sorbet', 'pop', ['#ffdbc8', '#7c2d12', '#fffdfb', '#65453a', '#fff1e8', '#ffdfcc', '#c65d38', '#9a4428', '#edc8b2']],
        'pistachio' => ['Pistachio', 'soft', ['#e1edc8', '#3f5325', '#fffffb', '#4a553c', '#f4f8e9', '#e7efce', '#637f35', '#476025', '#d0dcaf']],
        'powder_blue' => ['Powder Blue', 'airy', ['#dbeafe', '#1e3a8a', '#ffffff', '#3e526f', '#f1f6ff', '#dceaff', '#416eae', '#2f5286', '#c2d5f0']],
        'rose_quartz' => ['Rose Quartz', 'soft', ['#fce1ea', '#831843', '#fffdfd', '#654454', '#fff2f6', '#f9dce7', '#b34b78', '#8b345c', '#edc3d4']],
        'buttercream' => ['Buttercream', 'soft', ['#fef3c7', '#713f12', '#fffef9', '#625039', '#fffae8', '#faedbd', '#a97924', '#805910', '#ead79e']],
        'lilac_cloud' => ['Lilac Cloud', 'airy', ['#f3e8ff', '#6b21a8', '#fffefe', '#594367', '#fbf5ff', '#eddcf9', '#9254b7', '#713a92', '#dec6ec']],
        'apricot_haze' => ['Apricot Haze', 'earth', ['#ffe6d5', '#7c3d1c', '#fffdf8', '#664b3b', '#fff4e9', '#f8e2cc', '#b5723e', '#8e5229', '#e6c4a5']],
        'mint_macaron' => ['Mint Macaron', 'soft', ['#d1fae5', '#065f46', '#fcfffe', '#36574c', '#effcf6', '#d8f4e7', '#288667', '#19664d', '#bce4d3']],
        'soft_periwinkle' => ['Soft Periwinkle', 'airy', ['#e0e7ff', '#3730a3', '#fefeff', '#4c4d70', '#f2f4ff', '#e1e5fb', '#6366b5', '#45488e', '#c9ceed']],
      ],
      'Night' => [
        'obsidian' => ['Obsidian', 'sharp', ['#090b10', '#f8fafc', '#101319', '#e2e8f0', '#181d26', '#263041', '#a8b5c7', '#d2dae5', '#344052']],
        'neon_mint' => ['Neon Mint', 'night', ['#06251e', '#d1fae5', '#091e1a', '#d4ede5', '#102d26', '#1b4437', '#6ee7b7', '#a7f3d0', '#2d5948']],
        'electric_violet' => ['Electric Violet', 'pop', ['#211239', '#f3e8ff', '#191126', '#e9def6', '#261a3a', '#3c2759', '#c084fc', '#d8b4fe', '#51396d']],
        'cyan_circuit' => ['Cyan Circuit', 'blueprint', ['#071e2b', '#cffafe', '#0b1c28', '#d5edf6', '#102b3a', '#1a4051', '#22d3ee', '#67e8f9', '#295568']],
        'ember_night' => ['Ember Night', 'night', ['#291914', '#ffedd5', '#211713', '#f3e0d3', '#30221c', '#4a3025', '#fb923c', '#fdba74', '#624332']],
        'northern_lights' => ['Northern Lights', 'airy', ['#112638', '#d1fae5', '#0e2030', '#d9eaf3', '#172e40', '#25465a', '#5eead4', '#a7f3d0', '#34566b']],
        'velvet_night' => ['Velvet Night', 'luxe', ['#321c30', '#fce7f3', '#261724', '#f0e0ec', '#382235', '#50324b', '#e5a2cc', '#f5c4e3', '#68445f']],
        'midnight_copper' => ['Midnight Copper', 'luxe', ['#171e26', '#ffedd5', '#1c232c', '#e8e0d7', '#252f3b', '#364251', '#d49a6a', '#ebbc91', '#4b5866']],
        'terminal_green' => ['Terminal Green', 'mono', ['#061d12', '#bbf7d0', '#091b12', '#d1f0d9', '#10291c', '#1c3e29', '#4ade80', '#86efac', '#2d583b']],
        'deep_space' => ['Deep Space', 'technical', ['#101532', '#e0e7ff', '#141a36', '#e0e5f5', '#1c2444', '#2b365b', '#a5b4fc', '#c7d2fe', '#3b4872']],
      ],
      'Earth' => [
        'canyon' => ['Canyon', 'earth', ['#8b3f2b', '#fff2e6', '#fff9f1', '#604237', '#f8eadb', '#f0d6bd', '#b45f3d', '#8b442a', '#dfb696']],
        'sienna' => ['Sienna', 'serif', ['#7e3b25', '#fff7ed', '#fffaf3', '#5e4031', '#f7ebdc', '#eed6bd', '#a7542f', '#7c3c22', '#dcb18b']],
        'adobe' => ['Adobe', 'sharp', ['#b6634e', '#ffffff', '#fffaf7', '#65473e', '#faeee7', '#f2d9cd', '#ad5d48', '#84422f', '#e2b7a7']],
        'ochre' => ['Ochre', 'earth', ['#946721', '#fff8e7', '#fffdf5', '#5e4c2f', '#f8f0d9', '#efdfaf', '#aa7a27', '#7e5818', '#ddc58d']],
        'espresso' => ['Espresso', 'luxe', ['#30231e', '#f9ecdc', '#fffaf2', '#514139', '#f3e9dc', '#e5d6c3', '#74523a', '#513624', '#cdb494']],
        'sand_dune' => ['Sand Dune', 'airy', ['#e9dcc3', '#5b4326', '#fffdf7', '#5d4e3c', '#f7f0e2', '#ece0c9', '#967340', '#71532b', '#d8c39e']],
        'red_rock' => ['Red Rock', 'sharp', ['#963d32', '#fff1ed', '#fffaf8', '#643c36', '#fae9e4', '#f1cdc4', '#b04b3f', '#89352b', '#dfaea4']],
        'toffee' => ['Toffee', 'soft', ['#9c673b', '#fff7ed', '#fffaf4', '#624b38', '#f8ecdc', '#efdbbd', '#a36e3b', '#7d4e27', '#ddbf98']],
        'sandalwood' => ['Sandalwood', 'serif', ['#74604a', '#fff8e8', '#fffdf5', '#554b3b', '#f3ede0', '#e5dac5', '#8a724d', '#645034', '#d1c2a5']],
        'burnt_umber' => ['Burnt Umber', 'night', ['#38241b', '#ffedd5', '#2b1f19', '#f0dfce', '#3b2a20', '#543b2b', '#d3a16b', '#e8bf8e', '#71523b']],
      ],
      'Atelier' => [
        'burgundy_gold' => ['Burgundy & Gold', 'luxe', ['#481a2c', '#f9e6b5', '#fffaf2', '#51383d', '#f8efe5', '#eee0cb', '#997624', '#715414', '#d9c28d']],
        'ink_champagne' => ['Ink & Champagne', 'luxe', ['#202530', '#f4e9d2', '#fffdf7', '#42474f', '#f6f1e8', '#e9e2d6', '#8e753c', '#68542a', '#d7c7a6']],
        'emerald_club' => ['Emerald Club', 'luxe', ['#0d4034', '#f5e7bd', '#fffdf6', '#344b41', '#edf3e9', '#dce9d9', '#8d7437', '#675224', '#cdd6b8']],
        'platinum' => ['Platinum', 'airy', ['#454b55', '#ffffff', '#fcfdfe', '#414b59', '#f1f4f7', '#e3e9ef', '#697586', '#485361', '#c9d1dc']],
        'onyx_gold' => ['Onyx & Gold', 'luxe', ['#171717', '#f3d994', '#20201f', '#eee9dd', '#2c2b27', '#41402f', '#d6b76b', '#efd69a', '#5c5337']],
        'imperial_blue' => ['Imperial Blue', 'luxe', ['#192f62', '#f5e7b8', '#fffdf6', '#3b4860', '#f0f2f6', '#dfe5ef', '#8c7130', '#665120', '#c6cbd7']],
        'silk_cocoa' => ['Silk & Cocoa', 'serif', ['#5b4548', '#fff0e7', '#fffaf7', '#58474a', '#f6ece9', '#eadbd7', '#8f6869', '#68494b', '#d6bdbc']],
        'black_pearl' => ['Black Pearl', 'luxe', ['#15282e', '#e3f4ee', '#192d33', '#e0ece7', '#233b40', '#345052', '#a4cabc', '#cce4d8', '#496462']],
        'copper_luxe' => ['Copper Luxe', 'luxe', ['#332a2a', '#f6d7bc', '#fffaf4', '#51423e', '#f6ede2', '#e9d7c5', '#9b6844', '#72482d', '#d3b08f']],
        'ivory_label' => ['Ivory Label', 'serif', ['#f1ebdc', '#3d382d', '#fffdf7', '#4f4b40', '#f6f2e8', '#e8e1d0', '#6f6858', '#4e483b', '#d6ccb7']],
      ],
      'Pop' => [
        'coral_punch' => ['Coral Punch', 'pop', ['#f97066', '#401b1b', '#fffdfc', '#633d3b', '#fff0ed', '#ffdcd5', '#e54d43', '#b93831', '#f2b8af']],
        'mango' => ['Mango', 'pop', ['#fbbf24', '#4c2e08', '#fffdf4', '#614d2a', '#fff5d6', '#fde7a4', '#e99410', '#a96308', '#ebd097']],
        'fuchsia_pop' => ['Fuchsia Pop', 'pop', ['#a21caf', '#ffffff', '#fffcff', '#593660', '#fbedff', '#f5d3fc', '#c026d3', '#a21caf', '#e6b8ef']],
        'ultramarine' => ['Ultramarine', 'sharp', ['#3730a3', '#ffffff', '#fefeff', '#39365f', '#eef0ff', '#dde0fb', '#4f46e5', '#3730a3', '#c5c8f0']],
        'lime_circuit' => ['Lime Circuit', 'blueprint', ['#bef264', '#243a0a', '#fcfff5', '#3f4f2d', '#f1f9dd', '#e0f0bc', '#84cc16', '#4d7c0f', '#c9dda4']],
        'cherry_soda' => ['Cherry Soda', 'pop', ['#be123c', '#ffffff', '#fffafc', '#603445', '#fff0f3', '#ffdce5', '#e11d48', '#be123c', '#f2b8c7']],
        'aqua_arcade' => ['Aqua Arcade', 'pop', ['#22d3ee', '#083344', '#f9ffff', '#25525f', '#e8fcff', '#c4f4fb', '#06b6d4', '#0e7490', '#a8dfe8']],
        'violet_volt' => ['Violet Volt', 'pop', ['#7c3aed', '#ffffff', '#fdfbff', '#4c3866', '#f4edff', '#e9d8ff', '#8b5cf6', '#6d28d9', '#d5bdf5']],
        'tangerine' => ['Tangerine', 'sharp', ['#ea580c', '#ffffff', '#fffaf4', '#633e2c', '#fff0df', '#fedcb9', '#f97316', '#c2410c', '#edc09a']],
        'lemon_ink' => ['Lemon Ink', 'sharp', ['#fef08a', '#18181b', '#fffef7', '#3f3f46', '#fffbe5', '#f7edb0', '#27272a', '#52525b', '#e1d698']],
      ],
      'Seasonal' => [
        'sakura' => ['Sakura', 'soft', ['#f8d8e4', '#7f1d4c', '#fffdfd', '#644653', '#fff1f6', '#f8dfe9', '#b4547d', '#8d3c60', '#eac1d2']],
        'spring_rain' => ['Spring Rain', 'airy', ['#dcecf2', '#254b62', '#fbfeff', '#3c5969', '#eff7fa', '#dcecf4', '#507e98', '#345d75', '#c0d7e3']],
        'summer_citrus' => ['Summer Citrus', 'pop', ['#d9f99d', '#365314', '#fefff8', '#4c5936', '#f4fbdf', '#e6f3bd', '#65a30d', '#4d7c0f', '#cfdfa5']],
        'summer_sky' => ['Summer Sky', 'soft', ['#7dd3fc', '#0c4a6e', '#fbfeff', '#345569', '#edf9ff', '#d5f0ff', '#0284c7', '#0369a1', '#addcf3']],
        'autumn_maple' => ['Autumn Maple', 'earth', ['#92400e', '#fffbeb', '#fffaf1', '#62472c', '#fbefdc', '#f3dcb9', '#b45309', '#92400e', '#e2c092']],
        'harvest' => ['Harvest', 'serif', ['#7c5429', '#fff5d6', '#fffdf3', '#5d4d30', '#f6efda', '#eadbb7', '#947022', '#6e5015', '#d7c28c']],
        'first_frost' => ['First Frost', 'airy', ['#e2edf5', '#334f6b', '#ffffff', '#435b72', '#f2f7fc', '#e2edf8', '#4c779e', '#345778', '#c7d9e9']],
        'winter_berry' => ['Winter Berry', 'luxe', ['#701f45', '#fff1f5', '#fffafb', '#5c3c4c', '#f9edf2', '#efd7e3', '#a33468', '#7c244e', '#dfbbce']],
        'nordic_holiday' => ['Nordic Holiday', 'serif', ['#174538', '#f8e9bd', '#fffdf6', '#3b5145', '#edf5ea', '#d8e8d5', '#9c3643', '#762532', '#c3d4ba']],
        'solstice' => ['Solstice', 'luxe', ['#19233e', '#f8e3ad', '#202b45', '#eee7d8', '#2a3752', '#3c4b68', '#e5bd68', '#f3d99a', '#56617b']],
      ],
    ];
  }
}
