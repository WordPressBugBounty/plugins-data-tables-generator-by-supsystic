<?php

/**
 * What the table wizard can offer: goals, ready data, visitor tools, palettes, charts and plans.
 * The browser gets it for the live preview, and the server builds tables from the same definitions.
 */
class SupsysticTables_Wizard_Model_Catalog extends SupsysticTables_Core_BaseModel
{
  /**
   * @return array Everything the wizard needs in the browser.
   */
  public function getConfig()
  {
    $environment = $this->environment;

    return [
      'isPro' => (bool) $environment->isPro(),
      'proUrl' => $environment->getModule('core')->buildProUrl(['utm_medium' => 'table_wizard']),
      'goals' => $this->getGoals(),
      'datasets' => $this->getDatasets(),
      'interactions' => $this->getInteractions(),
      'tools' => $this->getTools(),
      'sizes' => $this->getSizes(),
      'palettes' => $this->getPalettes(),
      'charts' => $this->getCharts(),
      'sources' => $this->getSources(),
      'woo' => $this->getWooConfig(),
      'pricing' => $this->getPricingConfig(),
      'strings' => $this->getStrings(),
    ];
  }

  private function t($text)
  {
    return $this->environment->translate($text);
  }

  public function getGoals()
  {
    return [
      'report' => [
        'kind' => 'data',
        'icon' => 'fa-line-chart',
        'name' => $this->t('Report with charts'),
        'text' => $this->t('Sales, budgets and numbers people read at a glance.'),
        'tags' => [$this->t('Charts'), $this->t('Export')],
        'datasets' => ['sales', 'budget', 'traffic'],
        'defaults' => ['interaction' => 'explore', 'size' => 'few', 'palette' => 'ocean', 'chart' => true],
      ],
      'directory' => [
        'kind' => 'data',
        'icon' => 'fa-address-book-o',
        'name' => $this->t('Team or directory'),
        'text' => $this->t('People, stores or members that visitors can search.'),
        'tags' => [$this->t('Search'), $this->t('Sorting')],
        'datasets' => ['team', 'stores', 'members'],
        'defaults' => ['interaction' => 'explore', 'size' => 'some', 'palette' => 'paper'],
      ],
      'catalog' => [
        'kind' => 'data',
        'icon' => 'fa-tags',
        'name' => $this->t('Price list or inventory'),
        'text' => $this->t('Products and services with prices, no store needed.'),
        'tags' => [$this->t('Search'), $this->t('Prices')],
        'datasets' => ['pricelist', 'inventory', 'menu'],
        'defaults' => ['interaction' => 'explore', 'size' => 'some', 'palette' => 'sandstone'],
      ],
      'schedule' => [
        'kind' => 'data',
        'icon' => 'fa-calendar',
        'name' => $this->t('Schedule or timetable'),
        'text' => $this->t('Agendas, classes and shifts in a clear grid.'),
        'tags' => [$this->t('Clean grid'), $this->t('Mobile')],
        'datasets' => ['agenda', 'classes', 'shifts'],
        'defaults' => ['interaction' => 'show', 'size' => 'few', 'palette' => 'botanical', 'scroll' => true],
      ],
      'comparison' => [
        'kind' => 'data',
        'icon' => 'fa-balance-scale',
        'name' => $this->t('Comparison'),
        'text' => $this->t('Plans, products or specs side by side.'),
        'tags' => [$this->t('Side by side'), $this->t('Check marks')],
        'datasets' => ['plans', 'phones', 'hosting'],
        'defaults' => ['interaction' => 'show', 'size' => 'few', 'palette' => 'graphite', 'scroll' => true, 'center' => true, 'boldFirst' => true],
      ],
      'ranking' => [
        'kind' => 'data',
        'icon' => 'fa-trophy',
        'name' => $this->t('Leaderboard or ranking'),
        'text' => $this->t('Standings, top lists and scores in order.'),
        'tags' => [$this->t('Sorting'), $this->t('Charts')],
        'datasets' => ['league', 'salesboard', 'tools'],
        'defaults' => ['interaction' => 'sort', 'size' => 'few', 'palette' => 'ocean', 'boldFirst' => true],
      ],
      'pricing' => [
        'kind' => 'pricing',
        'icon' => 'fa-credit-card',
        'name' => $this->t('Pricing plans'),
        'text' => $this->t('Plan cards that sell, in a visual builder.'),
        'tags' => [$this->t('Plans'), $this->t('Themes')],
      ],
      'woo' => [
        'kind' => 'woo',
        'icon' => 'fa-shopping-cart',
        'name' => $this->t('WooCommerce products'),
        'text' => $this->t('A live product table with add to cart.'),
        'tags' => [$this->t('Live products'), $this->t('Add to cart')],
      ],
      'own' => [
        'kind' => 'own',
        'icon' => 'fa-clipboard',
        'name' => $this->t('Start from my own data'),
        'text' => $this->t('Paste from a spreadsheet or begin with an empty grid.'),
        'tags' => [$this->t('Paste'), $this->t('Empty grid')],
        'defaults' => ['interaction' => 'explore', 'size' => 'some', 'palette' => 'paper'],
      ],
    ];
  }

