<?php
// fulfil search 
function wcpt_search($filter_info, $post__in = array())
{
  global $wpdb;

  if (empty($filter_info['keyword'])) {
    return $post__in;
  }

  $filter_info = apply_filters('wcpt_search_args', $filter_info);

  if (empty($filter_info['post_type'])) {
    $filter_info['post_type'] = 'product';
  }

  if (!empty($filter_info['use_default_search'])) {

    $search_terms = array_map('trim', explode(' ', $filter_info['keyword']));
    $sql = array();

    foreach ($search_terms as $term) {
      // Terms prefixed with '-' should be excluded.
      $include = '-' !== substr($term, 0, 1);

      if ($include) {
        $like_op = 'LIKE';
        $andor_op = 'OR';
      } else {
        $like_op = 'NOT LIKE';
        $andor_op = 'AND';
        $term = substr($term, 1);
      }

      $like = '%' . $wpdb->esc_like($term) . '%';
      $sql[] = $wpdb->prepare("(($wpdb->posts.post_title $like_op %s) $andor_op ($wpdb->posts.post_excerpt $like_op %s) $andor_op ($wpdb->posts.post_content $like_op %s))", $like, $like, $like);
    }

    if (
      !empty($sql) &&
      !is_user_logged_in()
    ) {
      $sql[] = "($wpdb->posts.post_password = '')";
    }

    $sql = " SELECT ID FROM $wpdb->posts WHERE " . implode(' AND ', $sql);

    $post__in = $wpdb->get_col($sql);

    if (!$post__in) {
      $post__in = array('0');
    }

    return $post__in;
  }

  $search_ids = array(
    'title' => array(
      'phrase_exact' => array(), // $keyword_phrase === $title
      'phrase_like' => array(), // $title = ...$keyword_phrase...
      'keyword_exact' => array(), // $title = $word1 $keyword $word2
      'keyword_like' => array(), // $title = $word1 ...$keyword... $word2
    ),
    'sku' => array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
    ),
    'category' => array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
    ),
    'attribute' => array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
      'items' => array(),
    ),
    'brand' => array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
      'items' => array(),
    ),
    'gtin' => array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
      'items' => array(),
    ),
    'tag' => array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
    ),
    'content' => array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
    ),
    'excerpt' => array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
    ),
    'custom_field' => array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
      'items' => array(),
    ),
  );

  $keyword_phrase = strtolower(trim($filter_info['keyword']));

  $settings = wcpt_get_settings_data();

  // replacements
  foreach (preg_split('/\r\n|\r|\n/', strtolower($settings['search']['replacements'])) as $line) {
    $line = trim($line);
    if (empty($line))
      continue;

    $split1 = array_map('trim', explode(':', $line));
    if (count($split1) < 2)
      continue;

    $correction = $split1[0];
    $incorrect_terms = array_map('trim', explode('|', $split1[1]));

    foreach ($incorrect_terms as $incorrect) {
      if (!empty($incorrect)) {
        // Use word boundaries to prevent partial word matches
        $pattern = '/\b' . preg_quote($incorrect, '/') . '\b/i';
        $keyword_phrase = preg_replace($pattern, $correction, $keyword_phrase);
      }
    }
  }

  $keyword_separator = !empty($settings['search']['separator']) ? $settings['search']['separator'] : ' ';
  $stopwords = array_map('trim', explode(',', $settings['search']['stopwords']));
  $keywords = array_diff(explode($keyword_separator, $keyword_phrase), $stopwords);

  if (empty($filter_info['keyword_match_type'])) {
    $filter_info['keyword_match_type'] = 'any';
  }

  global $wcpt_search__keyword_product_matches;
  $wcpt_search__keyword_product_matches = array_fill_keys($keywords, array());

  if (empty($filter_info['target'])) {
    $filter_info['target'] = array('title', 'content');
  }

  $filter_info['target'] = apply_filters('wcpt_search_target', $filter_info['target']);

  if (in_array('custom_field', $filter_info['target'])) {

    $custom_fields__custom_rules = array(); // custom field with custom rules defined by user
    $custom_fields__default_rules = array(); // custom fields with default rules

    // if there are global settings for custom field search weightage
    if ($settings['search']['custom_field']['items']) {
      // custom fields in the global settings
      $registered_custom_fields = array_column($settings['search']['custom_field']['items'], 'item');
      // custom fields that aren't in the global settings
      $unregistered_custom_fields = array_diff($filter_info['custom_fields'], $registered_custom_fields);
      // register them
      foreach ($unregistered_custom_fields as $_custom_field) {
        $settings['search']['custom_field']['items'][] = array(
          'item' => $_custom_field,
          'custom_rules_enabled' => false,
        );
      }

      foreach ($settings['search']['custom_field']['items'] as $item) {
        if (
          !in_array($item['item'], $filter_info['custom_fields'])
        ) {
          continue;
        }

        if ($item['custom_rules_enabled']) {
          $custom_fields__custom_rules[] = $item['item'];
        } else {
          $custom_fields__default_rules[] = $item['item'];
        }
      }

      // if there aren't
    } else {
      $custom_fields__default_rules = $filter_info['custom_fields'];

    }

  }

  if (in_array('attribute', $filter_info['target'])) {

    $attributes__custom_rules = array();
    $attributes__default_rules = array();

    if (!$settings['search']['attribute']['items']) {
      $attributes__default_rules = $filter_info['attributes'];

    } else {
      foreach ($settings['search']['attribute']['items'] as $item) {
        if (
          !in_array($item['item'], $filter_info['attributes'])
        ) {
          continue;
        }

        if ($item['custom_rules_enabled']) {
          $attributes__custom_rules[] = $item['item'];
        } else {
          $attributes__default_rules[] = $item['item'];
        }
      }

    }

  }

  if (in_array('title', $filter_info['target'])) {
    $field = 'title';
    $item = null;

    $query = "
      SELECT ID 
      FROM $wpdb->posts 
      WHERE $wpdb->posts.post_type = '" . $filter_info['post_type'] . "' 
      AND post_title 
    ";
    wcpt_search__query($field, $item, $query, $keyword_phrase, $keywords, $search_ids);
  }

  if (in_array('category', $filter_info['target'])) {
    $field = 'category';
    $item = null;

    $query = "
      SELECT $wpdb->term_relationships.object_id 
      FROM $wpdb->terms
      INNER JOIN $wpdb->term_taxonomy ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id
      INNER JOIN $wpdb->term_relationships ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
      WHERE $wpdb->term_taxonomy.taxonomy = 'product_cat' 
      AND name
    ";
    wcpt_search__query($field, $item, $query, $keyword_phrase, $keywords, $search_ids);
  }

  if (in_array('brand', $filter_info['target'])) {
    $field = 'brand';
    $item = null;

    $query = "
      SELECT $wpdb->term_relationships.object_id 
      FROM $wpdb->terms
      INNER JOIN $wpdb->term_taxonomy ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id
      INNER JOIN $wpdb->term_relationships ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
      WHERE $wpdb->term_taxonomy.taxonomy = 'product_brand' 
      AND name
    ";
    wcpt_search__query($field, $item, $query, $keyword_phrase, $keywords, $search_ids);
  }

  if (in_array('attribute', $filter_info['target'])) {
    $field = 'attribute';
    $item = null;

    $query = "
      SELECT $wpdb->term_relationships.object_id 
      FROM $wpdb->terms
      INNER JOIN $wpdb->term_taxonomy ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id
      INNER JOIN $wpdb->term_relationships ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
      WHERE $wpdb->term_taxonomy.taxonomy %s 
      AND name       
    ";

    // default rule items
    if (count($attributes__default_rules)) {
      $var = "IN ('pa_" . implode("','pa_", $attributes__default_rules) . "')";
      wcpt_search__query($field, null, sprintf($query, $var), $keyword_phrase, $keywords, $search_ids);
    }

    // custom rule items
    if (count($attributes__custom_rules)) {
      foreach ($attributes__custom_rules as $item) {
        $var = "= 'pa_$item'";
        wcpt_search__query($field, $item, sprintf($query, $var), $keyword_phrase, $keywords, $search_ids);
      }

    }
  }

  if (in_array('tag', $filter_info['target'])) {
    $field = 'tag';
    $item = null;

    $query = "
      SELECT $wpdb->term_relationships.object_id 
      FROM $wpdb->terms
      INNER JOIN $wpdb->term_taxonomy ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id
      INNER JOIN $wpdb->term_relationships ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
      WHERE $wpdb->term_taxonomy.taxonomy = 'product_tag' 
      AND name
    ";
    wcpt_search__query($field, $item, $query, $keyword_phrase, $keywords, $search_ids);
  }

  if (in_array('content', $filter_info['target'])) {
    $field = 'content';
    $item = null;

    $query = "
      SELECT ID 
      FROM $wpdb->posts 
      WHERE post_type = '" . $filter_info['post_type'] . "' 
      AND post_content 
    ";
    wcpt_search__query($field, $item, $query, $keyword_phrase, $keywords, $search_ids);
  }

  if (in_array('excerpt', $filter_info['target'])) {
    $field = 'excerpt';
    $item = null;
    $query = "
      SELECT ID 
      FROM $wpdb->posts 
      WHERE post_type = '" . $filter_info['post_type'] . "' 
      AND post_excerpt 
    ";
    wcpt_search__query($field, $item, $query, $keyword_phrase, $keywords, $search_ids);
  }

  foreach (['sku', 'gtin'] as $field) {
    if (in_array($field, $filter_info['target'])) {
      $item = null;

      $meta_key = $field === 'sku' ? '_sku' : '_global_unique_id';
      $query = "
        SELECT post_id 
        FROM $wpdb->postmeta 
        WHERE meta_key = '$meta_key'
        AND meta_value 
      ";

      // swap the child variation ids with their parent variable ids if varaitions are to be included in the search
      $hook_added = false;
      if (
        (
          $field === 'sku' &&
          !empty($filter_info['include_variation_skus'])
        ) ||
        (
          $field === 'gtin' &&
          !empty($filter_info['include_variation_gtins'])
        )
      ) {
        $hook_added = true;
        add_filter('wcpt_search__query_results', 'wcpt_search__include_variations');
      }

      wcpt_search__query($field, $item, $query, $keyword_phrase, $keywords, $search_ids);

      if ($hook_added) {
        remove_filter('wcpt_search__query_results', 'wcpt_search__include_variations');
      }
    }
  }

  if (in_array('custom_field', $filter_info['target'])) {
    $field = 'custom_field';
    $item = null;

    $query = "
      SELECT post_id 
      FROM $wpdb->postmeta 
      WHERE meta_key %s
      AND meta_value 
    ";

    // default rule items
    if (count($custom_fields__default_rules)) {
      $var = "IN ('" . implode("','", $custom_fields__default_rules) . "')";
      wcpt_search__query($field, null, sprintf($query, $var), $keyword_phrase, $keywords, $search_ids);
    }

    // custom rule items
    if (count($custom_fields__custom_rules)) {
      foreach ($custom_fields__custom_rules as $item) {
        $var = "= '$item'";
        wcpt_search__query($field, $item, sprintf($query, $var), $keyword_phrase, $keywords, $search_ids);
      }
    }

  }

  // custom taxonomy
  foreach ($filter_info['target'] as $target) {
    $target = strtolower(trim($target));
    if ('taxonomy__' === substr($target, 0, 10)) {
      $taxonomy = substr($target, 10);
      $item = null;

      $query = "
        SELECT $wpdb->term_relationships.object_id 
        FROM $wpdb->terms
        INNER JOIN $wpdb->term_taxonomy ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id
        INNER JOIN $wpdb->term_relationships ON $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
        WHERE $wpdb->term_taxonomy.taxonomy = '" . esc_sql($taxonomy) . "' 
        AND name         
      ";
      wcpt_search__query($target, $item, $query, $keyword_phrase, $keywords, $search_ids);
    }
  }

  // matches against any keyword
  $post_ids = wcpt_search__combine(apply_filters('wcpt_search_ids', $search_ids, $filter_info, $keyword_phrase, $keywords), $post__in);

  // restrict to products that match all keywords
  if (
    !empty($filter_info['keyword_match_type']) &&
    $filter_info['keyword_match_type'] === 'all'
  ) {
    $complete_keyword_match_ids = array();

    // Get unique IDs for each keyword
    foreach ($wcpt_search__keyword_product_matches as $keyword => $ids) {
      $wcpt_search__keyword_product_matches[$keyword] = array_unique($ids);
    }

    // Get intersection of all keyword matches
    if (!empty($wcpt_search__keyword_product_matches)) {
      $keyword_match_arrays = array_values($wcpt_search__keyword_product_matches);
      if (!empty($keyword_match_arrays)) {
        $complete_keyword_match_ids = call_user_func_array('array_intersect', $keyword_match_arrays);
      }
    }

    // Maintain $post_ids order
    $post_ids = array_values(array_intersect($post_ids, $complete_keyword_match_ids));

    if (!count($post_ids) > 0) {
      $post_ids = [0];
    }
  }

  return $post_ids;
}

