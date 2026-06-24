<?php
class SupsysticTables_Woocommerce_Model_Wootables extends SupsysticTables_Core_BaseModel
{
  private $wooColumnModel = null;
  public $wooColumnOrders = null;
  public $isPartTable = false;

  public function getProductsData($params, $search, $productIds = false)
  {
    $showVariations = !$search || $this->getSettingValue($params, 'show_variations') == '1';

    $postStatuses = ['publish'];
    if (!$search || $this->getSettingValue($params, 'show_private') == '1') {
      $postStatuses[] = 'private';
    }

    $args = [
      'posts_per_page' => 10,
      'post_type' => $showVariations ? ['product', 'product_variation'] : 'product',
      'order' => 'DESC',
      'suppress_filters' => true,
      'post_status' => $postStatuses,
      'offset' => $this->getSettingValue($params, 'start', 0, true),
    ];
    if (!$search && is_array($productIds)) {
      $args['post__in'] = $productIds;
    }

    $searchArray = $this->getSettingValue($params, 'search', []);
    $searchValue = $this->getSettingValue($searchArray, 'value', '');
    if (!empty($searchValue)) {
      $args['s'] = $searchValue;
    }

    $orderArray = $this->getSettingValue($params, 'order', []);
    $orderValues = $this->getSettingValue($orderArray, '0', []);
    if (sizeof($orderValues) > 0) {
      $orderColumn = $this->getSettingValue($orderValues, 'column', 0, true);
      $orderDir = $this->getSettingValue($orderValues, 'dir', 'DESC');

      if (!empty($orderColumn) && !empty($orderDir)) {
        switch ($orderColumn) {
          //1 - title columns
          case 1:
            $args['orderby'] = 'title';
            break;
          //5 - price columns
          case 5:
            $args['meta_key'] = '_price';
            $args['orderby'] = 'meta_value_num';
            break;
          //6 - date columns
          case 6:
            $args['orderby'] = 'date';
            break;
        }
        $args['order'] = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
      }
    }

    $dataExist = new WP_Query($args);
    $products = $dataExist->posts;
    $parents = [];
    $existVariations = false;
    $dataArr = [];
    $isAdmin = is_admin();

    foreach ($products as $post) {
      $id = $post->ID;
      $_product = wc_get_product($id);
      if (!$_product) {
        continue;
      }
      $thumbnailSrc = get_the_post_thumbnail($post->ID, [50, 50]);
      if ($_product->post_type == 'product_variation') {
        $existVariations = true;
        $parentId = $_product->get_parent_id();
        if (!isset($parents[$parentId])) {
          $parents[$parentId] = [
            'thumbnail' => get_the_post_thumbnail($parentId, [50, 50]),
            'categories' => get_the_term_list($parentId, 'product_cat', '', ', ', ''),
          ];
        }
        if (empty($thumbnailSrc)) {
          $thumbnailSrc = $parents[$parentId]['thumbnail'];
        }
        $categories = $parents[$parentId]['categories'];
        $variation = $this->getVariationSummary($_product);
      } else {
        $categories = get_the_term_list($post->ID, 'product_cat', '', ', ', '');
        $variation = '';
      }
      $categories = $isAdmin ? str_ireplace('<a', '<a target="_blank"', $categories) : $categories;

      $dataArr[] = [
        '0' => '<input type="checkbox" class="stCheckDataTable" data-id="' . esc_attr($post->ID) . '">',
        '1' => esc_html($post->post_title),
        '2' => $thumbnailSrc,
        '3' => $variation,
        '4' => $categories,
        '5' => $_product->get_price_html(),
        '6' => $post->post_date,
      ];
    }
    return [
      'variations' => $existVariations ? 1 : 0,
      'recordsTotal' => $dataExist->found_posts,
      'recordsFiltered' => $dataExist->found_posts,
      'data' => $dataArr,
    ];
  }

  public function getProductLookupItems($term, $limit = 5, $showVariations = true, $showPrivate = false, $excludeIds = [])
  {
    $term = is_string($term) ? trim($term) : '';
    if ($term === '') {
      return [];
    }

    $limit = max(1, min(20, absint($limit)));
    $excludeIds = array_values(array_unique(array_filter(array_map('absint', (array) $excludeIds))));
    $postStatuses = ['publish'];
    if ($showPrivate) {
      $postStatuses[] = 'private';
    }

    $ids = $this->findLookupProductIds($term, $limit, $showVariations, $postStatuses, $excludeIds);

    return $this->buildLookupProductItems($ids, $postStatuses);
  }

  public function getProductLookupItemsByIds($ids, $showPrivate = true)
  {
    $ids = array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
    if (empty($ids)) {
      return [];
    }

    $postStatuses = ['publish'];
    if ($showPrivate) {
      $postStatuses[] = 'private';
    }

    return $this->buildLookupProductItems($ids, $postStatuses);
  }

  private function getWooColumnsModel()
  {
    if (!$this->wooColumnModel) {
      $this->wooColumnModel = $this->environment->getModule('core')->getModelsFactory()->get('Woocolumns', 'woocommerce');
    }
    return $this->wooColumnModel;
  }

  public function getOrderColumns($orders = false, $notNeed = [])
  {
    if (!$orders && is_array($this->wooColumnOrders)) {
      return $this->wooColumnOrders;
    }

    $wooColumns = $this->getWooColumnsModel()->getAllWooColumns();

    if ($orders == false || empty($orders)) {
      $orders = [];
      $defaultOrder = ['2', '4', '5', '6', '11'];
      foreach ($wooColumns as $column) {
        if (in_array($column['id'], $defaultOrder)) {
          $orders[] = $column;
        }
      }
    } else {
      if (!is_array($orders)) {
        $orders = json_decode($orders, true);
      }
    }
    $columns = [];
    foreach ($orders as $column) {
      $fullSlug = $column['slug'];
      if (in_array($fullSlug, $notNeed)) {
        continue;
      }
      $subDelim = strpos($fullSlug, '-');
      if ($subDelim > 0) {
        $column['main_slug'] = substr($fullSlug, 0, $subDelim);
        $column['sub_slug'] = wc_attribute_taxonomy_name_by_id((int) substr($fullSlug, $subDelim + 1));
      } else {
        $column['main_slug'] = $fullSlug;
      }
      $columns[] = $column;
    }
    $this->wooColumnOrders = $columns;
    return $columns;
  }

  public function getProductsArgs($wooSettings)
  {
    $productIds = $this->getEffectiveProductIds($wooSettings);
    if (empty($productIds)) {
      return false;
    }

    $postStatuses = ['publish'];
    if ($this->environment->isPro() && $this->getSettingValue($wooSettings, 'show_private') == 'on') {
      $postStatuses[] = 'private';
    }

    $args = [
      'post__in' => $productIds,
      'posts_per_page' => -1,
      'post_type' => ['product', 'product_variation'],
      'order' => 'DESC',
      'suppress_filters' => true,
      'post_status' => $postStatuses,
      'offset' => '0',
    ];

    if ($this->getSettingValue($wooSettings, 'hide_out_of_stock') == 'on') {
      $args['meta_query'][] = [
        'key' => '_stock_status',
        'value' => 'outofstock',
        'compare' => 'NOT LIKE',
      ];
    }
    return $args;
  }