  public function getDatasets()
  {
    $yes = '✓';
    $no = '—';
    $sets = [
      'sales' => [
        'name' => $this->t('Monthly sales'),
        'title' => $this->t('Monthly sales report'),
        'hint' => $this->t('Revenue, costs and profit by month'),
        'chart' => ['to' => 2, 'title' => $this->t('Revenue and costs'), 'type' => 'ChartjsColumn'],
        'columns' => [['Month'], ['Revenue', 'currency'], ['Costs', 'currency'], ['Profit', 'currency'], ['Orders', 'number']],
        'rows' => [
          ['January', 32000, 21000, 11000, 412], ['February', 35500, 22400, 13100, 455], ['March', 34100, 23000, 11100, 431], ['April', 39800, 24100, 15700, 502],
          ['May', 42300, 25200, 17100, 538], ['June', 47150, 26800, 20350, 601], ['July', 45200, 27100, 18100, 574], ['August', 51900, 28400, 23500, 648],
        ],
      ],
      'budget' => [
        'name' => $this->t('Marketing budget'),
        'title' => $this->t('Marketing budget'),
        'hint' => $this->t('Planned and spent money by channel'),
        'chart' => ['to' => 2, 'title' => $this->t('Planned and spent'), 'type' => 'ChartjsBar'],
        'columns' => [['Channel'], ['Planned', 'currency'], ['Spent', 'currency'], ['Leads', 'number']],
        'rows' => [
          ['Paid search', 12000, 11340, 486], ['Social ads', 8000, 7420, 351], ['Email', 2500, 2210, 298], ['Content', 6000, 5875, 264], ['Events', 9500, 10120, 143], ['Partners', 4000, 3360, 197],
        ],
      ],
      'traffic' => [
        'name' => $this->t('Website traffic'),
        'title' => $this->t('Website traffic'),
        'hint' => $this->t('Visitors and sign-ups by source'),
        'chart' => ['to' => 1, 'title' => $this->t('Visitors by source'), 'type' => 'ChartjsDoughnut'],
        'columns' => [['Source'], ['Visitors', 'number'], ['Sign-ups', 'number'], ['Conversion', '', 'right']],
        'rows' => [
          ['Organic search', 48210, 1640, '3.4%'], ['Direct', 21540, 905, '4.2%'], ['Social', 16320, 391, '2.4%'], ['Referral', 9870, 415, '4.2%'], ['Email', 7450, 521, '7.0%'], ['Paid ads', 12680, 368, '2.9%'],
        ],
      ],
      'team' => [
        'name' => $this->t('Team directory'),
        'title' => $this->t('Our team'),
        'hint' => $this->t('Names, roles and contacts'),
        'columns' => [['Name'], ['Role'], ['Department'], ['Location'], ['Email']],
        'rows' => [
          ['Emily Carter', 'Head of Marketing', 'Marketing', 'Austin, TX', 'emily@example.com'], ['Jake Morrison', 'Lead Developer', 'Engineering', 'Denver, CO', 'jake@example.com'],
          ['Madison Reed', 'Product Designer', 'Design', 'Portland, OR', 'madison@example.com'], ['Noah Bennett', 'Sales Manager', 'Sales', 'Chicago, IL', 'noah@example.com'],
          ['Olivia Hayes', 'Customer Success', 'Support', 'Boston, MA', 'olivia@example.com'], ['Ethan Brooks', 'Data Analyst', 'Finance', 'Seattle, WA', 'ethan@example.com'],
          ['Sophia Turner', 'Content Lead', 'Marketing', 'Nashville, TN', 'sophia@example.com'], ['Liam Foster', 'QA Engineer', 'Engineering', 'Phoenix, AZ', 'liam@example.com'],
        ],
      ],
      'stores' => [
        'name' => $this->t('Store locations'),
        'title' => $this->t('Store locations'),
        'hint' => $this->t('Cities, phones and opening hours'),
        'columns' => [['Store'], ['City'], ['State'], ['Phone'], ['Hours']],
        'rows' => [
          ['Downtown', 'Austin', 'TX', '(512) 555-0142', '9am to 8pm'], ['Riverside', 'Portland', 'OR', '(503) 555-0178', '10am to 7pm'], ['Union Square', 'Denver', 'CO', '(303) 555-0119', '9am to 9pm'],
          ['Lakeview', 'Chicago', 'IL', '(312) 555-0165', '8am to 8pm'], ['Harbor Point', 'Boston', 'MA', '(617) 555-0133', '10am to 6pm'], ['Sunset Plaza', 'Phoenix', 'AZ', '(602) 555-0107', '9am to 7pm'],
        ],
      ],
      'members' => [
        'name' => $this->t('Member list'),
        'title' => $this->t('Member list'),
        'hint' => $this->t('Companies, plans and status'),
        'columns' => [['Member'], ['Company'], ['Plan'], ['Joined'], ['Status']],
        'rows' => [
          ['Grace Sullivan', 'Northwind Labs', 'Gold', 'Mar 2023', 'Active'], ['Mason Clark', 'Blue Ridge Co', 'Silver', 'Jul 2023', 'Active'], ['Ava Mitchell', 'Summit Works', 'Gold', 'Jan 2024', 'Active'],
          ['Lucas Parker', 'Harbor Studio', 'Bronze', 'Feb 2024', 'Paused'], ['Chloe Adams', 'Maple Group', 'Silver', 'May 2024', 'Active'], ['Henry Wright', 'Atlas Partners', 'Gold', 'Sep 2024', 'Active'],
        ],
      ],
      'pricelist' => [
        'name' => $this->t('Service price list'),
        'title' => $this->t('Service price list'),
        'hint' => $this->t('Services, duration and price'),
        'columns' => [['Service'], ['Category'], ['Duration'], ['Price', 'currency']],
        'rows' => [
          ['Logo design', 'Branding', '5 days', 450], ['Brand guidelines', 'Branding', '10 days', 980], ['Landing page', 'Web', '7 days', 1200], ['Online store setup', 'Web', '21 days', 3400],
          ['SEO audit', 'Marketing', '3 days', 390], ['Monthly social media', 'Marketing', '30 days', 750], ['Email campaign', 'Marketing', '4 days', 320],
        ],
      ],
      'inventory' => [
        'name' => $this->t('Product inventory'),
        'title' => $this->t('Product inventory'),
        'hint' => $this->t('SKU, stock level and status'),
        'columns' => [['Product'], ['SKU'], ['Category'], ['Stock', 'number'], ['Price', 'currency'], ['Status']],
        'rows' => [
          ['Trail Backpack', 'BP-2041', 'Bags', 128, 89, 'In stock'], ['Canvas Tote', 'TO-1180', 'Bags', 342, 34, 'In stock'], ['Steel Bottle', 'BT-3312', 'Drinkware', 57, 26, 'Low stock'],
          ['Wool Beanie', 'HT-1407', 'Apparel', 214, 29, 'In stock'], ['Rain Jacket', 'JK-5520', 'Apparel', 0, 149, 'Sold out'], ['Camp Mug', 'MG-2206', 'Drinkware', 19, 18, 'Low stock'],
        ],
      ],
      'menu' => [
        'name' => $this->t('Restaurant menu'),
        'title' => $this->t('Our menu'),
        'hint' => $this->t('Dishes, descriptions and prices'),
        'columns' => [['Dish'], ['Category'], ['Description'], ['Price', 'currency']],
        'rows' => [
          ['Garden Salad', 'Starters', 'Mixed greens, cucumber, lemon dressing', 9.5], ['Tomato Soup', 'Starters', 'Roasted tomatoes, basil, sourdough', 8], ['Grilled Salmon', 'Mains', 'Wild rice, asparagus, dill butter', 24],
          ['Mushroom Risotto', 'Mains', 'Arborio rice, parmesan, thyme', 19], ['Classic Burger', 'Mains', 'Aged cheddar, pickles, house fries', 17], ['Lemon Tart', 'Desserts', 'Shortcrust, citrus curd, cream', 8.5],
        ],
      ],
      'agenda' => [
        'name' => $this->t('Conference agenda'),
        'title' => $this->t('Conference agenda'),
        'hint' => $this->t('Sessions, speakers and rooms'),
        'columns' => [['Time'], ['Session'], ['Speaker'], ['Room'], ['Track']],
        'rows' => [
          ['9:00 AM', 'Opening keynote', 'Emily Carter', 'Main Hall', 'General'], ['10:15 AM', 'Designing for clarity', 'Madison Reed', 'Room A', 'Design'], ['11:30 AM', 'Scaling your data', 'Jake Morrison', 'Room B', 'Engineering'],
          ['1:00 PM', 'Lunch and networking', '', 'Atrium', 'Break'], ['2:00 PM', 'Growth that lasts', 'Noah Bennett', 'Room A', 'Business'], ['3:15 PM', 'Closing panel', 'Olivia Hayes', 'Main Hall', 'General'],
        ],
      ],
      'classes' => [
        'name' => $this->t('Weekly timetable'),
        'title' => $this->t('Weekly class timetable'),
        'hint' => $this->t('Classes across the week'),
        'columns' => [['Time'], ['Monday'], ['Tuesday'], ['Wednesday'], ['Thursday'], ['Friday']],
        'rows' => [
          ['7:00 AM', 'Yoga', 'Spin', 'Yoga', 'Spin', 'Pilates'], ['9:00 AM', 'Pilates', 'Strength', 'Pilates', 'Strength', 'Yoga'], ['12:00 PM', 'Boxing', 'Yoga', 'Boxing', 'Yoga', 'Boxing'], ['5:30 PM', 'Spin', 'Boxing', 'Strength', 'Pilates', 'Spin'], ['7:00 PM', 'Strength', 'Pilates', 'Spin', 'Boxing', 'Yoga'],
        ],
      ],
      'shifts' => [
        'name' => $this->t('Shift roster'),
        'title' => $this->t('Staff shift roster'),
        'hint' => $this->t('Who works when'),
        'columns' => [['Employee'], ['Mon'], ['Tue'], ['Wed'], ['Thu'], ['Fri']],
        'rows' => [
          ['Emily Carter', 'Morning', 'Morning', 'Off', 'Evening', 'Evening'], ['Jake Morrison', 'Evening', 'Off', 'Morning', 'Morning', 'Morning'], ['Madison Reed', 'Off', 'Evening', 'Evening', 'Off', 'Morning'], ['Noah Bennett', 'Morning', 'Evening', 'Morning', 'Evening', 'Off'], ['Olivia Hayes', 'Evening', 'Morning', 'Off', 'Morning', 'Evening'],
        ],
      ],
      'plans' => [
        'name' => $this->t('Software plans'),
        'title' => $this->t('Plan comparison'),
        'hint' => $this->t('Features across three plans'),
        'columns' => [['Feature'], ['Starter'], ['Growth'], ['Scale']],
        'rows' => [
          ['Team members', '3', '10', 'Unlimited'], ['Projects', '5', '50', 'Unlimited'], ['Custom reports', $no, $yes, $yes], ['API access', $no, $yes, $yes], ['Single sign-on', $no, $no, $yes], ['Priority support', $no, $no, $yes],
        ],
      ],
      'phones' => [
        'name' => $this->t('Product specs'),
        'title' => $this->t('Phone comparison'),
        'hint' => $this->t('Specs of three products'),
        'columns' => [['Spec'], ['Nova X'], ['Aero 12'], ['Orbit 8']],
        'rows' => [
          ['Display', '6.7 in OLED', '6.1 in OLED', '6.4 in LCD'], ['Battery', '5000 mAh', '4200 mAh', '4600 mAh'], ['Storage', '256 GB', '128 GB', '128 GB'], ['Main camera', '50 MP', '48 MP', '32 MP'], ['Wireless charging', $yes, $yes, $no], ['Price', '$899', '$699', '$449'],
        ],
      ],
      'hosting' => [
        'name' => $this->t('Hosting plans'),
        'title' => $this->t('Hosting comparison'),
        'hint' => $this->t('What each plan includes'),
        'columns' => [['Feature'], ['Basic'], ['Plus'], ['Business']],
        'rows' => [
          ['Websites', '1', '10', '50'], ['Storage', '10 GB', '100 GB', '500 GB'], ['Free SSL', $yes, $yes, $yes], ['Daily backups', $no, $yes, $yes], ['Staging site', $no, $no, $yes], ['Price per month', '$4', '$9', '$24'],
        ],
      ],
      'league' => [
        'name' => $this->t('League standings'),
        'title' => $this->t('League standings'),
        'hint' => $this->t('Teams, wins and points'),
        'columns' => [['Team'], ['Played', 'number'], ['Won', 'number'], ['Drawn', 'number'], ['Lost', 'number'], ['Points', 'number']],
        'rows' => [
          ['Riverside Rovers', 18, 13, 3, 2, 42], ['Oakland Owls', 18, 12, 2, 4, 38], ['Summit City', 18, 10, 5, 3, 35], ['Harbor Town', 18, 9, 4, 5, 31], ['Lakeside United', 18, 7, 5, 6, 26], ['Mountain Athletic', 18, 5, 4, 9, 19],
        ],
      ],
      'salesboard' => [
        'name' => $this->t('Sales leaderboard'),
        'title' => $this->t('Sales leaderboard'),
        'hint' => $this->t('Top reps this quarter'),
        'chart' => ['to' => 1, 'title' => $this->t('Deals by rep'), 'type' => 'ChartjsBar'],
        'columns' => [['Rep'], ['Deals', 'number'], ['Revenue', 'currency'], ['Region']],
        'rows' => [
          ['Noah Bennett', 48, 212000, 'Midwest'], ['Grace Sullivan', 44, 198500, 'Northeast'], ['Mason Clark', 39, 171200, 'West'], ['Ava Mitchell', 35, 156400, 'South'], ['Lucas Parker', 31, 132900, 'West'], ['Chloe Adams', 27, 118700, 'South'],
        ],
      ],
      'tools' => [
        'name' => $this->t('Top tools'),
        'title' => $this->t('Top tools ranking'),
        'hint' => $this->t('Ratings and prices'),
        'columns' => [['Tool'], ['Category'], ['Rating', '', 'center'], ['Price', '', 'right']],
        'rows' => [
          ['TaskFlow', 'Productivity', '4.9', '$12'], ['PixelForge', 'Design', '4.8', '$19'], ['DataNest', 'Analytics', '4.7', '$29'], ['MailBloom', 'Marketing', '4.6', '$15'], ['ShipLog', 'Developer', '4.5', '$9'], ['DeskPilot', 'Support', '4.4', '$24'],
        ],
      ],
    ];

    foreach ($sets as $key => $set) {
      $columns = [];
      foreach ($set['columns'] as $column) {
        $format = isset($column[1]) ? $column[1] : '';
        $columns[] = [
          'title' => $this->t($column[0]),
          'format' => $format,
          'align' => isset($column[2]) ? $column[2] : ($format ? 'right' : 'left'),
        ];
      }
      $sets[$key]['columns'] = $columns;
    }

    return $sets;
  }