// query and store post ids
function wcpt_search__query($field, $item, $query, $keyword_phrase, $keywords, &$search_ids)
{
  global $wpdb;
  global $wcpt_search__keyword_product_matches;

  if (empty($search_ids[$field])) {
    $search_ids[$field] = array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
    );
  }

  $settings = wcpt_get_settings_data();

  $permitted = array(
    'phrase_like' => true,
    'phrase_exact' => true,
    'keyword_like' => true,
    'keyword_exact' => true,
  );

  if (empty($settings['search'][$field])) { // custom taxonomy
    $settings['search'][$field]['rules'] = array(
      'keyword_exact_enabled' => true,
      'keyword_exact_score' => 40,
      'keyword_like_enabled' => true,
      'keyword_like_score' => 20,
      'phrase_exact_enabled' => true,
      'phrase_exact_score' => 100,
      'phrase_like_enabled' => true,
      'phrase_like_score' => 60,
    );
  }

  $rules = $settings['search'][$field]['rules'];

  if ($item) {
    foreach ($settings['search'][$field]['items'] as $_item) {
      if (
        $_item['item'] == $item &&
        $_item['custom_rules_enabled']
      ) {
        $rules = $_item['rules'];
      }
    }
  }

  foreach ($permitted as $key => &$val) {
    $val = $rules[$key . '_enabled'];
  }

  if (!empty($item)) {
    $search_ids[$field]['items'][$item] = array();
    $location =& $search_ids[$field]['items'][$item];

  } else {
    $location =& $search_ids[$field];

  }

  if (empty($location)) {
    $location = array(
      'phrase_exact' => array(),
      'phrase_like' => array(),
      'keyword_exact' => array(),
      'keyword_like' => array(),
    );
  }

  // phrase exact
  if ($permitted['phrase_exact']) {
    $esc_keyword_phrase = esc_sql($keyword_phrase);
    $post_ids = $wpdb->get_col($query . " ='$esc_keyword_phrase' ");
    $location['phrase_exact'] = $post_ids;

    foreach ($wcpt_search__keyword_product_matches as $_keyword => $post_ids) {
      $wcpt_search__keyword_product_matches[$_keyword] = array_merge($wcpt_search__keyword_product_matches[$_keyword], $post_ids);
    }
  }

  // phrase like
  if ($permitted['phrase_like']) {
    $esc_keyword_phrase = $wpdb->esc_like($keyword_phrase);
    $post_ids = apply_filters('wcpt_search__query_results', $wpdb->get_col($query . " LIKE '%$esc_keyword_phrase%'"));
    $location['phrase_like'] = $post_ids;

    foreach ($wcpt_search__keyword_product_matches as $_keyword => $post_ids) {
      $wcpt_search__keyword_product_matches[$_keyword] = array_merge($wcpt_search__keyword_product_matches[$_keyword], $post_ids);
    }
  }

  foreach ($keywords as $k => $keyword) {
    $esc_keyword = $wpdb->esc_like($keyword);

    // keyword exact
    if ($permitted['keyword_exact']) {
      // Extract the base part of the query (everything before WHERE)
      $query_parts = explode('WHERE', $query, 2);
      $base_query = $query_parts[0];

      // Extract the fixed conditions (everything before the last AND)
      $conditions_parts = explode('AND', $query_parts[1]);
      $fixed_conditions = implode('AND', array_slice($conditions_parts, 0, -1));

      // Build the query
      $exact_query = $base_query .
        "WHERE " . $fixed_conditions .
        "AND (
                " . end($conditions_parts) . " = '$esc_keyword' 
                OR " . end($conditions_parts) . " LIKE '% $esc_keyword %' 
                OR " . end($conditions_parts) . " LIKE '$esc_keyword %' 
                OR " . end($conditions_parts) . " LIKE '% $esc_keyword'
            )";

      $post_ids = apply_filters('wcpt_search__query_results', $wpdb->get_col($exact_query));
      $location['keyword_exact'] = array_merge($location['keyword_exact'], $post_ids);
      $wcpt_search__keyword_product_matches[$keyword] = array_merge($wcpt_search__keyword_product_matches[$keyword], $post_ids);
    }

    // keyword like
    if ($permitted['keyword_like']) {
      // Extract the base part of the query (everything before WHERE)
      $query_parts = explode('WHERE', $query, 2);
      $base_query = $query_parts[0];

      // Extract the fixed conditions (everything before the last AND)
      $conditions_parts = explode('AND', $query_parts[1]);
      $fixed_conditions = implode('AND', array_slice($conditions_parts, 0, -1));

      // Build the query with LIKE
      $like_query = $base_query .
        "WHERE " . $fixed_conditions .
        "AND " . end($conditions_parts) . " LIKE '%$esc_keyword%'";

      $post_ids = apply_filters('wcpt_search__query_results', $wpdb->get_col($like_query));
      $location['keyword_like'] = array_merge($location['keyword_like'], $post_ids);
      $wcpt_search__keyword_product_matches[$keyword] = array_merge($wcpt_search__keyword_product_matches[$keyword], $post_ids);
    }

  }
}