  public function getEffectiveProductIds($wooSettings)
  {
    $productIds = array_filter(array_map('absint', explode(',', $this->getSettingValue($wooSettings, 'productids', ''))));

    if ($this->environment->isPro() && $this->getSettingValue($wooSettings, 'auto_categories_enable') === 'on') {
      $autoCategories = array_filter(explode(',', $this->getSettingValue($wooSettings, 'auto_categories_list', '')));
      if (!empty($autoCategories)) {
        $productIds = $this->filterProductIdsByCategories($productIds, $autoCategories);
      }
    }

    $excludeProductIds = array_filter(array_map('absint', explode(',', $this->getSettingValue($wooSettings, 'exclude_productids', ''))));
    if (!empty($excludeProductIds)) {
      $productIds = array_values(array_diff($productIds, $excludeProductIds));
    }

    return array_values(array_unique(array_filter(array_map('absint', $productIds))));
  }

  private function findLookupProductIds($term, $limit, $showVariations, $postStatuses, $excludeIds = [])
  {
    $types = $showVariations ? ['product', 'product_variation'] : ['product'];
    $ids = [];

    if (ctype_digit($term)) {
      $exactId = absint($term);
      if ($exactId > 0) {
        $post = get_post($exactId);
        if ($post && in_array($post->post_type, $types, true) && in_array($post->post_status, $postStatuses, true) && !in_array($exactId, $excludeIds, true)) {
          $ids[] = $exactId;
        }
      }
    }

    $titleQuery = new WP_Query([
      'posts_per_page' => $limit,
      'post_type' => $types,
      'post_status' => $postStatuses,
      'suppress_filters' => true,
      'fields' => 'ids',
      's' => $term,
      'post__not_in' => $excludeIds,
    ]);
    if (!empty($titleQuery->posts)) {
      $ids = array_merge($ids, $titleQuery->posts);
    }

    $skuQuery = new WP_Query([
      'posts_per_page' => $limit,
      'post_type' => $types,
      'post_status' => $postStatuses,
      'suppress_filters' => true,
      'fields' => 'ids',
      'post__not_in' => $excludeIds,
      'meta_query' => [
        [
          'key' => '_sku',
          'value' => $term,
          'compare' => 'LIKE',
        ],
      ],
    ]);
    if (!empty($skuQuery->posts)) {
      $ids = array_merge($ids, $skuQuery->posts);
    }

    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));

    return array_slice($ids, 0, $limit);
  }

  private function buildLookupProductItems($ids, $postStatuses)
  {
    $ids = array_values(array_unique(array_filter(array_map('absint', (array) $ids))));
    if (empty($ids)) {
      return [];
    }

    $query = new WP_Query([
      'posts_per_page' => count($ids),
      'post_type' => ['product', 'product_variation'],
      'post_status' => $postStatuses,
      'post__in' => $ids,
      'orderby' => 'post__in',
      'suppress_filters' => true,
    ]);

    $items = [];
    foreach ($query->posts as $post) {
      $product = wc_get_product($post->ID);
      if (!$product) {
        continue;
      }

      $isVariation = $post->post_type === 'product_variation' || $product->is_type('variation');
      $parentId = $isVariation ? $product->get_parent_id() : 0;
      $thumbnail = get_the_post_thumbnail($post->ID, [50, 50]);
      if ($thumbnail === '' && $parentId) {
        $thumbnail = get_the_post_thumbnail($parentId, [50, 50]);
      }

      $title = $isVariation && $parentId ? get_the_title($parentId) : get_the_title($post->ID);
      $variation = $isVariation ? $this->getVariationSummary($product) : '';
      $sku = $product->get_sku();
      $metaParts = ['#' . $post->ID];
      if ($sku !== '') {
        $metaParts[] = 'SKU: ' . $sku;
      }

      $items[] = [
        'id' => $post->ID,
        'title' => sanitize_text_field($title),
        'thumbnail' => $thumbnail,
        'variation' => sanitize_text_field($variation),
        'meta' => sanitize_text_field(implode(' | ', $metaParts)),
        'type' => $isVariation ? 'variation' : 'product',
      ];
    }

    return $items;
  }

  private function getVariationSummary($product)
  {
    if (!$product || !$product->is_type('variation')) {
      return '';
    }

    $summary = wc_get_formatted_variation($product, true, false, true);
    $summary = html_entity_decode(wp_strip_all_tags((string) $summary), ENT_QUOTES, 'UTF-8');

    return trim(preg_replace('/\s+/u', ' ', $summary));
  }

  public function getRows($id, $settings, $start = false, $length = false, $search = false, $export = false)
  {
    $dataArr = [];

    $wooSettings = $this->getSettingValue($settings, 'woocommerce', []);

    $args = $this->getProductsArgs($wooSettings);
    if (!is_array($args)) {
      return $dataArr;
    }
    if ($search) {
      $args['s'] = $search;
    }
    if ($length) {
      $args['posts_per_page'] = $length;
    }
    if ($start) {
      $args['offset'] = $start;
    }

    $orders = $this->getOrderColumns($this->getSettingValue($wooSettings, 'order'), $export ? ['add_to_cart'] : []);

    $frontend = !is_admin();
    $imgSize = $this->getSettingValue($wooSettings, 'thumbnail_size', 'thumbnail');
    if ($imgSize == 'set_size') {
      $imgSize = [$this->getSettingValue($wooSettings, 'thumbnail_width', 0), $this->getSettingValue($wooSettings, 'thumbnail_height', 0)];
    }

    $multyAddToCart = $this->getSettingValue($wooSettings, 'multiple_add_cart') === 'on';
    $stockNames = wc_get_product_stock_status_options();
    $dataExist = new WP_Query($args);
    $products = $dataExist->posts;

    $parents = [];
    $taxonomies = [];
    $cartUrl = wc_get_cart_url();

    foreach ($products as $post) {
      $id = $post->ID;
      $_product = wc_get_product($id);
      if (!$_product) {
        continue;
      }
      $parentId = $_product->post_type == 'product_variation' ? $_product->get_parent_id() : 0;
      if (!empty($parentId)) {
        $parents[$parentId] = [];
      }
      $mainId = empty($parentId) ? $id : $parentId;

      $sku = $_product->get_sku();
      $priceRange = $this->getProductPriceRange($_product);
      $data = [
        'id' => $id,
        '__stock_status' => $_product->get_stock_status(),
        '__attribute_terms' => $this->getProductAttributeTermMap($_product, $parentId),
        '__category_terms' => $this->getProductCategoryFilterIds($mainId),
        '__price_min' => $priceRange['min'],
        '__price_max' => $priceRange['max'],
      ];

      foreach ($orders as $column) {
        $slug = $column['slug'];
        switch ($column['main_slug']) {
          case 'thumbnail':
            $value = get_the_post_thumbnail($id, $imgSize);
            if (!empty($parentId) && empty($value)) {
              if (!isset($parents[$parentId]['thumbnail'])) {
                $parents[$parentId]['thumbnail'] = get_the_post_thumbnail($parentId, $imgSize);
              }
              $value = $parents[$parentId]['thumbnail'];
            }
            $data['thumbnail'] = $value;
            break;
          case 'product_title':
            $data['product_title'] = $export ? $post->post_title : '<a href="' . esc_url(get_permalink($id)) . '">' . esc_html($post->post_title) . '</a>';
            break;
          case 'attribute':
            $data[$slug] = isset($column['sub_slug']) ? $_product->get_attribute($column['sub_slug']) : $this->getProductAttribute($id, $export);
            break;
          case 'categories':
            $value = false;
            if (!empty($parentId) && isset($parents[$parentId]['categories'])) {
              $value = $parents[$parentId]['categories'];
            }
            if ($value === false) {
              if ($export) {
                $terms = get_the_terms($mainId, 'product_cat');
                $value = '';
                if (!is_wp_error($terms) && !empty($terms)) {
                  foreach ($terms as $term) {
                    $value .= $term->name . ', ';
                  }
                  $value = substr($value, 0, -2);
                }
              } else {
                $value = get_the_term_list($mainId, 'product_cat', '', ', ', '');
                if (!$frontend) {
                  $value = str_ireplace('<a', '<a target="_blank"', $value);
                }
              }
              if (!empty($parentId)) {
                $parents[$parentId]['categories'] = $value;
              }
            }
            $data['categories'] = $value;
            break;
          case 'tags':
            if ($export) {
              $terms = get_the_terms($id, 'product_tag');
              $tags = '';
              if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                  $tags .= $term->name . ', ';
                }
                $tags = substr($tags, 0, -2);
              }
            } else {
              $tags = get_the_term_list($id, 'product_tag', '', ', ', '');
              $tags = $frontend ? $tags : str_ireplace('<a', '<a target="_blank"', $tags);
            }
            $data['tags'] = $tags;
            break;
          case 'price':
            $data['price'] = $export ? $_product->get_price() : $_product->get_price_html();
            break;
          case 'date':
            $data['date'] = $export ? $post->post_date : '<div class="stDateWrapp">' . esc_html($post->post_date) . '</div>';
            break;
          case 'sku':
            $data['sku'] = esc_html($sku);
            break;
          case 'stock':
            $data['stock'] = esc_html($stockNames[$_product->get_stock_status()]);
            break;
          case 'description':
            $data['description'] = strip_tags(mb_strimwidth($_product->get_description(), 0, 100, '...'));
            break;
          case 'short_description':
            $data['short_description'] = strip_tags(mb_strimwidth($_product->get_short_description(), 0, 100, '...'));
            break;
          case 'reviews':
            $reviews = '';
            if ($average = $_product->get_average_rating()) {
              $reviews = $export
                ? $average
                : '<div class="star-rating" title="' . esc_attr(sprintf(__('Rated %s out of 5', 'woocommerce'), $average)) . '"><span style="width:' . esc_attr(($average / 5) * 100) . '%"><strong itemprop="ratingValue" class="rating">' . esc_html($average) . '</strong> ' . esc_html__('out of 5', 'woocommerce') . '</span></div>';
            }
            $data['reviews'] = $reviews;
            break;
          case 'add_to_cart':
            if ($export) {
              $addToCartHtml = '';
            } elseif ($_product->get_stock_status() == 'outofstock') {
              $addToCartHtml = $stockNames['outofstock'];
            } else {
              $variablesHtml = '';
              $varPricesHtml = '';
              if ($_product->is_type('variable')) {
                $variations = [];
                $attributes = [];
                foreach ($_product->get_available_variations() as $variation) {
                  if ($variation['variation_is_visible']) {
                    $varId = $variation['variation_id'];
                    $varAttributes = [];
                    foreach ($variation['attributes'] as $key => $value) {
                      $taxonomy = str_replace('attribute_', '', $key);
                      if (taxonomy_exists($taxonomy)) {
                        if (!isset($taxonomies[$taxonomy])) {
                          $terms = get_terms($taxonomy);
                          foreach ($terms as $term) {
                            $taxonomies[$taxonomy]['terms'][$term->slug] = $term->name;
                          }
                          $taxonomies[$taxonomy]['label'] = get_taxonomy($taxonomy)->labels->singular_name;
                        }
                      } else {
                        if (!isset($taxonomies[$taxonomy])) {
                          $taxonomies[$taxonomy] = ['label' => $taxonomy, 'terms' => []];
                        }
                        if (!empty($value)) {
                          $taxonomies[$taxonomy]['terms'][$value] = $value;
                        } else {
                          $pr_attrs = $_product->get_attributes();
                          if (isset($pr_attrs[$taxonomy])) {
                            $attr_data = $pr_attrs[$taxonomy]->get_data();
                            if (isset($attr_data['options'])) {
                              foreach ($attr_data['options'] as $k => $v) {
                                $taxonomies[$taxonomy]['terms'][$v] = $v;
                              }
                            }
                          }
                        }
                      }

                      if (!isset($taxonomies[$taxonomy])) {
                        break;
                      }
                      if (empty($value)) {
                        $attributes[$taxonomy] = $taxonomies[$taxonomy]['terms'];
                        $varAttributes[$taxonomy] = '';
                      } else {
                        $attributes[$taxonomy][$value] = $taxonomies[$taxonomy]['terms'][$value];
                        $varAttributes[$taxonomy] = $value;
                      }
                    }
                    $variations[$varId] = $varAttributes;
                    $varPricesHtml .= '<div class="stVarPrice stHidden" data-variation_id="' . esc_attr($varId) . '">' . wp_kses_post($variation['price_html']) . '</div>';
                  }
                }
                if (!empty($varPricesHtml)) {
                  $varPricesHtml = '<div class="stVarPrices">' . $varPricesHtml . '</div>';
                }
                if (sizeof($attributes) > 0) {
                  $variablesHtml = '<div class="stVarAttributes" data-variations="' . htmlspecialchars(json_encode($variations), ENT_QUOTES, 'UTF-8') . '">';
                  foreach ($attributes as $taxonomy => $terms) {
                    $variablesHtml .= '<select class="stVarAttribute" data-attribute="' . esc_attr($taxonomy) . '"><option value="">' . esc_html($taxonomies[$taxonomy]['label']) . '</option>';
                    foreach ($terms as $slug => $value) {
                      $variablesHtml .= '<option value="' . esc_attr($slug) . '">' . esc_html($value) . '</option>';
                    }
                    $variablesHtml .= '</select>';
                  }
                  $variablesHtml .= '</div>';
                }
              }
              $quantityHtml = woocommerce_quantity_input([], $_product, false);
              $addToCartUrl = do_shortcode('[add_to_cart_url id="' . $id . '"]');
              $translationSettings = !empty($settings['woocommerce']['translations']) && is_array($settings['woocommerce']['translations']) ? $settings['woocommerce']['translations'] : [];
              $addToCartLabel = !empty($translationSettings['add_to_cart']) ? $translationSettings['add_to_cart'] : esc_html__('Add to cart', 'supsystic-tables');

              $addButtonHtml =
                '<div class="stAddToCartButWrapp"><button href="' .
                esc_url($addToCartUrl) .
                '"' .
                (empty($variablesHtml) ? '' : ' disabled') .
                ' data-quantity="1" class="button stAddToCart" data-product_id="' .
                esc_attr($id) .
                '" data-variation_id="0" data-product_sku="' .
                esc_attr($sku) .
                '"> ' .
                esc_html($addToCartLabel) .
                '</button><a href="' .
                esc_url($cartUrl) .
                '" class="added_to_cart wc-forward stHidden" title="View cart">' .
                esc_html__('View cart', 'supsystic-tables') .
                '</a></div>';
              $addToCartHtml = $variablesHtml . '<div class="stAddToCartWrapper woocommerce">' . $quantityHtml . $addButtonHtml . ($multyAddToCart ? '<input type="checkbox" class="stAddMulty" value="' . esc_attr($id) . '" data-quantity="1">' : '') . '</div>' . $varPricesHtml;
            }
            $data['add_to_cart'] = $addToCartHtml;
            break;
          default:
            break;
        }
      }
      $dataArr[] = $data;
    }
    //var_dump($dataArr);
    return $this->isPartTable ? ['draw' => 0, 'data' => $dataArr, 'recordsTotal' => $dataExist->found_posts, 'recordsFiltered' => $dataExist->found_posts] : $dataArr;
  }

  public function getRowsByPart($id, $settings, $start = false, $length = false, $searchAll = false, $searchCols = [], $orderCol = false, $orderAsc = true, $searchParams = [])
  {
    $this->isPartTable = true;
    $rows = $this->getRows($id, $settings, false, false, false, false);
    $this->isPartTable = false;

    $data = is_array($rows) && isset($rows['data']) && is_array($rows['data']) ? $rows['data'] : [];
    $recordsTotal = is_array($rows) && isset($rows['recordsTotal']) ? (int) $rows['recordsTotal'] : count($data);
    $searchCols = is_array($searchCols) ? $searchCols : [];
    $searchParams = is_array($searchParams) ? $searchParams : [];
    $orders = $this->getOrderColumns($this->getSettingValue($this->getSettingValue($settings, 'woocommerce', []), 'order'));
    $orderSlugs = [];

    foreach ($orders as $column) {
      if (!empty($column['slug'])) {
        $orderSlugs[] = $column['slug'];
      }
    }
    $hiddenSlugs = $this->getHiddenOrderSlugs($orders);
    $searchHidden = $this->environment->isPro() && !empty($settings['searching']) && is_array($settings['searching']) && !empty($settings['searching']['searchByHiddenField']) && $settings['searching']['searchByHiddenField'] === 'on';

    $filtered = [];
    foreach ($data as $row) {
      if (!$this->matchesWooFilters($row, $orderSlugs, $searchParams, $hiddenSlugs, $searchHidden)) {
        continue;
      }
      if (!$this->matchesColumnSearch($row, $orderSlugs, $searchCols, $hiddenSlugs, $searchHidden)) {
        continue;
      }
      if (!$this->matchesGlobalSearch($row, $orderSlugs, $searchAll, $searchParams, $hiddenSlugs, $searchHidden)) {
        continue;
      }
      $filtered[] = $row;
    }

    $recordsFiltered = count($filtered);

    if ($orderCol !== false && isset($orderSlugs[(int) $orderCol])) {
      $sortSlug = $orderSlugs[(int) $orderCol];
      usort($filtered, function ($left, $right) use ($sortSlug, $orderAsc) {
        $leftValue = $this->normalizeCellValue(isset($left[$sortSlug]) ? $left[$sortSlug] : '');
        $rightValue = $this->normalizeCellValue(isset($right[$sortSlug]) ? $right[$sortSlug] : '');
        $result = $this->compareCellValues($leftValue, $rightValue);

        return $orderAsc ? $result : -$result;
      });
    }

    $start = max(0, (int) $start);
    $length = (int) $length;
    if ($length >= 0) {
      $filtered = array_slice($filtered, $start, $length);
    } elseif ($start > 0) {
      $filtered = array_slice($filtered, $start);
    }

    return [
      'draw' => 0,
      'recordsTotal' => $recordsTotal,
      'recordsFiltered' => $recordsFiltered,
      'data' => $filtered,
    ];
  }

  public function generateFrontendTableRows($listProducts)
  {
    $orders = $this->getOrderColumns();

    $table = [];
    $orderSlug = [];
    $orderMaxWidth = [];
    $orderHidden = [];
    $orderResponsivePriority = [];
    if (!empty($orders)) {
      $hasResponsiveHideColumns = false;
      foreach ($orders as $order) {
        if ($this->environment->isPro() && !empty($order['hide_responsive'])) {
          $hasResponsiveHideColumns = true;
          break;
        }
      }

      foreach ($orders as $order) {
        $orderSlug[] = $order['slug'];
        $orderMaxWidth[$order['slug']] = $this->environment->isPro() && !empty($order['max_width']) ? absint($order['max_width']) : 0;
        $orderHidden[$order['slug']] = $this->environment->isPro() && !empty($order['hide_column']);
        if ($hasResponsiveHideColumns) {
          $orderResponsivePriority[$order['slug']] = $this->environment->isPro() && !empty($order['hide_responsive']) ? 10000 : 1;
        }
      }
    } else {
      $orderSlug = ['product_title', 'thumbnail', 'categories', 'price', 'date'];
    }
    foreach ($listProducts as $product) {
      $tempArray = [];
      $rowAttributes = [];
      foreach ($orderSlug as $slug) {
        $cell = ['data' => isset($product[$slug]) ? $product[$slug] : ''];
        if (!empty($orderMaxWidth[$slug])) {
          $cell['maxWidth'] = $orderMaxWidth[$slug];
        }
        if (!empty($orderHidden[$slug])) {
          $cell['meta'] = ['invisibleCell'];
          $cell['invisibleCell'] = true;
        }
        if (isset($orderResponsivePriority[$slug])) {
          $cell['responsivePriority'] = $orderResponsivePriority[$slug];
        }
        $tempArray[] = $cell;
      }
      if (!empty($product['__stock_status'])) {
        $rowAttributes = [
          'data-stock-status' => sanitize_key($product['__stock_status']),
        ];
      }
      if (!empty($product['__attribute_terms']) && is_array($product['__attribute_terms'])) {
        $rowAttributes['data-woo-attributes'] = wp_json_encode($product['__attribute_terms']);
      }
      if (!empty($product['__category_terms']) && is_array($product['__category_terms'])) {
        $rowAttributes['data-woo-categories'] = wp_json_encode(array_map('absint', $product['__category_terms']));
      }
      if (isset($product['__price_min']) && $product['__price_min'] !== null && $product['__price_min'] !== '') {
        $rowAttributes['data-price-min'] = $product['__price_min'];
      }
      if (isset($product['__price_max']) && $product['__price_max'] !== null && $product['__price_max'] !== '') {
        $rowAttributes['data-price-max'] = $product['__price_max'];
      }

      if (!empty($tempArray) && !empty($rowAttributes)) {
        $metaAttributes = [];
        foreach ($rowAttributes as $attrName => $attrValue) {
          if ($attrName === '' || $attrValue === null || $attrValue === '') {
            continue;
          }

          $metaAttributes[] = sprintf('%s="%s"', esc_attr($attrName), esc_attr($attrValue));
        }

        if (!empty($metaAttributes)) {
          $tempArray[0]['data'] = '<span class="stbWooRowMeta" style="display:none !important;" aria-hidden="true" ' . implode(' ', $metaAttributes) . '></span>' . $tempArray[0]['data'];
        }
      }

      $row = ['cells' => $tempArray];
      if (!empty($rowAttributes)) {
        $row['attributes'] = $rowAttributes;
      }
      $table[] = $row;
    }

    return $table;
  }

  public function getProductAttribute($id, $export = false)
  {
    $_product = wc_get_product($id);
    if (!$_product) {
      return '';
    }
    $attributes = $_product->get_attributes();

    $attrStr = '';
    if ($_product->post_type == 'product_variation') {
      foreach ($attributes as $tax => $value) {
        if (empty($value)) {
          continue;
        }
        if (taxonomy_exists($tax)) {
          $meta = get_post_meta($_product->get_id(), 'attribute_' . $tax, true);
          $term = get_term_by('slug', $meta, $tax);
          if ($term) {
            $value = $term->name;
          }

          $tax_object = get_taxonomy($tax);
          if (isset($tax_object->labels->singular_name)) {
            $tax_label = $tax_object->labels->singular_name;
          } elseif (isset($tax_object->label)) {
            $tax_label = $tax_object->label;
            // Trim label prefix since WC 3.0
            if (0 === strpos($tax_label, 'Product ')) {
              $tax_label = substr($tax_label, 8);
            }
          }
        } else {
          $tax_label = $tax;
        }
        $attrStr .= $tax_label . ': ' . $value . ($export ? PHP_EOL : '<br />');
      }
    } else {
      foreach ($attributes as $attribute) {
        // skip variations
        //if($attribute->get_variation()) continue;

        $name = $attribute->get_name();
        if ($attribute->is_taxonomy()) {
          $terms = wp_get_post_terms($_product->get_id(), $name, 'all');
          // get the taxonomy
          $tax = $terms[0]->taxonomy;
          // get the tax object
          $tax_object = get_taxonomy($tax);
          // get tax label
          if (isset($tax_object->labels->singular_name)) {
            $tax_label = $tax_object->labels->singular_name;
          } elseif (isset($tax_object->label)) {
            $tax_label = $tax_object->label;
            // Trim label prefix since WC 3.0
            if (0 === strpos($tax_label, 'Product ')) {
              $tax_label = substr($tax_label, 8);
            }
          }
          $attrStr .= $tax_label . ': ';
          $tax_terms = [];
          foreach ($terms as $term) {
            $single_term = esc_html($term->name);
            array_push($tax_terms, $single_term);
          }
          $attrStr .= implode(', ', $tax_terms) . ($export ? PHP_EOL : '<br />');
        } else {
          $attrStr .= $name . ': ';
          $attrStr .= esc_html(implode(', ', $attribute->get_options())) . ($export ? PHP_EOL : '<br />');
        }
      }
    }
    return $attrStr;
  }

  public function getHeader($dataArr)
  {
    $colName = [];
    $orders = $this->getOrderColumns();
    foreach ($orders as $column) {
      if (!empty($column['show_display_name']) && !empty($column['display_name'])) {
        $colName[$column['slug']] = $column['display_name'];
      } else {
        $colName[$column['slug']] = '';
      }
    }

    array_unshift($dataArr, $colName);
    return $dataArr;
  }

  private function matchesColumnSearch($row, $orderSlugs, $searchCols, $hiddenSlugs = [], $searchHidden = false)
  {
    foreach ($searchCols as $index => $searchTerms) {
      $index = (int) $index;
      if (!isset($orderSlugs[$index])) {
        continue;
      }

      $slug = $orderSlugs[$index];
      if (!$searchHidden && in_array($slug, $hiddenSlugs, true)) {
        continue;
      }
      $value = $this->normalizeCellValue(isset($row[$slug]) ? $row[$slug] : '');
      if (!$this->matchesSearchTerms($value, $searchTerms, false)) {
        return false;
      }
    }

    return true;
  }

  private function matchesGlobalSearch($row, $orderSlugs, $searchAll, $searchParams, $hiddenSlugs = [], $searchHidden = false)
  {
    if ($searchAll === false || $searchAll === '') {
      return true;
    }

    $strict = !empty($searchParams['strictMatching']) && $searchParams['strictMatching'] === 'on';
    foreach ($orderSlugs as $slug) {
      if (!$searchHidden && in_array($slug, $hiddenSlugs, true)) {
        continue;
      }
      $value = $this->normalizeCellValue(isset($row[$slug]) ? $row[$slug] : '');
      if ($this->matchesSearchTerms($value, [$searchAll], $strict)) {
        return true;
      }
    }

    return false;
  }

  private function matchesWooFilters($row, $orderSlugs, $searchParams, $hiddenSlugs = [], $searchHidden = false)
  {
    $wooFilters = !empty($searchParams['wooFilters']) && is_array($searchParams['wooFilters']) ? $searchParams['wooFilters'] : [];

    if (empty($wooFilters)) {
      return true;
    }

    foreach ($wooFilters as $filter) {
      $filterType = isset($filter['type']) ? sanitize_key($filter['type']) : '';
      $filterTaxonomy = isset($filter['taxonomy']) ? $this->normalizeWooAttributeFilterKey($filter['taxonomy']) : '';
      $filterValue = isset($filter['value']) ? sanitize_text_field(wp_unslash($filter['value'])) : '';
      $filterColumns = !empty($filter['columns']) && is_array($filter['columns']) ? array_map('absint', $filter['columns']) : [];
      $matched = false;

      if ($filterType === 'price_range') {
        $filterMin = isset($filter['min']) && $filter['min'] !== '' ? (float) $filter['min'] : null;
        $filterMax = isset($filter['max']) && $filter['max'] !== '' ? (float) $filter['max'] : null;
        $rowMin = isset($row['__price_min']) && $row['__price_min'] !== '' ? (float) $row['__price_min'] : null;
        $rowMax = isset($row['__price_max']) && $row['__price_max'] !== '' ? (float) $row['__price_max'] : null;

        if (!$this->matchesPriceRange($rowMin, $rowMax, $filterMin, $filterMax)) {
          return false;
        }
        continue;
      }

      if ($filterType === 'category_list') {
        $filterValues = !empty($filter['values']) && is_array($filter['values']) ? array_filter(array_map('absint', $filter['values'])) : [];
        $rowCategoryTerms = !empty($row['__category_terms']) && is_array($row['__category_terms']) ? array_filter(array_map('absint', $row['__category_terms'])) : [];

        if (empty($filterValues)) {
          return false;
        }
        if (empty($rowCategoryTerms) || !array_intersect($filterValues, $rowCategoryTerms)) {
          return false;
        }
        continue;
      }

      if ($filterValue === '') {
        continue;
      }

      if ($filterType === 'stock_status') {
        $rowStockStatus = !empty($row['__stock_status']) ? sanitize_key($row['__stock_status']) : '';
        if ($rowStockStatus !== $filterValue) {
          return false;
        }
        continue;
      }

      if ($filterType === 'attribute_term') {
        $rowAttributeTerms = !empty($row['__attribute_terms']) && is_array($row['__attribute_terms']) ? $row['__attribute_terms'] : [];
        $taxonomyTerms = !empty($rowAttributeTerms[$filterTaxonomy]) && is_array($rowAttributeTerms[$filterTaxonomy]) ? array_map([$this, 'normalizeWooAttributeFilterValue'], $rowAttributeTerms[$filterTaxonomy]) : [];
        if ($filterTaxonomy === '' || !in_array($this->normalizeWooAttributeFilterValue($filterValue), $taxonomyTerms, true)) {
          return false;
        }
        continue;
      }

      if (empty($filterColumns)) {
        continue;
      }

      foreach ($filterColumns as $index) {
        if (!isset($orderSlugs[$index])) {
          continue;
        }

        $slug = $orderSlugs[$index];
        if (!$searchHidden && in_array($slug, $hiddenSlugs, true)) {
          continue;
        }

        $value = $this->normalizeCellValue(isset($row[$slug]) ? $row[$slug] : '');
        if ($this->matchesSearchTerms($value, [$filterValue], false)) {
          $matched = true;
          break;
        }
      }

      if (!$matched) {
        return false;
      }
    }

    return true;
  }
  private function matchesSearchTerms($value, $searchTerms, $strict)
  {
    $value = is_string($value) ? $value : '';
    $searchTerms = is_array($searchTerms) ? $searchTerms : [$searchTerms];

    foreach ($searchTerms as $searchTerm) {
      $searchTerm = is_string($searchTerm) ? trim($searchTerm) : '';
      if ($searchTerm === '') {
        continue;
      }

      if ($strict) {
        if (preg_match('~\b' . preg_quote($searchTerm, '~') . '\b~iu', $value)) {
          return true;
        }
      } elseif (stripos($value, $searchTerm) !== false) {
        return true;
      }
    }

    return false;
  }

  private function normalizeCellValue($value)
  {
    if (is_array($value)) {
      $value = implode(' ', array_map([$this, 'normalizeCellValue'], $value));
    }

    $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim($value);
  }

  private function compareCellValues($leftValue, $rightValue)
  {
    $leftNumber = $this->toComparableNumber($leftValue);
    $rightNumber = $this->toComparableNumber($rightValue);
    if ($leftNumber !== null && $rightNumber !== null) {
      if ($leftNumber == $rightNumber) {
        return 0;
      }

      return $leftNumber < $rightNumber ? -1 : 1;
    }

    $leftTime = strtotime($leftValue);
    $rightTime = strtotime($rightValue);
    if ($leftTime !== false && $rightTime !== false) {
      if ($leftTime === $rightTime) {
        return 0;
      }

      return $leftTime < $rightTime ? -1 : 1;
    }

    return strnatcasecmp($leftValue, $rightValue);
  }

  private function toComparableNumber($value)
  {
    $prepared = preg_replace('/[^\d,\.\-]/', '', (string) $value);
    if ($prepared === '' || $prepared === '-' || $prepared === '.' || $prepared === ',') {
      return null;
    }

    if (substr_count($prepared, ',') > 0 && substr_count($prepared, '.') === 0) {
      $prepared = str_replace(',', '.', $prepared);
    } elseif (substr_count($prepared, ',') > 0 && substr_count($prepared, '.') > 0) {
      $prepared = str_replace(',', '', $prepared);
    }

    return is_numeric($prepared) ? (float) $prepared : null;
  }

  private function getHiddenOrderSlugs($orders)
  {
    $hiddenSlugs = [];

    foreach ((array) $orders as $column) {
      if (!is_array($column) || empty($column['slug']) || empty($column['hide_column']) || !$this->environment->isPro()) {
        continue;
      }

      $hiddenSlugs[] = $column['slug'];
    }

    return $hiddenSlugs;
  }

  public function getAttributeFilterData($id, $settings)
  {
    $dataArr = [];
    $wooSettings = $this->getSettingValue($settings, 'woocommerce', []);
    $attributeIds = $this->getSettingValue($wooSettings, 'filter_attribute_selected', []);
    if (!$this->environment->isPro() || !is_array($attributeIds) || sizeof($attributeIds) == 0) {
      return false;
    }

    $args = $this->getProductsArgs($wooSettings);
    if (!is_array($args)) {
      return false;
    }

    $dataExist = new WP_Query($args);
    $postExist = $dataExist->posts;

    if (!$postExist) {
      return false;
    }

    $allAttributes = wc_get_attribute_taxonomies();
    foreach ($attributeIds as $attributeId) {
      if (is_string($attributeId) && strpos($attributeId, 'custom:') === 0) {
        $customAttributeFilter = $this->buildCustomAttributeFilterData($postExist, $attributeId);
        if (!empty($customAttributeFilter)) {
          $dataArr[] = $customAttributeFilter;
        }
        continue;
      }

      $attributeLabel = '';
      $attributeTaxonomy = '';

      foreach ($allAttributes as $attribute) {
        if ((int) $attribute->attribute_id === (int) $attributeId) {
          $attributeLabel = $attribute->attribute_label;
          $attributeTaxonomy = 'pa_' . $attribute->attribute_name;
          break;
        }
      }

      if ($attributeTaxonomy === '') {
        continue;
      }

      $allValues = [];
      foreach ($postExist as $post) {
        $productAttr = get_the_terms($post->post_type == 'product_variation' ? $post->post_parent : $post->ID, $attributeTaxonomy);
        if (is_array($productAttr)) {
          $allValues = array_merge($allValues, $productAttr);
        }
      }

      if (!empty($allValues)) {
        $dataArr[] = [
          'label' => $attributeLabel,
          'type' => 'attribute_term',
          'taxonomy' => $attributeTaxonomy,
          'options' => $this->generateTaxonomyList($allValues),
        ];
      }
      wp_reset_postdata();
    }
    return $dataArr;
  }

  public function getAvailableFilterAttributes($settings)
  {
    $attributes = [];
    $attributeTaxonomies = wc_get_attribute_taxonomies();

    if (is_array($attributeTaxonomies)) {
      foreach ($attributeTaxonomies as $attr) {
        $attrId = is_object($attr) ? (isset($attr->attribute_id) ? absint($attr->attribute_id) : 0) : (is_array($attr) && isset($attr['attribute_id']) ? absint($attr['attribute_id']) : 0);
        $attrName = is_object($attr) ? (isset($attr->attribute_name) ? (string) $attr->attribute_name : '') : (is_array($attr) && isset($attr['attribute_name']) ? (string) $attr['attribute_name'] : '');
        $attrLabel = is_object($attr) ? (isset($attr->attribute_label) ? (string) $attr->attribute_label : '') : (is_array($attr) && isset($attr['attribute_label']) ? (string) $attr['attribute_label'] : '');

        if ($attrId < 1) {
          continue;
        }
        if ($attrLabel === '' && $attrName !== '') {
          $attrLabel = wc_attribute_label(wc_attribute_taxonomy_name($attrName));
        }
        if ($attrLabel === '' && $attrName !== '') {
          $attrLabel = $attrName;
        }

        $attributes[$attrId] = $attrLabel;
      }
    }

    $wooSettings = $this->getSettingValue($settings, 'woocommerce', []);
    $args = $this->getProductsArgs($wooSettings);
    if (is_array($args)) {
      $query = new WP_Query($args);
      if (!empty($query->posts)) {
        foreach ($query->posts as $post) {
          $product = wc_get_product($post->ID);
          if (!$product || !method_exists($product, 'get_attributes')) {
            continue;
          }

          $baseProduct = $product->get_parent_id() ? wc_get_product($product->get_parent_id()) : $product;
          if (!$baseProduct || !method_exists($baseProduct, 'get_attributes')) {
            continue;
          }

          foreach ((array) $baseProduct->get_attributes() as $attribute) {
            if (!$attribute || !method_exists($attribute, 'is_taxonomy') || $attribute->is_taxonomy()) {
              continue;
            }

            $attributeName = trim((string) $attribute->get_name());
            $attributeKey = sanitize_title($attributeName);
            if ($attributeKey === '') {
              continue;
            }

            $attributes['custom:' . $attributeKey] = $attributeName;
          }
        }
      }
      wp_reset_postdata();
    }

    if (empty($attributes)) {
      global $wpdb;

      $tableName = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
      $rows = $wpdb->get_results("SELECT attribute_id, attribute_name, attribute_label FROM {$tableName}");

      if (is_array($rows)) {
        foreach ($rows as $row) {
          if (!is_object($row)) {
            continue;
          }

          $attrId = isset($row->attribute_id) ? absint($row->attribute_id) : 0;
          $attrName = isset($row->attribute_name) ? (string) $row->attribute_name : '';
          $attrLabel = isset($row->attribute_label) ? (string) $row->attribute_label : '';

          if ($attrId < 1) {
            continue;
          }
          if ($attrLabel === '' && $attrName !== '') {
            $attrLabel = $attrName;
          }

          $attributes[$attrId] = $attrLabel;
        }
      }
    }

    natcasesort($attributes);

    return $attributes;
  }

  public function getStockStatusFilterData($settings)
  {
    $wooSettings = $this->getSettingValue($settings, 'woocommerce', []);
    if (
      !$this->environment->isPro()
      || $this->getSettingValue($wooSettings, 'hide_out_of_stock') === 'on'
      || $this->getSettingValue($wooSettings, 'filter_stock_status') !== 'on'
    ) {
      return false;
    }

    return [
      'label' => $this->environment->translate('Availability'),
      'type' => 'stock_status',
      'options' => [
        'instock' => $this->environment->translate('In stock'),
        'outofstock' => $this->environment->translate('Out of stock'),
        'onbackorder' => $this->environment->translate('Preorder'),
      ],
    ];
  }

  public function getCategoryFilterData($settings)
  {
    $wooSettings = $this->getSettingValue($settings, 'woocommerce', []);
    if (
      !$this->environment->isPro()
      || $this->getSettingValue($wooSettings, 'filter_category_list') !== 'on'
    ) {
      return false;
    }

    $args = $this->getProductsArgs($wooSettings);
    if (!is_array($args)) {
      return false;
    }

    $dataExist = new WP_Query($args);
    $posts = $dataExist->posts;
    if (empty($posts)) {
      return false;
    }

    $termMap = [];
    foreach ($posts as $post) {
      $mainId = $post->post_type == 'product_variation' ? $post->post_parent : $post->ID;
      $terms = get_the_terms($mainId, 'product_cat');
      if (is_wp_error($terms) || empty($terms)) {
        continue;
      }

      foreach ($terms as $term) {
        $this->collectCategoryTermWithAncestors($term, $termMap);
      }
    }

    if (empty($termMap)) {
      return false;
    }

    return [
      'label' => $this->environment->translate('Categories'),
      'type' => 'category_list',
      'options' => $this->buildCategoryFilterHierarchy($termMap),
    ];
  }

  public function getPriceFilterData($settings)
  {
    $wooSettings = $this->getSettingValue($settings, 'woocommerce', []);
    if (!$this->environment->isPro()) {
      return false;
    }

    $priceColumn = $this->getPriceColumnConfig($wooSettings);
    if (empty($priceColumn)) {
      return false;
    }

    $args = $this->getProductsArgs($wooSettings);
    if (!is_array($args)) {
      return false;
    }

    $dataExist = new WP_Query($args);
    $posts = $dataExist->posts;
    if (empty($posts)) {
      return false;
    }

    $minPrice = null;
    $maxPrice = null;
    foreach ($posts as $post) {
      $product = wc_get_product($post->ID);
      if (!$product) {
        continue;
      }

      $range = $this->getProductPriceRange($product);
      if ($range['min'] === null || $range['max'] === null) {
        continue;
      }

      $minPrice = $minPrice === null ? $range['min'] : min($minPrice, $range['min']);
      $maxPrice = $maxPrice === null ? $range['max'] : max($maxPrice, $range['max']);
    }

    if ($minPrice === null || $maxPrice === null) {
      return false;
    }

    $showInputs = $this->getSettingValue($wooSettings, 'filter_price_show_inputs') === 'on';
    $showSlider = $this->getSettingValue($wooSettings, 'filter_price_show_slider') === 'on';

    if (!$showInputs && !$showSlider) {
      return false;
    }

    return [
      'label' => !empty($priceColumn['show_display_name']) && !empty($priceColumn['display_name'])
        ? $priceColumn['display_name']
        : (!empty($priceColumn['original_name']) ? $priceColumn['original_name'] : $this->environment->translate('Price')),
      'type' => 'price_range',
      'min' => $minPrice,
      'max' => $maxPrice,
      'show_inputs' => $showInputs ? 1 : 0,
      'show_slider' => $showSlider ? 1 : 0,
      'step' => $this->getPriceFilterStep($minPrice, $maxPrice),
    ];
  }

  private function getProductAttributeTermMap($_product, $parentId = 0)
  {
    $attributeTerms = [];
    $baseProduct = $parentId ? wc_get_product($parentId) : $_product;

    if ($baseProduct && method_exists($baseProduct, 'get_attributes')) {
      foreach ((array) $baseProduct->get_attributes() as $attribute) {
        if (!$attribute || !method_exists($attribute, 'is_taxonomy')) {
          continue;
        }

        if ($attribute->is_taxonomy()) {
          $taxonomy = $attribute->get_name();
          $attributeTerms[$taxonomy] = [];

          foreach ((array) $attribute->get_terms() as $term) {
            if (is_object($term) && !empty($term->slug)) {
              $attributeTerms[$taxonomy][] = sanitize_key($term->slug);
            }
          }

          $attributeTerms[$taxonomy] = array_values(array_unique(array_filter($attributeTerms[$taxonomy])));
          continue;
        }

        $customKey = 'custom:' . sanitize_title((string) $attribute->get_name());
        $customOptions = $this->getCustomAttributeOptions($attribute);
        if (!empty($customOptions)) {
          $attributeTerms[$customKey] = array_keys($customOptions);
        }
      }
    }

    if ($_product && method_exists($_product, 'is_type') && $_product->is_type('variation') && method_exists($_product, 'get_variation_attributes')) {
      foreach ((array) $_product->get_variation_attributes() as $attributeKey => $attributeValue) {
        $taxonomy = str_replace('attribute_', '', (string) $attributeKey);
        $attributeValue = sanitize_key((string) $attributeValue);

        if ($taxonomy !== '' && $attributeValue !== '') {
          if (taxonomy_exists($taxonomy)) {
            $attributeTerms[$taxonomy] = [$attributeValue];
          } else {
            $attributeTerms['custom:' . sanitize_title($taxonomy)] = [$this->normalizeAttributeOptionValue($attributeValue)];
          }
        }
      }
    }

    return $attributeTerms;
  }

  private function buildCustomAttributeFilterData($posts, $attributeKey)
  {
    $attributeKey = is_string($attributeKey) ? strtolower(trim($attributeKey)) : '';
    if (strpos($attributeKey, 'custom:') !== 0) {
      return [];
    }

    $customSlug = sanitize_title(substr($attributeKey, 7));
    if ($customSlug === '') {
      return [];
    }

    $attributeLabel = '';
    $options = [];
    foreach ((array) $posts as $post) {
      $product = wc_get_product($post->post_type == 'product_variation' ? $post->post_parent : $post->ID);
      if (!$product || !method_exists($product, 'get_attributes')) {
        continue;
      }

      foreach ((array) $product->get_attributes() as $attribute) {
        if (!$attribute || !method_exists($attribute, 'is_taxonomy') || $attribute->is_taxonomy()) {
          continue;
        }

        $currentKey = sanitize_title((string) $attribute->get_name());
        if ($currentKey !== $customSlug) {
          continue;
        }

        if ($attributeLabel === '') {
          $attributeLabel = trim((string) $attribute->get_name());
        }

        $options = array_merge($options, $this->getCustomAttributeOptions($attribute));
      }
    }

    $options = array_filter($options);
    if ($attributeLabel === '' || empty($options)) {
      return [];
    }

    natcasesort($options);

    return [
      'label' => $attributeLabel,
      'type' => 'attribute_term',
      'taxonomy' => 'custom:' . $customSlug,
      'options' => $options,
    ];
  }

  private function getCustomAttributeOptions($attribute)
  {
    $options = [];
    if (!$attribute || !method_exists($attribute, 'get_options')) {
      return $options;
    }

    foreach ((array) $attribute->get_options() as $option) {
      $optionLabel = $this->normalizeAttributeOptionLabel($option);
      $optionValue = $this->normalizeAttributeOptionValue($optionLabel);
      if ($optionLabel === '' || $optionValue === '') {
        continue;
      }

      $options[$optionValue] = $optionLabel;
    }

    return $options;
  }

  private function normalizeAttributeOptionLabel($value)
  {
    $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES, 'UTF-8');
    $value = preg_replace('/\s+/u', ' ', $value);

    return trim($value);
  }

  private function normalizeAttributeOptionValue($value)
  {
    $value = $this->normalizeAttributeOptionLabel($value);

    return $value !== '' ? sanitize_title($value) : '';
  }

  private function normalizeWooAttributeFilterKey($value)
  {
    $value = strtolower(trim((string) $value));
    if ($value === '') {
      return '';
    }

    if (strpos($value, 'custom:') === 0) {
      $customKey = sanitize_title(substr($value, 7));
      return $customKey !== '' ? 'custom:' . $customKey : '';
    }

    return sanitize_key($value);
  }

  private function normalizeWooAttributeFilterValue($value)
  {
    return sanitize_title($this->normalizeAttributeOptionLabel($value));
  }

  private function getProductCategoryFilterIds($productId)
  {
    $terms = get_the_terms($productId, 'product_cat');
    if (is_wp_error($terms) || empty($terms)) {
      return [];
    }

    $termIds = [];
    foreach ($terms as $term) {
      if (empty($term->term_id)) {
        continue;
      }

      $termIds[] = (int) $term->term_id;
      $ancestorIds = get_ancestors((int) $term->term_id, 'product_cat', 'taxonomy');
      if (!empty($ancestorIds)) {
        $termIds = array_merge($termIds, array_map('absint', $ancestorIds));
      }
    }

    return array_values(array_unique(array_filter(array_map('absint', $termIds))));
  }

  private function collectCategoryTermWithAncestors($term, &$termMap)
  {
    while ($term && !is_wp_error($term) && !empty($term->term_id)) {
      $termId = (int) $term->term_id;
      if (!isset($termMap[$termId])) {
        $termMap[$termId] = [
          'id' => $termId,
          'parent' => !empty($term->parent) ? (int) $term->parent : 0,
          'name' => $term->name,
        ];
      }

      if (empty($term->parent)) {
        break;
      }

      $term = get_term((int) $term->parent, 'product_cat');
    }
  }

  private function buildCategoryFilterHierarchy($termMap, $parent = 0, $level = 0)
  {
    $options = [];
    $children = [];

    foreach ((array) $termMap as $termData) {
      if ((int) $termData['parent'] === (int) $parent) {
        $children[] = $termData;
      }
    }

    usort($children, function ($left, $right) {
      return strcasecmp($left['name'], $right['name']);
    });

    foreach ($children as $termData) {
      $options[] = [
        'id' => (int) $termData['id'],
        'label' => str_repeat(' - ', max(0, (int) $level)) . $termData['name'],
        'level' => (int) $level,
      ];
      $options = array_merge($options, $this->buildCategoryFilterHierarchy($termMap, (int) $termData['id'], $level + 1));
    }

    return $options;
  }

  private function getPriceColumnConfig($wooSettings)
  {
    $orders = $this->getOrderColumns($this->getSettingValue($wooSettings, 'order'));

    foreach ($orders as $column) {
      if (!empty($column['slug']) && $column['slug'] === 'price') {
        return $column;
      }
    }

    return [];
  }

  private function getProductPriceRange($product)
  {
    if (!$product) {
      return ['min' => null, 'max' => null];
    }

    if (method_exists($product, 'is_type') && $product->is_type('variable')) {
      $min = $product->get_variation_price('min', false);
      $max = $product->get_variation_price('max', false);
    } else {
      $min = $product->get_price();
      $max = $product->get_price();
    }

    $min = $min === '' ? null : (float) $min;
    $max = $max === '' ? null : (float) $max;

    if ($min !== null && $max !== null && $min > $max) {
      $swap = $min;
      $min = $max;
      $max = $swap;
    }

    return ['min' => $min, 'max' => $max];
  }

  private function matchesPriceRange($rowMin, $rowMax, $filterMin, $filterMax)
  {
    if ($filterMin === null && $filterMax === null) {
      return true;
    }

    if ($rowMin === null && $rowMax === null) {
      return false;
    }

    if ($rowMin === null) {
      $rowMin = $rowMax;
    }
    if ($rowMax === null) {
      $rowMax = $rowMin;
    }

    if ($filterMin !== null && $filterMax !== null && $filterMin > $filterMax) {
      $swap = $filterMin;
      $filterMin = $filterMax;
      $filterMax = $swap;
    }

    if ($filterMin !== null && $rowMax < $filterMin) {
      return false;
    }

    if ($filterMax !== null && $rowMin > $filterMax) {
      return false;
    }

    return true;
  }

  private function getPriceFilterStep($minPrice, $maxPrice)
  {
    $values = [(string) $minPrice, (string) $maxPrice];
    foreach ($values as $value) {
      if (strpos($value, '.') !== false) {
        return '0.01';
      }
    }

    return '1';
  }

  private function generateTaxonomyList($terms, $pre = '')
  {
    $data = [];
    $existTaxonomy = [];
    foreach ($terms as $term) {
      if (!in_array($term->slug, $existTaxonomy, true)) {
        $data[$term->slug] = $pre . $term->name;
        if (!empty($term->children)) {
          $data = array_merge($data, $this->generateTaxonomyList($term->children, $pre . ' - '));
        }
        $existTaxonomy[] = $term->slug;
      }
    }
    return $data;
  }

  public function getCategoryHierarchy($parent = 0, $pre = '')
  {
    $args = [
      'hide_empty' => true,
      'parent' => $parent,
    ];
    $terms = get_terms('product_cat', $args);
    $data = [];
    foreach ($terms as $term) {
      if (!empty($term->term_id)) {
        $data[] = ['parent' => $parent, 'value' => $term->term_id, 'title' => $pre . $term->name];
        $data = array_merge($data, $this->getCategoryHierarchy($term->term_id, $pre . '&nbsp;&nbsp;&nbsp;'));
      }
    }
    return $data;
  }

  public function filterProductIdsByCategories($products, $categoryIds)
  {
    if (sizeof($categoryIds) > 0) {
      $args = [
        'post_type' => 'product',
        'ignore_sticky_posts' => true,
        'post_status' => ['publish'],
        'posts_per_page' => -1,
      ];
      if ($categoryIds[0] != 'all') {
        $categoryIds = array_filter(array_map('absint', $categoryIds));
        $args['tax_query'] = [
          [
            'taxonomy' => 'product_cat',
            'field' => 'id',
            'terms' => $categoryIds,
            'include_children' => false,
            'operator' => 'IN',
          ],
        ];
      }
      $postExist = new WP_Query($args);

      foreach ($postExist->posts as $post) {
        $products[] = $post->ID;
      }
      $products = array_unique($products);
    }
    return $products;
  }

  public function addAutoProducts($productId)
  {
    return;
  }

  public function getSettingValue($settings, $name, $default = '', $num = false, $arr = false)
  {
    if (!isset($settings[$name]) || empty($settings[$name])) {
      return $default;
    }
    $value = $settings[$name];
    if ($num && !is_numeric($value)) {
      return $default;
    }
    if ($arr !== false && !in_array($value, $arr)) {
      return $default;
    }
    return $value;
  }
}