  public function getInteractions()
  {
    return [
      'show' => [
        'icon' => 'fa-eye',
        'name' => $this->t('Just show it'),
        'text' => $this->t('A clean table with nothing extra.'),
        'tools' => ['hover' => true],
      ],
      'sort' => [
        'icon' => 'fa-sort-amount-desc',
        'name' => $this->t('Let them sort'),
        'text' => $this->t('Click a column to order the rows.'),
        'tools' => ['ordering' => true, 'hover' => true, 'stripe' => true],
      ],
      'explore' => [
        'icon' => 'fa-search',
        'name' => $this->t('Let them explore'),
        'text' => $this->t('Search, sorting and pages.'),
        'recommended' => true,
        'tools' => ['searching' => true, 'ordering' => true, 'paging' => true, 'info' => true, 'hover' => true, 'stripe' => true],
      ],
      'power' => [
        'icon' => 'fa-rocket',
        'name' => $this->t('Power tools'),
        'text' => $this->t('Everything, plus export and column filters.'),
        'pro' => true,
        'tools' => ['searching' => true, 'ordering' => true, 'paging' => true, 'info' => true, 'hover' => true, 'stripe' => true, 'columnSearch' => true, 'export' => true],
      ],
    ];
  }

  /**
   * The switches of the visitors step. Those marked PRO only apply in PRO.
   */
  public function getTools()
  {
    return [
      'searching' => ['name' => $this->t('Search box'), 'icon' => 'fa-search'],
      'ordering' => ['name' => $this->t('Sorting'), 'icon' => 'fa-sort'],
      'paging' => ['name' => $this->t('Pages'), 'icon' => 'fa-ellipsis-h'],
      'info' => ['name' => $this->t('Row counter'), 'icon' => 'fa-info-circle'],
      'stripe' => ['name' => $this->t('Striped rows'), 'icon' => 'fa-bars'],
      'hover' => ['name' => $this->t('Row highlight'), 'icon' => 'fa-hand-pointer-o'],
      'compact' => ['name' => $this->t('Compact rows'), 'icon' => 'fa-compress'],
      'columnSearch' => ['name' => $this->t('Filter each column'), 'icon' => 'fa-filter'],
      'export' => ['name' => $this->t('Export buttons'), 'icon' => 'fa-download', 'pro' => true, 'note' => $this->t('CSV, Excel, PDF and print')],
      'editable' => ['name' => $this->t('Edit on the site'), 'icon' => 'fa-pencil', 'pro' => true, 'note' => $this->t('Logged-in users change cells right in the table')],
    ];
  }