// converts variation ids to parent variable ids
function wcpt_search__include_variations($post_ids)
{
  global $wpdb;

  // helps avoid re-finding variations for same parent ID 
  static $cache = array();

  // Return empty array if no post IDs
  if (empty($post_ids)) {
    return array();
  }

  // gathers variation and parent ids
  $result_ids = array();
  // parent ids that have not been processed yet
  $unprocessed_parent_ids = array();

  // check cache first
  foreach ($post_ids as $post_id) {
    if (isset($cache[$post_id])) {
      $result_ids[] = $cache[$post_id];
    } else {
      $unprocessed_parent_ids[] = (int) $post_id;
    }
  }

  // Query only uncached IDs
  if (!empty($unprocessed_parent_ids)) {
    $sql_query = "SELECT ID, post_parent FROM $wpdb->posts WHERE ID IN (" . implode(',', $unprocessed_parent_ids) . ")";
    $id_relations = $wpdb->get_results($sql_query, ARRAY_A);

    foreach ($id_relations as $relation) {
      $id = (int) $relation['ID'];
      $parent_id = (int) $relation['post_parent'];

      // If it's a variation, use parent ID, otherwise use the ID itself
      $final_id = $parent_id > 0 ? $parent_id : $id;

      $cache[$id] = $final_id;
      $result_ids[] = $final_id;
    }
  }

  return array_unique($result_ids);
}

// combine current search ids into the query post__in 
function wcpt_search__combine($search_ids, $post__in)
{
  // restrict to search results
  if (is_array($search_ids)) {

    $arr = array();
    $settings = wcpt_get_settings_data();
    $search_settings = $settings['search'];

    foreach ($search_ids as $field => $matches) {
      foreach ($matches as $match_type => $ids) {
        if (empty($settings['search'][$field])) { // custom taxonomy
          $rules = array(
            'keyword_exact_enabled' => true,
            'keyword_exact_score' => 40,
            'keyword_like_enabled' => true,
            'keyword_like_score' => 20,
            'phrase_exact_enabled' => true,
            'phrase_exact_score' => 100,
            'phrase_like_enabled' => true,
            'phrase_like_score' => 60,
          );
        } else {
          $rules = $search_settings[$field]['rules'];
        }

        if ($match_type === 'items') {
          foreach ($matches['items'] as $item => $matches) {
            $item_rules = $rules;
            // maybe use custom rules
            foreach ($search_settings[$field]['items'] as $item2) {
              if (
                $item2['item'] === $item &&
                !empty($item2['custom_rules_enabled'])
              ) {
                $item_rules = $item2['rules'];
                break;
              }
            }

            foreach ($matches as $match_type => $ids) {
              foreach ($ids as $id) {
                if (!isset($arr[$id])) {
                  $arr[$id] = 0;
                }
                $arr[$id] += $item_rules[$match_type . '_score'];
              }
            }
          }
        } else {
          foreach ($ids as $id) {
            if (!isset($arr[$id])) {
              $arr[$id] = 0;
            }
            $arr[$id] += $rules[$match_type . '_score'];
          }
        }
      }
    }

    arsort($arr);

    $post_ids = array_keys($arr);

    if (empty($post_ids)) {
      $post__in = array(0);

    } else if (empty($post__in)) {
      $post__in = $post_ids;

    } else {
      $post__in = array_intersect($post_ids, $post__in);

      if (!count($post__in)) {
        // if 1 search instance fails, fail all
        $post__in = array(0);
      }

    }
  }

  return $post__in;
}