  public function getSizes()
  {
    return [
      'few' => ['name' => $this->t('Up to 25 rows'), 'text' => $this->t('All rows fit on one page.'), 'paging' => false],
      'some' => ['name' => $this->t('Up to 1,000 rows'), 'text' => $this->t('Pages keep the table short.'), 'paging' => true],
      'many' => ['name' => $this->t('More than 1,000'), 'text' => $this->t('Rows load from the server as needed.'), 'paging' => true, 'serverSide' => true],
    ];
  }

  /**
   * Only what the preview draws, not the full design data of a palette.
   */
  public function getPalettes()
  {
    $palettes = [];
    foreach ($this->environment->getModule('tables')->getClassicDesignPaletteLibrary() as $key => $preset) {
      $styles = $preset['styles'];
      $values = $preset['values'];
      $palettes[$key] = [
        'name' => $this->t($preset['name']),
        'collection' => $this->t($preset['collection']),
        'tone' => $preset['tone'],
        'pro' => !empty($preset['pro']),
        'headerBg' => $styles['headerBackgroundColor'],
        'headerText' => $styles['headerFontColor'],
        'cellBg' => $styles['cellBackgroundColor'],
        'cellText' => $styles['cellFontColor'],
        'border' => $styles['rowBorderColor'],
        'stripeBg' => $values['stripeBg'],
        'stripeText' => $values['stripeText'],
        'hoverBg' => $values['hoverBg'],
        'surface' => $values['surfaceBg'],
        'radius' => (int) $values['radius'],
        'buttonBg' => $values['buttonBg'],
        'buttonText' => $values['buttonText'],
        'inputBg' => $values['inputBg'],
        'inputText' => $values['inputText'],
        'inputBorder' => $values['inputBorder'],
        'pageActiveBg' => $values['pageActiveBg'],
        'pageActiveText' => $values['pageActiveText'],
        'labelColor' => $values['labelColor'],
      ];
    }

    return $palettes;
  }