// search original config data
$WCPT_SEARCH_DATA = array(
  'stopwords' => "i, me, my, myself, we, our, ours, ourselves, you, your, yours, yourself, yourselves, he, him, his, himself, she, her, hers, herself, it, its, itself, they, them, their, theirs, themselves, what, which, who, whom, this, that, these, those, am, is, are, was, were, be, been, being, have, has, had, having, do, does, did, doing, a, an, the, and, but, if, or, because, as, until, while, of, at, by, for, with, about, against, between, into, through, during, before, after, above, below, to, from, up, down, in, out, on, off, over, under, again, further, then, once, here, there, when, where, why, how, all, any, both, each, few, more, most, other, some, such, no, nor, not, only, own, same, so, than, too, very, s, t, can, will, just, don, should, now",

  'replacements' => '',

  'override_settings' => array(
    'target' => array('title', 'content'),
    'attributes' => array(),
    'custom_fields' => array(),
    'keyword_match_type' => 'all',
  ),

  'relevance_label' => "en_US: Sort by Relevance\r\nfr_FR: Trier par pertinence",

  'title' => array(
    'enabled' => true,
    'rules' => array(
      'phrase_exact_enabled' => true,
      'phrase_exact_score' => 100,

      'phrase_like_enabled' => true,
      'phrase_like_score' => 60,

      'keyword_exact_enabled' => true,
      'keyword_exact_score' => 40,

      'keyword_like_enabled' => true,
      'keyword_like_score' => 20,
    )
  ),
  'sku' => array(
    'enabled' => true,
    'rules' => array(
      'phrase_exact_enabled' => true,
      'phrase_exact_score' => 100,

      'phrase_like_enabled' => true,
      'phrase_like_score' => 60,

      'keyword_exact_enabled' => true,
      'keyword_exact_score' => 40,

      'keyword_like_enabled' => true,
      'keyword_like_score' => 20,
    )
  ),
  'category' => array(
    'enabled' => true,
    'rules' => array(
      'phrase_exact_enabled' => true,
      'phrase_exact_score' => 100,

      'phrase_like_enabled' => true,
      'phrase_like_score' => 60,

      'keyword_exact_enabled' => true,
      'keyword_exact_score' => 40,

      'keyword_like_enabled' => true,
      'keyword_like_score' => 20,
    )
  ),
  'attribute' => array(
    'enabled' => true,
    'rules' => array(
      'phrase_exact_enabled' => true,
      'phrase_exact_score' => 100,

      'phrase_like_enabled' => true,
      'phrase_like_score' => 60,

      'keyword_exact_enabled' => true,
      'keyword_exact_score' => 40,

      'keyword_like_enabled' => true,
      'keyword_like_score' => 20,
    ),
    'items' => array()
  ),
  'tag' => array(
    'enabled' => true,
    'rules' => array(
      'phrase_exact_enabled' => true,
      'phrase_exact_score' => 100,

      'phrase_like_enabled' => true,
      'phrase_like_score' => 60,

      'keyword_exact_enabled' => true,
      'keyword_exact_score' => 40,

      'keyword_like_enabled' => true,
      'keyword_like_score' => 20,
    )
  ),
  'content' => array(
    'enabled' => true,
    'rules' => array(
      'phrase_exact_enabled' => true,
      'phrase_exact_score' => 100,

      'phrase_like_enabled' => true,
      'phrase_like_score' => 60,

      'keyword_exact_enabled' => true,
      'keyword_exact_score' => 40,

      'keyword_like_enabled' => true,
      'keyword_like_score' => 20,
    )
  ),
  'excerpt' => array(
    'enabled' => true,
    'rules' => array(
      'phrase_exact_enabled' => true,
      'phrase_exact_score' => 100,

      'phrase_like_enabled' => true,
      'phrase_like_score' => 60,

      'keyword_exact_enabled' => true,
      'keyword_exact_score' => 40,

      'keyword_like_enabled' => true,
      'keyword_like_score' => 20,
    )
  ),
  'custom_field' => array(
    'enabled' => true,
    'rules' => array(
      'phrase_exact_enabled' => true,
      'phrase_exact_score' => 100,

      'phrase_like_enabled' => true,
      'phrase_like_score' => 60,

      'keyword_exact_enabled' => true,
      'keyword_exact_score' => 40,

      'keyword_like_enabled' => true,
      'keyword_like_score' => 20,
    ),
    'items' => array()
  ),
);


// apply custom search orderby
add_filter('wcpt_before_apply_user_filters', 'wcpt_search_orderby');
function wcpt_search_orderby()
{
  $table_data = wcpt_get_table_data();
  $table_id = $table_data['id'];
  $query = $table_data['query'];
  $sc_attrs = $query['sc_attrs'];

  // no search orderby set by user
  if (empty($sc_attrs['search_orderby'])) {
    return;
  }

  // visitor has not searched or selected some other orderby criteria
  $orderby_param_key = $table_id . '_orderby';
  $orderby_param_value = isset($_GET[$orderby_param_key]) ? $_GET[$orderby_param_key] : false;
  if (
    empty($orderby_param_value) ||
    $orderby_param_value !== 'search_orderby'
  ) {
    return;
  }

  $default_params = wcpt_get_nav_filter('orderby');

  if (is_numeric($sc_attrs['search_orderby'])) { // user entered an op index for orderby
    $sort_by = wcpt_get_nav_elms_ref('sort_by', $table_data);
    $op_index = (int) $sc_attrs['search_orderby'] - 1;

    if (
      gettype($sort_by) === 'array' &&
      !empty($sort_by[0]['dropdown_options'][$op_index])
    ) {
      $sort_by_op = $sort_by[0]['dropdown_options'][$op_index];
      $orderby = $sort_by_op['orderby'];
      $order = $sort_by_op['order'];
      $meta_key = $sort_by_op['meta_key'];
      $attribute = isset($sort_by_op['orderby_attribute']) ? $sort_by_op['orderby_attribute'] : '';
    }

  } else { // user entered an orderby val 
    $orderby = strtolower($sc_attrs['search_orderby']);
    $order = !empty($sc_attrs['search_order']) ? strtolower($sc_attrs['search_order']) : $default_params['order'];
    $meta_key = !empty($sc_attrs['search_meta_key']) ? $sc_attrs['search_meta_key'] : $default_params['meta_key'];
    $attribute = isset($sc_attrs['search_orderby_attribute']) ? $sc_attrs['search_orderby_attribute'] : '';
  }

  if (
    $orderby === 'price' &&
    $order === 'desc'
  ) {
    $orderby = 'price-desc';
    $order = 'DESC';
  }

  if ($orderby === 'initial') {
    $search_orderby_filter = array(
      'filter' => 'orderby',
      'orderby' => !empty($query['orderby']) ? $query['orderby'] : '',
      'order' => $query['order'] ? $query['order'] : '',
      'meta_key' => $query['meta_key'] ? $query['meta_key'] : '',
      'orderby_attribute' => isset($query['orderby_attribute']) ? $query['orderby_attribute'] : '',
    );

  } else {
    $search_orderby_filter = array(
      'filter' => 'orderby',
      'orderby' => $orderby,
      'order' => $order,
      'meta_key' => $meta_key,
      'orderby_attribute' => $attribute,
    );

  }

  wcpt_update_user_filters($search_orderby_filter, true);
  unset($_GET[$table_id . '_orderby']);
}