  public function getCharts()
  {
    return [
      'ChartjsColumn' => ['name' => $this->t('Columns'), 'text' => $this->t('Compare months or categories'), 'draw' => 'column'],
      'ChartjsLine' => ['name' => $this->t('Line'), 'text' => $this->t('Show a trend over time'), 'draw' => 'line'],
      'ChartjsArea' => ['name' => $this->t('Area'), 'text' => $this->t('A trend with visual weight'), 'draw' => 'area'],
      'ChartjsBar' => ['name' => $this->t('Bars'), 'text' => $this->t('Rank items with long names'), 'draw' => 'bar'],
      'ChartjsPie' => ['name' => $this->t('Pie'), 'text' => $this->t('Parts of a whole'), 'draw' => 'pie'],
      'ChartjsDoughnut' => ['name' => $this->t('Doughnut'), 'text' => $this->t('Parts as a clean ring'), 'draw' => 'doughnut'],
    ];
  }

  public function getSources()
  {
    return [
      'paste' => ['icon' => 'fa-clipboard', 'name' => $this->t('Paste from a spreadsheet'), 'text' => $this->t('Copy cells from Excel or Google Sheets and paste them here.')],
      'blank' => ['icon' => 'fa-th', 'name' => $this->t('Empty grid'), 'text' => $this->t('Choose the size and type your data in the editor.')],
      'file' => ['icon' => 'fa-file-excel-o', 'name' => $this->t('Excel or CSV file'), 'text' => $this->t('Import a file with all its rows.'), 'pro' => true, 'tab' => 'import'],
      'google' => ['icon' => 'fa-google', 'name' => $this->t('Google Sheets sync'), 'text' => $this->t('Keep the table updated from a sheet.'), 'pro' => true, 'tab' => 'source'],
      'database' => ['icon' => 'fa-database', 'name' => $this->t('Database'), 'text' => $this->t('Show rows from a database table or SQL query.'), 'pro' => true, 'tab' => 'source'],
    ];
  }

  private function getWooConfig()
  {
    $active = class_exists('WooCommerce') && function_exists('wc_get_products');
    $config = [
      'active' => $active,
      'installUrl' => admin_url('plugin-install.php?s=woocommerce&tab=search&type=term'),
      'count' => 0,
      'products' => [],
      'categories' => [],
      'sources' => [
        'latest' => ['icon' => 'fa-clock-o', 'name' => $this->t('Latest 20 products'), 'text' => $this->t('Your newest products, ready to edit.')],
        'later' => ['icon' => 'fa-hand-pointer-o', 'name' => $this->t('I will pick them'), 'text' => $this->t('Choose products one by one in the editor.')],
        'all' => ['icon' => 'fa-cubes', 'name' => $this->t('All products, always'), 'text' => $this->t('New products appear in the table by themselves.'), 'pro' => true],
        'category' => ['icon' => 'fa-folder-open-o', 'name' => $this->t('By category'), 'text' => $this->t('A table for each category, updated by itself.'), 'pro' => true],
      ],
      'layouts' => $this->getWooLayouts(),
      'tools' => [
        'ordering' => ['name' => $this->t('Sorting'), 'icon' => 'fa-sort'],
        'paging' => ['name' => $this->t('Pages'), 'icon' => 'fa-ellipsis-h'],
        'info' => ['name' => $this->t('Row counter'), 'icon' => 'fa-info-circle'],
        'multi' => ['name' => $this->t('Add several to cart'), 'icon' => 'fa-check-square-o'],
        'searching' => ['name' => $this->t('Search box'), 'icon' => 'fa-search', 'pro' => true],
        'filterCategory' => ['name' => $this->t('Category filter'), 'icon' => 'fa-filter', 'pro' => true],
        'filterPrice' => ['name' => $this->t('Price filter'), 'icon' => 'fa-sliders', 'pro' => true],
        'columns' => ['name' => $this->t('Show or hide columns'), 'icon' => 'fa-columns', 'pro' => true],
      ],
    ];
    if (!$active) {
      return $config;
    }

    $ids = wc_get_products(['limit' => 6, 'orderby' => 'date', 'order' => 'DESC', 'status' => 'publish', 'return' => 'ids']);
    foreach ($ids as $id) {
      $product = wc_get_product($id);
      if (!$product) {
        continue;
      }
      $categories = wp_get_post_terms($id, 'product_cat', ['fields' => 'names']);
      $config['products'][] = [
        'name' => $product->get_name(),
        'sku' => $product->get_sku(),
        'price' => html_entity_decode(wp_strip_all_tags(wc_price((float) $product->get_price())), ENT_QUOTES),
        'image' => (string) wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
        'category' => is_array($categories) && $categories ? $categories[0] : '',
        'summary' => wp_trim_words(wp_strip_all_tags($product->get_short_description()), 8),
      ];
    }
    $counts = wp_count_posts('product');
    $config['count'] = isset($counts->publish) ? (int) $counts->publish : 0;
    $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => true, 'number' => 40]);
    if (is_array($terms)) {
      foreach ($terms as $term) {
        $config['categories'][] = ['id' => (int) $term->term_id, 'name' => $term->name, 'count' => (int) $term->count];
      }
    }

    return $config;
  }

  public function getWooLayouts()
  {
    return [
      'catalog' => ['icon' => 'fa-th-large', 'name' => $this->t('Catalog'), 'text' => $this->t('Image, name, category, price and a buy button.'), 'columns' => [4, 2, 5, 6, 9]],
      'order' => ['icon' => 'fa-list-ol', 'name' => $this->t('Quick order form'), 'text' => $this->t('Tick several products and add them at once.'), 'columns' => [4, 2, 3, 6, 9], 'multi' => true],
      'list' => ['icon' => 'fa-align-justify', 'name' => $this->t('Price list'), 'text' => $this->t('Compact rows with no images.'), 'columns' => [2, 3, 6, 9]],
      'detailed' => ['icon' => 'fa-newspaper-o', 'name' => $this->t('Detailed'), 'text' => $this->t('Adds a summary and customer reviews.'), 'columns' => [4, 2, 8, 10, 6, 9]],
    ];
  }

  private function getPricingConfig()
  {
    $pricing = $this->environment->getModule('pricing');
        $templates = [];
    foreach (array_merge($pricing->getTemplates(), $pricing->getPremiumTemplates()) as $key => $template) {
      $templates[$key] = [
        'name' => $this->t($template['label']),
        'accent' => $template['accent'],
        'surface' => $template['surface'],
        'pro' => !empty($template['premium']),
      ];
    }

    return [
      'templates' => $templates,
      'sets' => $this->getPlanSets(),
      'extras' => [
        'badge' => ['name' => $this->t('"Most popular" badge'), 'icon' => 'fa-star'],
        'hover' => ['name' => $this->t('Animate cards on hover'), 'icon' => 'fa-magic'],
        'billing' => ['name' => $this->t('Monthly and yearly switcher'), 'icon' => 'fa-toggle-on', 'pro' => true, 'note' => $this->t('Visitors switch the prices with one click')],
        'ajax' => ['name' => $this->t('Show the table by a button'), 'icon' => 'fa-mouse-pointer', 'pro' => true, 'note' => $this->t('Loads the plans from the server when opened')],
      ],
    ];
  }

  public function getPlanSets()
  {
    return [
      'saas' => [
        'name' => $this->t('Software or app'),
        'title' => $this->t('Software pricing'),
        'unit' => $this->t('/ month'),
        'plans' => [
          ['name' => 'Starter', 'for' => $this->t('For individuals'), 'price' => 19, 'yearly' => 15, 'features' => [$this->t('One project'), $this->t('Email support'), $this->t('Core features')], 'button' => $this->t('Get started')],
          ['name' => 'Growth', 'for' => $this->t('For growing teams'), 'price' => 49, 'yearly' => 39, 'features' => [$this->t('Unlimited projects'), $this->t('Priority support'), $this->t('Advanced features')], 'button' => $this->t('Choose Growth')],
          ['name' => 'Business', 'for' => $this->t('For organizations'), 'price' => 99, 'yearly' => 79, 'features' => [$this->t('Everything in Growth'), $this->t('Dedicated support'), $this->t('Team controls')], 'button' => $this->t('Contact us')],
          ['name' => 'Enterprise', 'for' => $this->t('For large companies'), 'price' => 199, 'yearly' => 159, 'features' => [$this->t('Single sign-on'), $this->t('Custom contract'), $this->t('Account manager')], 'button' => $this->t('Talk to sales')],
        ],
      ],
      'services' => [
        'name' => $this->t('Agency or services'),
        'title' => $this->t('Service packages'),
        'unit' => $this->t('/ project'),
        'plans' => [
          ['name' => 'Essential', 'for' => $this->t('A clean start'), 'price' => 490, 'yearly' => 440, 'features' => [$this->t('One landing page'), $this->t('Two revisions'), $this->t('Delivery in 7 days')], 'button' => $this->t('Book now')],
          ['name' => 'Professional', 'for' => $this->t('Most businesses'), 'price' => 1290, 'yearly' => 1160, 'features' => [$this->t('Five pages'), $this->t('Unlimited revisions'), $this->t('SEO setup')], 'button' => $this->t('Book now')],
          ['name' => 'Premium', 'for' => $this->t('Full service'), 'price' => 2890, 'yearly' => 2600, 'features' => [$this->t('Online store'), $this->t('Brand guidelines'), $this->t('Three months of care')], 'button' => $this->t('Get a quote')],
          ['name' => 'Custom', 'for' => $this->t('Built around you'), 'price' => 4900, 'yearly' => 4400, 'features' => [$this->t('Dedicated team'), $this->t('Custom features'), $this->t('Priority schedule')], 'button' => $this->t('Talk to us')],
        ],
      ],
      'membership' => [
        'name' => $this->t('Membership or club'),
        'title' => $this->t('Membership plans'),
        'unit' => $this->t('/ month'),
        'plans' => [
          ['name' => 'Bronze', 'for' => $this->t('Get to know us'), 'price' => 9, 'yearly' => 7, 'features' => [$this->t('Community access'), $this->t('Monthly newsletter'), $this->t('Member discounts')], 'button' => $this->t('Join')],
          ['name' => 'Silver', 'for' => $this->t('Our favorite'), 'price' => 25, 'yearly' => 20, 'features' => [$this->t('Everything in Bronze'), $this->t('Live sessions'), $this->t('Course library')], 'button' => $this->t('Join')],
          ['name' => 'Gold', 'for' => $this->t('The full experience'), 'price' => 59, 'yearly' => 47, 'features' => [$this->t('Everything in Silver'), $this->t('One-on-one coaching'), $this->t('Private events')], 'button' => $this->t('Join')],
          ['name' => 'Patron', 'for' => $this->t('Support our work'), 'price' => 149, 'yearly' => 119, 'features' => [$this->t('Everything in Gold'), $this->t('Early access'), $this->t('Your name on our wall')], 'button' => $this->t('Join')],
        ],
      ],
    ];
  }

  /**
   * The texts of the wizard screens. tables.wizard.js reads them by their English text.
   */
  private function getStrings()
  {
    $strings = [];
    foreach (SupsysticTables_Wizard_Model_Strings::all() as $text) {
      $strings[$text] = $this->t($text);
    }

    return $strings;
  }
}
