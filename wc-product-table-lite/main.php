<?php
/*
 * Plugin Name: Product Table and List Builder for WooCommerce Lite
 * Plugin URI: https://wcproducttable.com/
 * Description: Display your WooCommerce products in beautiful table and list layouts that are mobile responsive and fully customizable.
 * Author: WC Product Table
 * Author URI: https://profiles.wordpress.org/wcproducttable/
 * Version: 4.6.2
 *
 * WC requires at least: 3.4.4
 * WC tested up to: 10.3.6
 *
 * Text Domain: wc-product-table-pro
 * Domain Path: /languages/
 *
 */

if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly
}

define('WCPT_DEV', false);

define('WCPT_VERSION', '4.6.2');
define('WCPT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WCPT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WCPT_TEXT_DOMAIN', 'wc-product-table-pro');

require_once(WCPT_PLUGIN_PATH . 'update.php');

// suggest to deactivate Lite if PRO is installed
add_action('admin_notices', 'wcpt_suggest_uninstall_lite');
function wcpt_suggest_uninstall_lite()
{
  if (
    FALSE !== strpos(dirname(__FILE__), 'wc-product-table-lite') && // if this is lite...
    file_exists(WP_PLUGIN_DIR . '/wc-product-table-pro/main.php') // ...and pro is installed
  ) { // ...suggest deactivating this
    $class = 'notice notice-warning';
    $message = 'Please deactivate the \'Product Table and List Builder for WooCommerce Lite\' plugin before activating the \'Product Table and List Builder for WooCommerce PRO\' plugin to avoid conflict errors.';
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
  }
}

// Show admin notice for plugin name change uptill v4.8
add_action('admin_notices', 'wcpt_name_change_notice');
function wcpt_name_change_notice()
{
  // Only show notice for versions below 4.8.0
  if (version_compare(WCPT_VERSION, '4.8.0', '>=')) {
    return;
  }

  // Check if notice was already dismissed
  if (get_option('wcpt_name_change_notice_dismissed')) {
    return;
  }

  $edition = FALSE !== strpos(dirname(__FILE__), 'wc-product-table-lite') ? 'LITE' : 'PRO';

  ?>
  <div class="notice notice-info is-dismissible" id="wcpt-name-change-notice">
    <p>
      <?php _e('The plugin "WooCommerce Product Table ' . $edition . '" has been renamed to "Product Table and List Builder for WooCommerce ' . $edition . '" by the plugin author. Core functionality remains the same.', 'wc-product-table-pro'); ?>
    </p>
  </div>

  <script>
    jQuery(document).ready(function ($) {
      $(document).on('click', '#wcpt-name-change-notice .notice-dismiss', function () {
        $.ajax({
          url: ajaxurl,
          data: {
            action: 'wcpt_dismiss_name_change_notice'
          }
        });
      });
    });
  </script>
  <?php
}

// Ajax handler to dismiss the notice
add_action('wp_ajax_wcpt_dismiss_name_change_notice', 'wcpt_dismiss_name_change_notice');
function wcpt_dismiss_name_change_notice()
{
  update_option('wcpt_name_change_notice_dismissed', true);
  wp_die();
}

// get / cache global settings 
function wcpt_get_settings_data($ctx = 'view')
{
  wcpt_ensure_default_settings();

  if ($ctx !== 'edit') {
    $ctx = 'view';
  }

  global $wcpt_settings; // assumes $ctx remains same for script

  if (
    empty($wcpt_settings) ||
    empty($wcpt_settings['ctx']) ||
    $wcpt_settings['ctx'] !== $ctx
  ) {
    if (!$wcpt_settings = wcpt_update_settings_data()) {
      $table_data = json_decode(stripslashes(get_option('wcpt_settings', '')), true);
      $wcpt_settings = apply_filters('wcpt_settings', $table_data, $ctx);
    }

    $wcpt_settings['ctx'] = $ctx;
  }

  return $wcpt_settings;
}

/* upload initial plugin data */
function wcpt_ensure_default_settings()
{
  if (!get_option('wcpt_settings')) {
    update_option(
      'wcpt_settings',
      addslashes(
        json_encode(
          array(
            'version' => WCPT_VERSION,
            'timestamp' => time(),

            'pro_license' => array(
              'key' => '',
            ),

            'archive_override' => array(
              'default' => '',
              'shop' => 'default',
              'search' => '',

              'category' => array(
                'default' => 'default',
                'other_rules' => array(
                  array(
                    'category' => array(),
                    'table_id' => '',
                  ),
                ),
              ),

              'attribute' => array(
                'default' => 'default',
                'other_rules' => array(
                  array(
                    'attribute' => array(),
                    'table_id' => '',
                  ),
                ),
              ),

              'tag' => array(
                'default' => 'default',
                'other_rules' => array(
                  array(
                    'tag' => array(),
                    'table_id' => '',
                  ),
                ),
              ),

            ),

            'cart_widget' => array(
              'toggle' => 'enabled',
              'r_toggle' => 'enabled',
              'link' => 'cart',
              'cost_source' => 'subtotal',
              'labels' => array(
                'item' => "en_US: Item\r\nfr_FR: Article",
                'items' => "en_US: Items\r\nfr_FR: Articles",
                'view_cart' => "en_US: View Cart\r\nfr_FR: Voir le panier",
                'extra_charges' => "en_US: Extra charges may apply\r\nfr_FR: Les taxes peuvent s'appliquer",
              ),
              'style' => array(
                'background-color' => '',
                'border-color' => '',
                'bottom' => '',
              ),
            ),

            'modals' => array(
              'labels' => array(
                'filters' => "en_US: Filters\r\nfr_FR: Filtres",
                'sort' => "en_US: Sort results\r\nfr_FR: Trier les résultats",
                'reset' => "en_US: Reset\r\nfr_FR: Rafraîchir",
                'apply' => "en_US: Apply\r\nfr_FR: Appliquer",
              ),
            ),

            'no_results' => array(
              'label' => 'No results found. [link]Clear filters[/link] and try again?',
            ),

            'search' => $GLOBALS['WCPT_SEARCH_DATA'],
            'checkbox_trigger' => $GLOBALS['WCPT_CHECKBOX_TRIGGER_DATA'],
          )
        )
      ),
      false
    );
  }

}

$WCPT_CHECKBOX_TRIGGER_DATA = array(
  'toggle' => 'enabled',
  'r_toggle' => 'enabled',
  'link' => '',
  'labels' => array(
    'label' => "en_US: Add selected ([n]) to cart\r\nfr_FR: Ajouter des produits ([n]) au panier",
  ),
  'style' => array(
    'background-color' => '',
    'border-color' => '',
    'color' => '',
  ),
);

/* load plugin textdomain. */
add_action('plugins_loaded', 'wcpt_load_textdomain');
function wcpt_load_textdomain()
{
  load_plugin_textdomain(WCPT_TEXT_DOMAIN, false, basename(dirname(__FILE__)) . '/languages');
}

/* register wc-product-table custom post type */
add_action('init', 'wcpt_register_post_type');
function wcpt_register_post_type()
{
  $current_user = wp_get_current_user();
  $current_user_roles = $current_user->roles;
  $is_admin = in_array('administrator', $current_user_roles);

  register_post_type(
    'wc_product_table',
    array(
      'labels' => array(
        'name' => __('WooCommerce Product Table ' . (defined('WCPT_PRO') ? 'PRO' : 'Lite'), 'wc-product-table'),
        'singular_name' => __('Product Table', 'wc-product-table'),
        'menu_name' => __('Product Tables', 'wc-product-table'),
        'add_new' => __('Add New Table', 'wc-product-table'),
        'add_new_item' => __('Add New Table', 'wc-product-table'),
        'new_item' => __('New Product Table', 'wc-product-table'),
        'edit_item' => __('Edit Product Table', 'wc-product-table'),
        'view_item' => __('View Product Table', 'wc-product-table'),
        'all_items' => __('All Product Tables', 'wc-product-table'),
        'search_items' => __('Search Product Tables', 'wc-product-table'),
        'not_found' => __('No product tables found.', 'wc-product-table'),
      ),
      'description' => __('Easily display your WooCommerce products in responsive tables.', 'wc-product-table'),
      'public' => true,
      'has_archive' => false,
      'menu_icon' => 'dashicons-editor-justify',
      'rewrite' => array('slug' => 'product-table'), // @TODO: change to 'wc-product-table'
      'capability_type' => 'wc_product_table',
      'map_meta_cap' => true,
      'supports' => array(),
      'hierarchical' => false,
      'show_in_nav_menus' => true,
      'show_in_admin_bar' => true,
      'publicly_queryable' => true,
      'exclude_from_search' => true,
      'can_export' => true,
      'show_in_rest' => true,
    )
  );

  $admin = get_role('administrator');

  $admin->add_cap('create_wc_product_tables');
  $admin->add_cap('edit_wc_product_table');
  $admin->add_cap('edit_wc_product_tables');
  $admin->add_cap('edit_others_wc_product_tables');
  $admin->add_cap('edit_published_wc_product_tables');
  $admin->add_cap('publish_wc_product_tables');
  $admin->add_cap('read_wc_product_table');
  $admin->add_cap('read_private_wc_product_tables');
  $admin->add_cap('delete_wc_product_table');
  $admin->add_cap('delete_wc_product_tables');
  $admin->add_cap('delete_published_wc_product_tables');
  $admin->add_cap('delete_others_wc_product_tables');
}

/* flush rewrites upon activation */
register_activation_hook(__FILE__, 'wcpt_activate');
function wcpt_activate()
{
  wcpt_register_post_type();
  flush_rewrite_rules();
}

/* redirect to table editor */
add_action('plugins_loaded', 'wcpt_redirect_to_table_editor');
function wcpt_redirect_to_table_editor()
{
  global $pagenow;

  // edit
  if ($pagenow == 'post.php' && isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] == 'edit') {
    $post_id = (int) $_GET['post'];
    $post = get_post_type($post_id);
    if ($post === 'wc_product_table') {
      wp_redirect(admin_url('/edit.php?post_type=wc_product_table&page=wcpt-edit&post_id=' . $post_id));
      exit;
    }
  }

  // add new
  if ($pagenow == 'post-new.php' && isset($_GET['post_type']) && $_GET['post_type'] == 'wc_product_table') {
    wp_redirect(admin_url('/edit.php?post_type=wc_product_table&page=wcpt-edit'));
    exit;
  }

}

/* plugin's table editor and global settings page */
define('WCPT_CAP', 'edit_wc_product_tables');
add_action('admin_menu', 'wcpt_hook_menu_pages');
function wcpt_hook_menu_pages()
{
  if (class_exists('WooCommerce')) { // check if WC is installed
    add_action('admin_enqueue_scripts', 'wcpt_enqueue_admin_scripts');
  }

  // editor page
  add_submenu_page('edit.php?post_type=wc_product_table', 'WC Product Table', 'Add New Table', WCPT_CAP, 'wcpt-edit', 'wcpt_editor_page');

  // global settings page
  add_submenu_page('edit.php?post_type=wc_product_table', 'WCPT Settings', 'Settings', WCPT_CAP, 'wcpt-settings', 'wcpt_settings_page');

  // addons page
  add_submenu_page('edit.php?post_type=wc_product_table', 'WCPT Addons', 'Addons', WCPT_CAP, 'wcpt-addons', 'wcpt_addons_page');

}

/* highlight the WC Product Table menu item when editing an existing wcpt table post */
add_action('admin_menu', 'wcpt_correct_menu_highlight');
function wcpt_correct_menu_highlight()
{
  if (
    isset($_GET['post_type']) &&
    $_GET['post_type'] === 'wc_product_table' &&
    isset($_GET['page']) &&
    $_GET['page'] === 'wcpt-edit' &&
    !empty($_GET['post_id'])
  ) {
    global $submenu_file;
    $submenu_file = "edit.php?post_type=wc_product_table";
  }
}

/* create table editor page */
function wcpt_editor_page()
{
  if (!class_exists('WooCommerce'))
    return;

  if (empty($_GET['post_id']) && !empty($_GET['wcpt_preset'])) {
    $post_id = wp_insert_post(array('post_type' => 'wc_product_table'));
    $_GET['post_id'] = $post_id;
    wp_redirect(admin_url('edit.php?post_type=wc_product_table&page=wcpt-edit&wcpt_preset=' . $_GET['wcpt_preset'] . '&post_id=' . $post_id));
    exit;
  }

  if (!empty($_GET['post_id'])) {
    $post_id = (int) $_GET['post_id'];

    if (get_post_meta($post_id, 'wcpt_data', true)) {
      // previously saved table data
      $GLOBALS['wcpt_table_data'] = wcpt_get_table_data($post_id, 'edit');

    } else {
      // starter data
      $table_data = array(
        'query' => array(
          'category' => array(),
          'orderby' => 'price',
          'order' => 'ASC',
          'limit' => 10,
          'paginate' => true,
          'visibility' => 'visible',
        ),
        'columns' => array(
          'laptop' => array(),
          'tablet' => array(),
          'phone' => array(),
        ),
        'navigation' => array(
          'laptop' => array(
            'header' => array(
              'rows' => array(
                array(
                  'columns_enabled' => 'left-right',
                  'columns' => array(
                    'left' => array(
                      'template' => '',
                    ),
                    'right' => array(
                      'template' => '',
                    ),
                    'center' => array(
                      'template' => '',
                    ),
                  ),
                ),
              ),
            ),
            'left_sidebar' => false,
          ),
          'tablet' => false,
          'phone' => false,
        ),
        'navigation_settings' => array(
          'disable_ajax' => true,
          'autoScroll' => ['laptop', 'tablet', 'phone'],
        ),
        'style' => array(
          'css' => '',
          'laptop' => array(),
          'tablet' => array(
            'inherit_laptop_style' => true,
          ),
          'phone' => array(
            'inherit_tablet_style' => false,
          ),
          'navigation' => array(),
        ),
        'elements' => array(
          'column' => array(),
          'navigation' => array(),
        ),
        'version' => WCPT_VERSION,
        'timestamp' => time(),
      );

      $GLOBALS['wcpt_table_data'] = apply_filters('wcpt_data', $table_data, 'edit');
    }

    ?>
    <script>
      var wcpt = {
        model: {},
        view: {},
        controller: {},
        data: <?php echo json_encode($GLOBALS['wcpt_table_data']); ?>,
      };
    </script>
    <?php
  }
  // editor template
  require(WCPT_PLUGIN_PATH . 'editor/editor.php');
}

/* esc data fields */
function wcpt_esc_attr(&$info)
{
  foreach ($info as $key => &$val) {
    if (is_string($val) && !in_array($key, array("heading", "css"))) {
      $val = esc_attr($val);
    } else if (is_array($val)) {
      wcpt_esc_attr($val);
    }
  }
}

// save table data over ajax
add_action('wp_ajax_wcpt_save_table_settings', 'wcpt_save_table_settings_over_ajax');
function wcpt_save_table_settings_over_ajax()
{

  // check for errors first
  $errors = array();

  // error: no table settings
  if (empty($_POST['wcpt_data'])) {
    $errors[] = 'Table settings were not received.';
  }

  // error: no post ID
  if (empty($_POST['wcpt_post_id'])) {
    $errors[] = 'Post ID was not received.';

    // error: unathorized user
  } else if (!current_user_can('edit_wc_product_table', (int) $_POST['wcpt_post_id'])) {
    $user = wp_get_current_user();
    $errors[] = 'User (' . implode(", ", $user->roles) . ') is not authorized to edit product tables.';
  }

  // error: no nonce
  if (empty($_POST['wcpt_nonce'])) {
    $errors[] = 'Nonce string was not received.';

    // error: wrong nonce
  } else if (!wp_verify_nonce($_POST['wcpt_nonce'], 'wcpt')) {
    $errors[] = 'Nonce verification failed.';

  }

  if (count($errors)) { // failure
    $error_message = 'WCPT error: Table data was not saved because:';
    foreach ($errors as $i => $error) {
      $error_message .= ' (' . ($i + 1) . ') ' . $error;
    }

    $remedy = ' Please contact plugin author at https://wcproducttable.com/support/ for prompt assistance with this issue!';

    echo wp_kses_post($error_message . $remedy);

  } else { // success
    $post_id = (int) $_POST['wcpt_post_id'];
    $table_data = json_decode(stripslashes($_POST['wcpt_data']), TRUE);
    $table_data['timestamp'] = time();
    update_post_meta($post_id, 'wcpt_data', addslashes(json_encode($table_data)));
    $my_post = array(
      'ID' => $post_id,
      'post_title' => (string) $_POST['wcpt_title'],
      'post_status' => 'publish',
    );
    wp_update_post($my_post);

    echo "WCPT success: Table data was saved.";

  }

  wp_die();
}

// save table data
function wcpt_save_table_settings($table_data)
{
  if (is_array($table_data)) {
    $post_id = $table_data['id'];
    $table_data['timestamp'] = time();
    update_post_meta($post_id, 'wcpt_data', addslashes(json_encode($table_data)));
  }
}

/* create plugin settings page */
function wcpt_settings_page()
{
  if (!class_exists('WooCommerce')) {
    return;
  }

  if (!empty($_GET['wcpt_reset_global_settings'])) {
    if (
      empty($_GET['_wp_nonce']) ||
      !wp_verify_nonce($_GET['_wp_nonce'], 'wcpt_reset_global_settings')
    ) {
      die();
    }

    do_action('wcpt_reset_global_settings');
    delete_option('wcpt_settings');
    wp_safe_redirect(admin_url('edit.php?post_type=wc_product_table&page=wcpt-settings'));
  }

  $settings = wcpt_get_settings_data('edit');
  ?>
  <script>
    var wcpt = {
      model: {},
      view: {},
      controller: {},
      data: <?php echo json_encode($settings); ?>,
    };
  </script>
  <?php
  // settings page template
  require(WCPT_PLUGIN_PATH . 'editor/settings.php');
}

add_action('wp_ajax_wcpt_save_global_settings', 'wcpt_save_global_settings');
function wcpt_save_global_settings()
{
  if (
    !empty($_POST['wcpt_data']) &&
    wp_verify_nonce($_POST['wcpt_nonce'], 'wcpt')
  ) {
    $settings = json_decode(stripslashes($_POST['wcpt_data']), true);
    $settings['timestamp'] = time();
    $settings = addslashes(json_encode($settings));

    update_option('wcpt_settings', apply_filters('wcpt_global_settings', $settings), 'no');
    echo "WCPT success: Global settings saved.";
  }
  wp_die();
}

/* create addons page */
function wcpt_addons_page()
{
  if (!class_exists('WooCommerce')) {
    return;
  }

  // settings page template
  require(WCPT_PLUGIN_PATH . 'addons/addons.php');
}

/* display error if minimum specifications to run WCPT are not met */
function wcpt_min_spec_warning()
{
  $errors = false;

  // check if php version is compatible
  if (version_compare(PHP_VERSION, '5.4.0') < 0) {
    $errors = true;
    ?>
    <div class="notice notice-error wcpt-needs-woocommerce">
      <p>
        <?php esc_html_e('WooCommerce Product Table requires at least PHP 5.4.0. Please request you webhost to update your PHP version or run the plugin on another server to avoid incompatibility issues and unexpected behaviour.', 'wc-product-table'); ?>
      </p>
    </div>
    <?php
  }

  // check if wordpress version is compatible
  if (
    version_compare($GLOBALS['wp_version'], '4.9.0') < 0
  ) {
    $errors = true;
    ?>
    <div class="notice notice-error wcpt-needs-woocommerce">
      <p>
        <?php esc_html_e('WooCommerce Product Table requires at least WordPress 4.9.0. Please update your WordPress version to avoid incompatibility issues and unexpected behaviour.', 'wc-product-table'); ?>
      </p>
    </div>
    <?php
  }

  // check if woocommerce is installed
  if (!class_exists('WooCommerce')) {
    $errors = true;
    ?>
    <div class="notice notice-error wcpt-needs-woocommerce">
      <p>
        <?php esc_html_e('WooCommerce Product Table needs the WooCommerce plugin to be installed and activated on your site!', 'wc-product-table'); ?>
        <a href="<?php echo esc_url(get_admin_url(false, "/plugin-install.php?s=woocommerce&tab=search&type=term")); ?>"
          target="_blank">
          <?php esc_html_e('Install now?', 'wc-product-table') ?>
        </a>
      </p>
    </div>
    <?php
  }

  // check if woocommerce version is compatible
  $wc_version_compat = true;
  if (class_exists('WooCommerce')) {
    $wc_info = get_plugin_data(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php');
  }
  if (
    class_exists('WooCommerce') &&
    version_compare($wc_info['Version'], '3.4.4') < 0
  ) {
    $errors = true;
    $wc_version_compat = false;
    ?>
    <div class="notice notice-error wcpt-needs-woocommerce">
      <p>
        <?php esc_html_e('WooCommerce Product Table requires at least WooCommerce 3.4.4. Please update your WooCommerce version to avoid incompatibility issues and unexpected behaviour.', 'wc-product-table'); ?>
      </p>
    </div>
    <?php
  }

  // check if woocommerce products exist version is compatible
  if (
    class_exists('WooCommerce') &&
    $wc_version_compat &&
    !empty($_GET['post_type']) &&
    $_GET['post_type'] === 'wc_product_table'
  ) {
    $query = new WP_Query(
      array(
        'post_type' => 'product',
        'posts_per_page' => 1,
        'post_status' => 'publish',
      )
    );

    if (!$query->found_posts) {
      ?>
      <div class="notice notice-error wcpt-needs-woocommerce">
        <p>
          <?php esc_html_e('WooCommerce Product Table (WCPT) could not find a single \'published\' WooCommerce product on your site! WCPT cannot dispaly any products in tables if you do not have any published products on your site. See:', 'wc-product-table'); ?>
          <a href="https://docs.woocommerce.com/document/managing-products/" target="_blank">
            <?php esc_html_e('How to add WooCommerce products', 'wc-product-table') ?>
          </a>
        </p>
      </div>
      <?php
    }

  }

  ?>

  <?php
  if (!$errors)
    return;
  ?>
  <style media="screen">
    .wp-admin.post-type-wcpt #posts-filter,
    .wp-admin.post-type-wcpt .subsubsub,
    #menu-posts-wcpt .wp-submenu,
    #menu-posts-wcpt:after {
      display: none;
    }

    .wp-admin.post-type-wcpt .wcpt-needs-woocommerce {
      margin-top: 10px;
    }

    .wp-admin.post-type-wcpt .wcpt-needs-woocommerce p {
      font-size: 18px;
    }

    .plugin-card-woocommerce {
      border: 4px solid #03A9F4;
      animation: wcpt-pulse 1s infinite;
    }

    .plugin-card-woocommerce:hover {
      animation: none;
    }

    @-webkit-keyframes wcpt-pulse {
      0% {
        -webkit-box-shadow: 0 0 0 0 rgba(3, 169, 244, 1);
      }

      70% {
        -webkit-box-shadow: 0 0 0 15px rgba(3, 169, 244, 0);
      }

      100% {
        -webkit-box-shadow: 0 0 0 0 rgba(3, 169, 244, 0);
      }
    }

    @keyframes wcpt-pulse {
      0% {
        -moz-box-shadow: 0 0 0 0 rgba(3, 169, 244, 1);
        box-shadow: 0 0 0 0 rgba(3, 169, 244, 1);
      }

      70% {
        -moz-box-shadow: 0 0 0 15px rgba(3, 169, 244, 0);
        box-shadow: 0 0 0 15px rgba(3, 169, 244, 0);
      }

      100% {
        -moz-box-shadow: 0 0 0 0 rgba(3, 169, 244, 0);
        box-shadow: 0 0 0 0 rgba(3, 169, 244, 0);
      }
    }
  </style>
  <?php
}
add_action('admin_notices', 'wcpt_min_spec_warning');

/* back end scripts */
add_action('admin_enqueue_scripts', 'wcpt_enqueue_admin_scripts');
function wcpt_enqueue_admin_scripts()
{
  if (!isset($_GET['page']) || !in_array($_GET['page'], array('wcpt-edit', 'wcpt-settings')))
    return;

  // Google font: Ubuntu
  wp_enqueue_style('Ubuntu', 'https://fonts.googleapis.com/css?family=Ubuntu:300,400,500,600');

  // CSS
  // -- editor
  wp_enqueue_style('wcpt-editor', plugin_dir_url(__FILE__) . 'editor/assets/css/editor.css', null, WCPT_VERSION);

  // -- spectrum
  wp_enqueue_style('spectrum', plugin_dir_url(__FILE__) . 'editor/assets/css/spectrum.min.css', null, WCPT_VERSION);

  // -- block editor
  wp_enqueue_style('wcpt-block-editor', plugin_dir_url(__FILE__) . 'editor/partials/element-editor/block-editor/block-editor.css', null, WCPT_VERSION);

  // -- tabs
  wp_enqueue_style('wcpt-tabs', plugin_dir_url(__FILE__) . 'editor/partials/element-editor/tabs/tabs.css', null, WCPT_VERSION);

  // -- element editor
  wp_enqueue_style('wcpt-element-editor', plugin_dir_url(__FILE__) . 'editor/partials/element-editor/element-editor.css', null, WCPT_VERSION);

  // -- select2
  wp_enqueue_style('wcpt-select2', plugin_dir_url(__FILE__) . 'editor/assets/css/select2.css');

  // -- dominator - react apps
  wp_enqueue_style('wcpt-dominator-react-apps', plugin_dir_url(__FILE__) . 'editor/react/css.css');


  // JS
  // -- dominator
  wp_enqueue_script('wcpt-dominator', plugin_dir_url(__FILE__) . 'editor/assets/js/dominator_ui.js', array('jquery'), null, WCPT_VERSION);

  // -- dominator - react apps
  wp_enqueue_script('wcpt-dominator-react-apps', plugin_dir_url(__FILE__) . 'editor/react/js.js', array('jquery'), null, WCPT_VERSION);

  // -- util
  wp_enqueue_script('wp-util');

  // -- spectrum
  wp_enqueue_script('spectrum', plugin_dir_url(__FILE__) . 'editor/assets/js/spectrum.min.js', array('jquery'), null, false);

  // -- wp.media
  wp_enqueue_media();

  // -- block editor
  wp_enqueue_script('wcpt-block-editor', plugin_dir_url(__FILE__) . 'editor/partials/element-editor/block-editor/block-editor.js', array('jquery'), WCPT_VERSION, true);
  wp_enqueue_script('wcpt-block-editor-model', plugin_dir_url(__FILE__) . 'editor/partials/element-editor/block-editor/block-editor-model.js', array('jquery', 'wcpt-block-editor'), WCPT_VERSION, true);
  wp_enqueue_script('wcpt-block-editor-view', plugin_dir_url(__FILE__) . 'editor/partials/element-editor/block-editor/block-editor-view.js', array('jquery', 'wcpt-block-editor'), WCPT_VERSION, true);
  wp_enqueue_script('wcpt-block-editor-controller', plugin_dir_url(__FILE__) . 'editor/partials/element-editor/block-editor/block-editor-controller.js', array('jquery', 'wcpt-block-editor'), WCPT_VERSION, true);

  // -- tabs
  wp_enqueue_script('wcpt-tabs', plugin_dir_url(__FILE__) . 'editor/partials/element-editor/tabs/tabs.js', array('jquery'), WCPT_VERSION, true);

  // -- element editor
  wp_enqueue_script('wcpt-element-editor', plugin_dir_url(__FILE__) . 'editor/partials/element-editor/element-editor.js', array('jquery', 'wcpt-dominator'), WCPT_VERSION, true);

  // -- controller
  wp_enqueue_script('wcpt-controller', plugin_dir_url(__FILE__) . 'editor/assets/js/controller.js', array('jquery', 'wcpt-dominator'), WCPT_VERSION, true);

  // -- version
  wp_add_inline_script('wcpt-controller', 'var wcpt_version = "' . WCPT_VERSION . '";', 'after');

  // -- feedback anim
  wp_enqueue_script('wcpt-feedback-anim', plugin_dir_url(__FILE__) . 'editor/assets/js/feedback_anim.js', array('wcpt-controller'), WCPT_VERSION, true);

  // -- select2
  wp_enqueue_script('wcpt-select2', plugin_dir_url(__FILE__) . 'editor/assets/js/select2.min.js');
  wp_dequeue_script('gmwqp_select2_js'); // conflict fix

  // -- jquery ui
  wp_enqueue_script('jquery-ui-sortable');

}

add_action('admin_print_scripts', 'wcpt_admin_print_scripts');
function wcpt_admin_print_scripts()
{
  ?>
  <script>var wcpt_icons = "<?php echo esc_url(WCPT_PLUGIN_URL . 'assets/feather/'); ?>"; </script>
  <style media="screen">
    #menu-posts-wc_product_table .wp-submenu li:nth-child(3) {
      display: none;
    }
  </style>
  <?php
}

/* front end scripts */
add_action('wp_enqueue_scripts', 'wcpt_enqueue_scripts');
function wcpt_enqueue_scripts()
{

  if (
    defined('WCPT_DEV') &&
    WCPT_DEV
  ) {
    $min = '';
  } else {
    $min = '.min';
  }

  if (!class_exists('WooCommerce')) {
    return;
  }

  // antiscroll
  wp_enqueue_script('antiscroll', plugin_dir_url(__FILE__) . 'assets/antiscroll/js.min.js', 'jquery', WCPT_VERSION, true);
  wp_enqueue_style('antiscroll', plugin_dir_url(__FILE__) . 'assets/antiscroll/css.min.css', false, WCPT_VERSION);

  // freeze table
  if (apply_filters('wcpt_use_legacy_freeze_table', false)) {
    $freeze_table_folder = 'freeze_table';
  } else {
    $freeze_table_folder = 'freeze_table_v2';
  }

  wp_enqueue_script('freeze_table', plugin_dir_url(__FILE__) . 'assets/' . $freeze_table_folder . '/js' . $min . '.js', array('jquery', 'antiscroll'), WCPT_VERSION, true);
  include(WCPT_PLUGIN_PATH . 'assets/' . $freeze_table_folder . '/tpl.html');
  wp_enqueue_style('freeze_table', plugin_dir_url(__FILE__) . 'assets/' . $freeze_table_folder . '/css' . $min . '.css', false, WCPT_VERSION);

  // photoswipe
  wp_enqueue_script(
    'wc-photoswipe',
    plugin_dir_url(WC_PLUGIN_FILE) . 'assets/js/photoswipe/photoswipe.min.js',
    false,
    WCPT_VERSION,
    true
  );

  wp_enqueue_script(
    'wc-photoswipe-ui-default',
    plugin_dir_url(WC_PLUGIN_FILE) . 'assets/js/photoswipe/photoswipe-ui-default.min.js',
    array('wc-photoswipe'),
    WCPT_VERSION,
    true
  );

  wp_enqueue_style(
    'wc-photoswipe',
    plugin_dir_url(WC_PLUGIN_FILE) . 'assets/css/photoswipe/photoswipe.min.css',
    false,
    WCPT_VERSION
  );

  wp_enqueue_style(
    'wc-photoswipe-default-skin',
    plugin_dir_url(WC_PLUGIN_FILE) . 'assets/css/photoswipe/default-skin/default-skin.min.css',
    false,
    WCPT_VERSION
  );

  add_action('wp_footer', 'wcpt_woocommerce_photoswipe');

  // multirange
  wp_enqueue_script('multirange', plugin_dir_url(__FILE__) . 'assets/multirange/js' . $min . '.js', 'jquery', WCPT_VERSION, true);
  wp_enqueue_style('multirange', plugin_dir_url(__FILE__) . 'assets/multirange/css' . $min . '.css', false, WCPT_VERSION);

  // WC measurement price calculator -- script
  if (
    class_exists('WC_Measurement_Price_Calculator') &&
    defined('WCPT_PRO')
  ) {
    // custom script
    wp_enqueue_script('wcpt-wc-price-calculator', WCPT_PLUGIN_URL . 'pro/assets/js/wc-measurement-price-calculator.js', array('jquery'), WC_VERSION, true);
    // tooltip required by MPC
    wp_enqueue_script('jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array('jquery'), defined('WC_VERSION') ? WC_VERSION : $version, true);
  }


  // WooCommerce Product Table - self
  // -- scripts
  wp_enqueue_script('wcpt', plugin_dir_url(__FILE__) . 'assets/js' . $min . '.js', array('jquery', 'freeze_table'), WCPT_VERSION, true);
  wp_localize_script(
    'wcpt',
    'wcpt_i18n',
    array(
      // 'ajax_url' => admin_url( 'admin-ajax.php' ),
      'i18n_no_matching_variations_text' => esc_attr__('Sorry, no products matched your selection. Please choose a different combination.', 'woocommerce'),
      'i18n_make_a_selection_text' => esc_attr__('Please select some product options before adding this product to your cart.', 'woocommerce'),
      'i18n_unavailable_variation_text' => esc_attr__('Sorry, this product is unavailable. Please choose a different combination.', 'woocommerce'),
      'i18n_out_of_stock_text' => esc_attr__('This product is currently out of stock and unavailable.', 'woocommerce'),
      'lang' => !empty($_REQUEST['lang']) ? $_REQUEST['lang'] : '',
      // 'currency_symbol' => get_woocommerce_currency_symbol()
    )
  );

  $settings = wcpt_get_settings_data();

  if (
    empty($settings['cart_widget']) ||
    empty($settings['cart_widget']['enabled_site_wide'])
  ) {
    $settings['cart_widget'] = array(
      'enabled_site_wide' => false,
      'exclude_urls' => false,
      'include_urls' => false,
      'link' => 'cart',
    );
  }

  $responsive_checkbox_trigger = false;
  if (
    !empty($settings['checkbox_trigger']) &&
    !empty($settings['checkbox_trigger']['r_toggle']) &&
    $settings['checkbox_trigger']['r_toggle'] === 'enabled'
  ) {
    $responsive_checkbox_trigger = true;
  }

  wp_localize_script(
    'wcpt',
    'wcpt_params',
    array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'wc_ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
      'shop_url' => get_permalink(wc_get_page_id('shop')),
      'shop_table_id' => wcpt_get_shop_table_id(),
      'site_url' => site_url(),
      'cart_widget_enabled_site_wide' => $settings['cart_widget']['enabled_site_wide'],
      'cart_widget_exclude_urls' => !empty($settings['cart_widget']['exclude_urls']) ? $settings['cart_widget']['exclude_urls'] : false,
      'cart_widget_include_urls' => !empty($settings['cart_widget']['include_urls']) ? $settings['cart_widget']['include_urls'] : false,
      'initially_empty_cart' => !WC()->cart || !WC()->cart->get_cart_contents_count(),
      'breakpoints' => apply_filters('wcpt_breakpoints', $GLOBALS['wcpt_breakpoints']),
      'price_decimals' => wc_get_price_decimals(),
      'price_decimal_separator' => wc_get_price_decimal_separator(),
      'price_thousand_separator' => wc_get_price_thousand_separator(),
      'price_format' => get_woocommerce_price_format(),
      'currency_symbol' => get_woocommerce_currency_symbol(),
      'initial_device' => wcpt_get_device(),
      'responsive_checkbox_trigger' => $responsive_checkbox_trigger,
      'pagination_slug' => wcpt_get_pagination_slug(),
      'permalink_structure' => get_option('permalink_structure'),
    )
  );

  wp_enqueue_script('wc-add-to-cart', apply_filters('woocommerce_get_asset_url', plugins_url('assets/js/frontend/add-to-cart' . $min . '.js', WC_PLUGIN_FILE), 'assets/js/frontend/add-to-cart' . $min . '.js'), array('jquery', 'wp-util'), WC_VERSION);
  wp_enqueue_script('wc-add-to-cart-variation', apply_filters('woocommerce_get_asset_url', plugins_url('assets/js/frontend/add-to-cart-variation' . $min . '.js', WC_PLUGIN_FILE), 'assets/js/frontend/add-to-cart-variation' . $min . '.js'), array('jquery', 'wp-util'), WC_VERSION);
  wp_enqueue_script('wp-mediaelement');
  include(WCPT_PLUGIN_PATH . 'templates/form-loading-screen.php');
  include(WCPT_PLUGIN_PATH . 'templates/checkbox-trigger.php');

  // -- styles
  wp_enqueue_style('wcpt', plugin_dir_url(__FILE__) . 'assets/css' . $min . '.css', false, WCPT_VERSION);
  wp_enqueue_style('wp-mediaelement');

  // media player button hover fix
  wp_add_inline_style('wcpt', '
    .mejs-button>button {
      background: transparent url(' . includes_url() . 'js/mediaelement/mejs-controls.svg) !important;
    }
    .mejs-mute>button {
      background-position: -60px 0 !important;
    }    
    .mejs-unmute>button {
      background-position: -40px 0 !important;
    }    
    .mejs-pause>button {
      background-position: -20px 0 !important;
    }    
  ');

  // Name your price
  if (function_exists('WC_Name_Your_Price')) {
    $wcpt_nyp_error_message_templates = apply_filters(
      'wc_nyp_error_message_templates',
      array(
        'invalid-product' => __('This is not a valid product.', 'wc_name_your_price'),
        'invalid' => __('&quot;%%TITLE%%&quot; could not be added to the cart. Please enter a valid, positive number.', 'wc_name_your_price'),
        'minimum' => __('&quot;%%TITLE%%&quot; could not be added to the cart. Please enter at least %%MINIMUM%%.', 'wc_name_your_price'),
        'hide_minimum' => __('&quot;%%TITLE%%&quot; could not be added to the cart. Please enter a higher amount.', 'wc_name_your_price'),
        'minimum_js' => __('Please enter at least %%MINIMUM%%.', 'wc_name_your_price'),
        'hide_minimum_js' => __('Please enter a higher amount.', 'wc_name_your_price'),
        'maximum' => __('&quot;%%TITLE%%&quot; could not be added to the cart. Please enter less than or equal to %%MAXIMUM%%.', 'wc_name_your_price'),
        'maximum_js' => __('Please enter less than or equal to %%MAXIMUM%%.', 'wc_name_your_price'),
        'empty' => __('Please enter an amount.', 'wc_name_your_price'),
        'minimum-cart' => __('&quot;%%TITLE%%&quot; cannot be purchased. Please enter at least %%MINIMUM%%.', 'wc_name_your_price'),
        'maximum-cart' => __('&quot;%%TITLE%%&quot; cannot be purchased. Please enter less than or equal to %%MAXIMUM%%.', 'wc_name_your_price'),
      )
    );

    wp_localize_script('wcpt', 'wcpt_nyp_error_message_templates', $wcpt_nyp_error_message_templates);
  }

  // WooCommerce Request a Quote (Addify)
  if (class_exists("Addify_Request_For_Quote")) {
    if (is_plugin_active('woocommerce-request-a-quote/class-addify-request-for-quote.php')) {
      $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/woocommerce-request-a-quote/class-addify-request-for-quote.php');
      $plugin_version = $plugin_data['Version'];

      // below v2.6.0
      if (version_compare($plugin_version, '2.6.0', '<')) {
        wp_enqueue_script('wcpt-addify-legacy', plugin_dir_url(__FILE__) . 'pro/assets/js/addify-request-quote-legacy' . $min . '.js', array('jquery', 'wcpt'), WCPT_VERSION, true);

      } else {
        // above v2.6.0
        wp_enqueue_script('wcpt-addify', plugin_dir_url(__FILE__) . 'pro/assets/js/addify-request-quote' . $min . '.js', array('jquery', 'wcpt'), WCPT_VERSION, true);

      }

    }
  }

  // JetPack lazy load image fix
  if (defined('JETPACK__VERSION')) {
    ob_start();
    ?>
    function wcpt_jetpack_lazy_load_compatibility(){
    document.querySelector( 'body' ).dispatchEvent(new Event( 'jetpack-lazy-images-load' ));
    }

    jQuery(function($){
    $('body').on('wcpt_after_every_load', '.wcpt', wcpt_jetpack_lazy_load_compatibility);
    })
    <?php
    wp_add_inline_script('wcpt', ob_get_clean(), 'after');
  }

  // WC measurement price calculator -- style
  if (
    class_exists('WC_Measurement_Price_Calculator') &&
    defined('WCPT_PRO')
  ) {
    wp_add_inline_style(
      'wcpt',
      '.wcpt #price_calculator {
        width: auto;
      }
      
      .wcpt #price_calculator input[type="text"],
      .wcpt #price_calculator input[type="number"],
      .wcpt #price_calculator input[type="text"],
      .wcpt #price_calculator input[type="number"] {
        width: 100px;
      }
      
      #tiptip_holder {
        display: none;
        position: absolute;
        top: 0;
        left: 0;
        z-index: 99999;
      }

      #tiptip_holder.tip_top {
        padding-bottom: 5px;
      }

      #tiptip_holder.tip_top #tiptip_arrow_inner {
        margin-top: -7px;
        margin-left: -6px;
        border-top-color: #464646;
      }

      #tiptip_holder.tip_bottom {
        padding-top: 5px;
      }

      #tiptip_holder.tip_bottom #tiptip_arrow_inner {
        margin-top: -5px;
        margin-left: -6px;
        border-bottom-color: #464646;
      }

      #tiptip_holder.tip_right {
        padding-left: 5px;
      }

      #tiptip_holder.tip_right #tiptip_arrow_inner {
        margin-top: -6px;
        margin-left: -5px;
        border-right-color: #464646;
      }

      #tiptip_holder.tip_left {
        padding-right: 5px;
      }

      #tiptip_holder.tip_left #tiptip_arrow_inner {
        margin-top: -6px;
        margin-left: -7px;
        border-left-color: #464646;
      }

      #tiptip_content, .chart-tooltip {
        font-size: 11px;
        color: #fff;
        padding: 0.5em 0.5em;
        background: #464646;
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
        border-radius: 3px;
        -webkit-box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
        -moz-box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
        box-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
        text-align: center;
        max-width: 150px;
      }

      #tiptip_content code, .chart-tooltip code {
        background: #888;
        padding: 1px;
      }

      #tiptip_arrow, #tiptip_arrow_inner {
        position: absolute;
        border-color: transparent;
        border-style: solid;
        border-width: 6px;
        height: 0;
        width: 0;
      }            
    '
    );
  }

  // NP Quote Request WooCommerce -- style
  if (class_exists('GPLS_WOO_RFQ')) {
    wp_add_inline_style(
      'wcpt',
      '.wcpt-cw-footer,
     .wcpt-cw-separator {
        display: none !important;
    }'
    );
  }

  // theme specific
  $theme_slug = trim(get_option('template'));

  if (substr($theme_slug, -6) == '-child') {
    $theme_slug = substr($theme_slug, 0, -6);
  }

  switch (strtolower($theme_slug)) {

    // -- be theme

    case 'betheme':

      wp_add_inline_style(
        'wcpt',
        '.wcpt-product-form, .wcpt {
          .mfn-variations-wrapper {
              display: none !important;
          }
          
          .mfn-variations-wrapper + table.variations {
              display: block !important;
          }
          
          a.quantity-change {
              display: none !important;
          }
      }'
      );

    //-- the7
    case 'dt-the7':
      wp_add_inline_style(
        'wcpt',
        ' .woocommerce-variation-add-to-cart .minus,
        .woocommerce-variation-add-to-cart .plus {
          padding: 0 !important;
          height: 40px !important;
          width: 25px !important;
          text-align: center !important;
        }'
      );

      break;

    //-- jupiterx
    case 'jupiterx':
      wp_add_inline_style(
        'wcpt',
        '.wcpt-quantity .input-group {
        display: none !important;
      }
      
      .wcpt-quantity input.qty {
        display: inline-block !important;
      }'
      );

      break;


    //-- jupiter
    case 'jupiter':
      wp_add_inline_style(
        'wcpt',
        '.wcpt-modal .cart select {
        height: 45px !important;
        font-size: 18px !important;
        line-height: 20px !important;
        font-weight: normal !important;
      }

      .wcpt-modal .cart input.qty {
        width: 80px !important;
        text-align: center !important;
        padding-right: 36px !important;
      }

      .woocommerce .wcpt-modal .cart .quantity {
        margin-left: 20px !important;
      }

      .wcpt-modal .cart .single_variation_wrap .single_variation {
        float: none !important;
      }

      .wcpt-product-form table.variations tr td {
        text-align: left
      }

      .wcpt-product-form table.variations tr td.label label {
        margin-top: 10px !important;
        display: inline-block !important;
      }'
      );

      break;

    //-- shopkeeper
    case 'shopkeeper':
      wp_add_inline_style(
        'wcpt',
        '.wcpt-modal .cart select {
        height: 45px !important;
        font-size: 18px !important;
        line-height: 20px !important;
        font-weight: normal !important;
      }

      .wcpt-product-form table.variations tr td.label label {
        margin-top: 10px !important;
        display: inline-block !important;
      }'
      );

      break;

    //-- flatsome
    case 'flatsome':
      wp_add_inline_style('wcpt', '
      .wcpt-product-form .woocommerce-variation-add-to-cart .plus,
      .wcpt-product-form .woocommerce-variation-add-to-cart .minus {
        display: none;
      }

      .wcpt-product-form .variations .reset_variations {
          position: relative !important;
          right: 0 !important;
          bottom: 0 !important;
          color: currentColor !important;
          opacity: 0.6;
          font-size: 11px;
          text-transform: uppercase;
      }

      .wcpt-product-form .cart .button,
      .wcpt .cart .button {
        margin-bottom: 0 !important;
      }

      .wcpt-quantity .qty {vertical-align: initial;}
      ');

      break;

    //-- x
    case 'x':
      wp_add_inline_style('wcpt', '
      .wcpt-product-form input.input-text[type="number"] {
        height: 44px !important;
      }
      ');

      break;

    //-- woodmart
    case 'woodmart':
      wp_add_inline_style('wcpt', '
      .wcpt-product-form .swatches-select {
        display: none !important;
      }

      .wcpt-product-form .quantity input[type="button"] {
        display: none !important;
      }

      .wcpt-product-form .woocommerce-variation-price .price {
        margin: 0 20px 0 0 !important;
      }

      .woodmart-products-shop-view {
        display: none !important;
      }

      div.quantity.wcpt-quantity-wrapper {
        font-size: 16px;    
      }

      ');

      break;

    //-- martfury
    case 'martfury':
      wp_add_inline_style('wcpt', '
      .wcpt-table {
        min-width: 100%;
      }
      ');

      break;

    //-- divi
    case 'divi':
      wp_add_inline_style('wcpt', '
      .wcpt-table {
        min-width: 100%;
      }

      .wcpt-add-to-cart-wrapper .quantity {
        width: auto !important;
      }

      .wcpt-add-to-cart-wrapper .quantity + button {
        vertical-align: middle !important;
      }

      .wcpt-product-form .woocommerce-variation-add-to-cart .button, .wcpt-product-form .button.button.single_add_to_cart_button,
      .wcpt-product-form .woocommerce-variation-add-to-cart .button:hover, .wcpt-product-form .button.button.single_add_to_cart_button:hover {
        padding: 12px 16px;
        height: auto !important;
        line-height: 1em !important; 
      }
      
      .wcpt-product-form .woocommerce-variation-add-to-cart .button:after, .wcpt-product-form .button.button.single_add_to_cart_button:after {
        display: none !important;
      } 
      
      html, body {
        overflow: visible !important;
      }

      ');

      break;

    //-- avada
    case 'avada':
      wp_add_inline_style('wcpt', '
      .wcpt-table {
        min-width: 100%;
      }

      body .wcpt-table input[type=number].qty {
        line-height: 17px !important;
        font-size: 14px !important;
        margin: 0 !important;
      }

      .wcpt-product-form .wcpt-quantity > input:not([type="number"]),
      .wcpt-table .wcpt-quantity > input:not([type="number"]) {
        display: none !important;
      }

      .wcpt-product-form .quantity .qty {
        width: 80px !important;
      }

      .wcpt-product-form .quantity input[type="button"] {
        display: none !important;
      }

      .wcpt-table .product-addon {
        width: 100% !important;
      }

      .wcpt-modal-content .woocommerce-variation.single_variation {
        display: none !important;
      }

      .avada-footer-scripts .pswp { 
        display: none; 
      }

      #products { 
        z-index: 2; 
        position: relative; 
      }
      
      ');

      break;

    // -- unicon
    case 'unicon':
      wp_add_inline_style('wcpt', '
      .wcpt .wcpt-table .wcpt-quantity > .input-group {
        display: inline-block !important;
        height: 100% !important;
        width: 100% !important;
    }

      .wcpt .wcpt-table .wcpt-quantity > .input-group > .input-group-btn {
        display: none !important;
      }

      .quantity .input-text {
        float: none !important;
        width: 100% !important;
        height: 100% !important;
        padding: 0 !important;
        background: transparent !important;
    }
      ');

      ob_start();
      ?>
      ;(function($){
      $('.wcpt').on('wcpt_layout', function(){
      $('.wcpt-quantity input').attr('type', 'number');
      })
      })(jQuery);

      <?php
      wp_add_inline_script('wcpt', ob_get_clean(), 'after');
      break;

    //-- equipo
    case 'equipo':
      wp_add_inline_style('wcpt', '
      .woocommerce-Tabs-panel .wcpt tr > th {
        width: auto !important;
        min-width: 0 !important;
      }
      ');

      break;


    //-- Total
    case 'total':
      wp_add_inline_style('wcpt', '
      .wcpt .quantity.wcpt-quantity > div.wpex-quantity-btns-wrap {
        display: inline-block !important;
        height: 100%;
      }
      
      .wcpt .quantity.wcpt-quantity > div.wpex-quantity-btns-wrap .wpex-quantity-btns {
          display: none !important;
      }
      ');

      break;

    //-- enfold
    case 'enfold':
      wp_add_inline_style('wcpt', '
      .wcpt-range-options-wrapper input[type=number] {
          width: 60px !important;
          height: 36px !important;
          margin-right: 5px !important;
          margin-bottom: 0 !important;
          display: inline-block !important;
          padding: 0 0 0 5px !important;
      }

      .wcpt div form.cart div.quantity {
        float: none !important;
        margin: 0 5px 5px 0;
        white-space: nowrap;
        border: none;
        vertical-align: middle;
        min-width: 0;
        width: auto;
      }

      #top .wcpt form.cart .single_add_to_cart_button {
        float: none !important;
        margin-bottom: 5px;
        padding: 12px 30px;
        vertical-align: middle;
      }

      .wcpt-product-form .single_add_to_cart_button {
        border: 1px solid #c7c7c7;
      }

      .wcpt .single_variation_wrap, 
      .wcpt-product-form .single_variation_wrap {
        margin: 0 0 20px !important;
      }

      .wcpt .reset_variations, 
      .wcpt-product-form .reset_variations {
        line-height: 1em;
        font-size: 12px;
        position: relative;
        right: 0;
        bottom: 0;
        height: auto;
        margin-top: 1em;
        display: inline-block;
      }

      ');

      ob_start();
      ?>
      jQuery(function($){
      setTimeout(function(){
      $('.wcpt-quantity > .qty').attr('type', 'number');
      }, 200);

      // Enfold - add the + - buttons
      function wcpt_avia_apply_quant_btn(){
      $( ".wcpt .cart .quantity input[type=number], .wcpt-product-form .cart .quantity input[type=number]" ).each( function()
      {
      var number = $(this),
      current_val = number.val(),
      cloned = number.clone( true );

      // WC 4.0 renders '' for grouped products
      if( ( 'undefined' == typeof( current_val ) ) || ( '' == ( current_val + '' ).trim() ) )
      {
      var placeholder = cloned.attr( 'placeholder' );
      placeholder = ( ( 'undefined' == typeof( placeholder ) ) || ( '' == ( placeholder + '' ).trim() ) ) ? 1 : placeholder;
      cloned.attr( 'value', placeholder );
      }

      var max = parseFloat( number.attr( 'max' ) ),
      min = parseFloat( number.attr( 'min' ) ),
      step = parseInt( number.attr( 'step' ), 10 ),
      newNum = $( $( '
      <div />' ).append( cloned ).html().replace( 'number','text' ) ).insertAfter( number );
      number.remove();

      setTimeout(function(){
      if( newNum.next( '.plus' ).length === 0 )
      {
      var minus = $( '<input type="button" value="-" class="minus">' ).insertBefore( newNum ),
      plus = $( '<input type="button" value="+" class="plus">' ).insertAfter( newNum );

      minus.on( 'click', function()
      {
      var the_val = parseInt( newNum.val(), 10 ) - step;
      the_val = the_val < 0 ? 0 : the_val; the_val=the_val < min ? min : the_val; newNum.val(the_val).trigger( "change" ); });
        plus.on( 'click' , function() { var the_val=parseInt( newNum.val(), 10 ) + step; the_val=the_val> max ? max : the_val;
        newNum.val(the_val).trigger( "change" );

        });
        }
        },10);

        });
        }

        $('body').on('wcpt_after_every_load', '.wcpt', wcpt_avia_apply_quant_btn);
        $('body').on('wcpt_product_form_ready', wcpt_avia_apply_quant_btn);
        })
        <?php
        wp_add_inline_script('wcpt', ob_get_clean(), 'after');

        break;

    //-- plumbin
    case 'plumbin':

      ob_start();
      ?>
        jQuery(function( $ ){
        function wcpt_plumbin_input_fix(){
        var $qty = $('.wcpt-quantity');
        $qty.each(function(){
        var $this = $(this),
        $input = $this.find('.qty'),
        $minus = $this.find('.wcpt-minus'),
        $input_grp = $this.find('.input-group');

        $input.attr('type', 'number');

        if( $input_grp.length ){
        $input.insertAfter($minus);
        $input_grp.remove();
        }
        })
        }

        wcpt_plumbin_input_fix();
        $('.wcpt').one('wcpt_layout', wcpt_plumbin_input_fix);
        setTimeout(wcpt_plumbin_input_fix, 1000);
        })
        <?php
        wp_add_inline_script('wcpt', ob_get_clean(), 'after');

        break;

    //-- bavarian
    case 'bavarian':
      ob_start();
      ?>
        jQuery(function($){
        $('body').on('click', 'a.wcpt-title, .wcpt-button-product_link, a.wcpt-product-image-wrapper, .wcpt-product-link',
        function(e){
        window.location = $(this).attr('href');
        setTimeout(function(){ $('.kt-preloader-obj').hide(); }, 1);
        })
        })
        <?php
        wp_add_inline_script('jquery', ob_get_clean(), 'after');

        break;


    //-- motor
    case 'motor':
      wp_add_inline_style('wcpt', '
      .wcpt + .row .stm-blog-pagination {
        display: none !important;
      }
      ');

      break;

    //-- riode
    case 'riode':
      ob_start();
      ?>
        jQuery(function($){
        var count = 10,
        clear = setInterval(function(){
        var $qty_plus = $('.wcpt-plus');
        $qty_plus.off('mousedown');
        --count;
        if( ! count ){
        clearInterval(clear);
        }
        }, 500);
        })
        <?php
        wp_add_inline_script('jquery', ob_get_clean(), 'after');

        break;

    default:
      // code...
      break;

  }

  // yith yraq
  if (
    defined('YITH_YWRAQ_PREMIUM') &&
    defined('WCPT_PRO')
  ) {
    wp_enqueue_script('wcpt-yith-ywraq', WCPT_PLUGIN_URL . 'pro/assets/js/yith-ywraq.js', array('jquery'), WC_VERSION, true);
    wp_add_inline_script('wcpt-yith-ywraq', 'var wcpt_ywraq_url="' . YITH_Request_Quote()->get_raq_page_url() . '"', 'after');

    $wcpt_ywraq_ids = array();

    foreach (YITH_Request_Quote()->raq_content as $item) {
      if (!isset($item['variation_id'])) {
        $wcpt_ywraq_ids[] = $item['product_id'];

      } else if ($item['variation_id'] != 0) {
        $wcpt_ywraq_ids[] = $item['variation_id'];
      }
    }

    wp_add_inline_script('wcpt-yith-ywraq', 'var wcpt_ywraq_ids=' . json_encode($wcpt_ywraq_ids) . '; ', 'after');

    wp_enqueue_style('wcpt-yith-ywraq', WCPT_PLUGIN_URL . 'pro/assets/css/yith-ywraq.css', null, WC_VERSION);
  }

  // ultimate social media icons
  if (defined('SFSI_DOCROOT')) {
    wp_enqueue_script('wcpt-ultimate-social-media-icons', WCPT_PLUGIN_URL . 'pro/assets/js/ultimate-social-media-icons.js', array('jquery', 'SFSICustomJs'), WC_VERSION, true);
  }

  // product addons
  if (
    defined('WCPT_PRO') &&
    defined('WC_PRODUCT_ADDONS_VERSION')
  ) {
    // jquery tipTip
    wp_enqueue_script('jquery-tiptip', WC()->plugin_url() . '/assets/js/jquery-tiptip/jquery.tipTip.min.js', array('jquery'), WC_VERSION, true);

    // -- older versions
    if (version_compare(WC_PRODUCT_ADDONS_VERSION, '6.3.0', '<')) {
      wp_register_script('accounting', WC()->plugin_url() . '/assets/js/accounting/accounting.min.js', array('jquery'), '0.4.2');

      wp_dequeue_script('woocommerce-addons');
      wp_enqueue_script('woocommerce-addons', WCPT_PLUGIN_URL . 'pro/assets/js/addons.js', array('jquery', 'accounting'), WC_VERSION, true);

      $params = array(
        'price_display_suffix' => esc_attr(get_option('woocommerce_price_display_suffix')),
        'tax_enabled' => wc_tax_enabled(),
        'price_include_tax' => 'yes' === esc_attr(get_option('woocommerce_prices_include_tax')),
        'display_include_tax' => (wc_tax_enabled() && 'incl' === esc_attr(get_option('woocommerce_tax_display_shop'))) ? true : false,
        'ajax_url' => WC()->ajax_url(),
        'i18n_sub_total' => __('Subtotal', 'woocommerce-product-addons'),
        'i18n_remaining' => __('characters remaining', 'woocommerce-product-addons'),
        'currency_format_num_decimals' => absint(get_option('woocommerce_price_num_decimals')),
        'currency_format_symbol' => get_woocommerce_currency_symbol(),
        'currency_format_decimal_sep' => esc_attr(stripslashes(get_option('woocommerce_price_decimal_sep'))),
        'currency_format_thousand_sep' => esc_attr(stripslashes(get_option('woocommerce_price_thousand_sep'))),
        'trim_trailing_zeros' => apply_filters('woocommerce_price_trim_zeros', false),
        'is_bookings' => class_exists('WC_Bookings'),
        'trim_user_input_characters' => apply_filters('woocommerce_product_addons_show_num_chars', 1000),
        'quantity_symbol' => 'x ',
      );

      if (!function_exists('get_woocommerce_price_format')) {
        $currency_pos = get_option('woocommerce_currency_pos');

        switch ($currency_pos) {
          case 'left':
            $format = '%1$s%2$s';
            break;
          case 'right':
            $format = '%2$s%1$s';
            break;
          case 'left_space':
            $format = '%1$s&nbsp;%2$s';
            break;
          case 'right_space':
            $format = '%2$s&nbsp;%1$s';
            break;
        }

        $params['currency_format'] = esc_attr(str_replace(array('%1$s', '%2$s'), array('%s', '%v'), $format));
      } else {
        $params['currency_format'] = esc_attr(str_replace(array('%1$s', '%2$s'), array('%s', '%v'), get_woocommerce_price_format()));
      }

      wp_localize_script('woocommerce-addons', 'woocommerce_addons_params', apply_filters('woocommerce_product_addons_params', $params));

      wp_enqueue_style('woocommerce-addons-css', plugins_url() . '/woocommerce-product-addons/assets/css/frontend.css');

      // -- newer versions
    } else {

      // style
      if (!wp_script_is('woocommerce-addons-css', 'enqueued')) {
        wp_enqueue_style('woocommerce-addons-css', plugins_url() . '/woocommerce-product-addons/assets/css/frontend/frontend.css');
      }

    }
  }

}

/**
 * Get the pagination key used by WordPress.
 *
 * Retrieves the pagination base slug (e.g., 'page') if pretty permalinks are enabled,
 * or the query variable key (e.g., 'paged') if plain permalinks are used.
 *
 * @global WP_Rewrite $wp_rewrite WordPress rewrite component.
 * @return string The pagination key ('paged' for plain permalinks, pagination base slug for pretty permalinks).
 */
function wcpt_get_pagination_slug()
{
  global $wp_rewrite;

  // Ensure $wp_rewrite is available and check if permalinks are enabled
  if (isset($wp_rewrite) && is_object($wp_rewrite) && $wp_rewrite->using_permalinks()) {
    // Pretty permalinks are enabled, use the pagination base slug
    $pagination_base = $wp_rewrite->pagination_base;

    // If the pagination base is somehow empty with permalinks enabled, default to 'page'.
    if (empty($pagination_base)) {
      $pagination_base = 'page';
    }
    return $pagination_base;

  } else {
    // Plain permalinks are used (e.g., ?paged=2), the key is 'paged'.
    return 'paged';
  }
}

function wcpt_woocommerce_photoswipe()
{
  wc_get_template('single-product/photoswipe.php');
}

/* permitted shortcode attributes */
add_action('init', 'wcpt_set_permitted_shortcode_attributes');
function wcpt_set_permitted_shortcode_attributes()
{
  $GLOBALS['wcpt_permitted_shortcode_attributes'] = apply_filters(
    'wcpt_permitted_shortcode_attributes',
    array(
      'id',
      'name',
      'offset',
      'limit',
      'category',
      'orderby',
      'order',
      'ids',
      'skus',
      'use_default_search',
      'class',

      'auto_scroll',
      'laptop_auto_scroll',
      'tablet_auto_scroll',
      'phone_auto_scroll',

      'laptop_scroll_offset',
      'tablet_scroll_offset',
      'phone_scroll_offset',

      'disable_url_update',
      'disable_ajax',

      'html_class',
    )
  );
}

/* wcpt ajax shortcode */
add_action('wc_ajax_wcpt_ajax', 'wcpt_ajax');
add_action('wp_ajax_wcpt_ajax', 'wcpt_ajax');
add_action('wp_ajax_nopriv_wcpt_ajax', 'wcpt_ajax');
function wcpt_ajax()
{
  if (!empty($_REQUEST['id'])) {
    $sc_attrs = '';
    if (
      !empty($_REQUEST[$_REQUEST['id'] . '_sc_attrs']) &&
      $_REQUEST[$_REQUEST['id'] . '_sc_attrs'] = json_decode(stripslashes($_REQUEST[$_REQUEST['id'] . '_sc_attrs']))
    ) {
      foreach ($_REQUEST[$_REQUEST['id'] . '_sc_attrs'] as $key => $val) {
        if (in_array($key, $GLOBALS['wcpt_permitted_shortcode_attributes'])) {
          $sc_attrs .= ' ' . $key . '="' . $val . '" ';
        }
      }
    }

    $id = (int) $_REQUEST['id'];
    if (is_numeric($id)) {
      // remove square brackets and < brackets to prevent shortcode or js execution
      $sc_attrs = preg_replace('/[\[\]<>]/', '', $sc_attrs);
      echo do_shortcode('[product_table id="' . $id . '" ' . $sc_attrs . ' ]');
    }

  }
  die();
}

// removes other woocommerce arguments from the pagination links
function wcpt_paginate_links($link)
{
  $remove = array('add-to-cart', 'variation_id', 'product_id', 'quantity');
  foreach ($_GET as $key => $val) {
    if (substr($key, 0, 10) === 'attribute_') {
      $remove[] = $key;
    }
  }
  return remove_query_arg($remove, $link);
}

// remove inline editor buttons from 'ALL Tables' page
// add_filter('post_row_actions', 'wcpt_row_buttons', 10, 2);
// function wcpt_row_buttons($actions, $post)
// {
//   if ($post->post_type == 'wc_product_table') {
//     unset($actions['inline hide-if-no-js'], $actions['view']);
//   }
//   return $actions;
// }

// add view product table link in admin bar
add_action('admin_bar_menu', 'wcpt_add_view_button_to_wcpt_editor', 100);
function wcpt_add_view_button_to_wcpt_editor($admin_bar)
{
  // Only run on product table edit pages
  if (
    !is_admin() ||
    !isset($_GET['page']) ||
    $_GET['page'] !== 'wcpt-edit' ||
    !isset($_GET['post_id'])
  ) {
    return;
  }

  $post_id = (int) $_GET['post_id'];
  $post_status = get_post_status($post_id);

  // Only show for published tables
  if ($post_status !== 'publish') {
    return;
  }

  // Add the view button to admin bar
  $admin_bar->add_node([
    'id' => 'view',
    'title' => __('View Table'),
    'href' => get_permalink($post_id),
    'meta' => [
      'target' => '_blank',
      'class' => 'ab-item'
    ]
  ]);
}

require_once(WCPT_PLUGIN_PATH . 'preview-form/preview-form.php');

// cancel add to cart redirect when WCPT is adding to cart 
if (
  !empty($_REQUEST['wcpt_payload']) ||
  !empty($_REQUEST['wcpt_request'])
) {
  // needs to be disabled because the cart redirect breaks table add to cart functinality
  function wcpt_disable_cart_redirect($value)
  {
    return "no";
  }
  add_filter('option_woocommerce_cart_redirect_after_add', 'wcpt_disable_cart_redirect', 10, 1);
}

// process add to cart payload
add_action('wp_loaded', 'wcpt_process_cart_payload', 15);
function wcpt_process_cart_payload()
{
  if (empty($_REQUEST['wcpt_payload'])) {
    return;
  }

  // clear cart
  if (!empty($_REQUEST['wcpt_payload']['clear_cart'])) {
    WC()->cart->empty_cart();
    return;
  }

  if (empty($_REQUEST['wcpt_payload']['products'])) {
    return;
  }

  $_REQUEST['_wcpt_payload'] = $_REQUEST['wcpt_payload']; // original will be mutated

  add_filter('woocommerce_add_error', 'wcpt__woocommerce_add_error', 10);

  // addons - official Woocommerce Product Addons
  if (
    class_exists('WC_Product_Addons_Helper') ||
    function_exists('get_product_addons')
  ) {
    // don't need to sync with product loop, each product addon name is based on product id
    if (!empty($_REQUEST['wcpt_payload']['addons'])) {
      foreach ($_REQUEST['wcpt_payload']['addons'] as $product_id => $addons) {
        foreach ($addons as $key => $val) {
          $_POST[$key] = $val;
        }
      }
    }
  }

  foreach ($_REQUEST['wcpt_payload']['products'] as $product_id => $qty) {

    $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($product_id));
    $adding_to_cart = wc_get_product($product_id);

    if (!$adding_to_cart) {
      continue;
    }

    // overwrite / remove

    if (!empty($_REQUEST['wcpt_payload']['overwrite_cart_qty'])) {
      $cart = WC()->instance()->cart;
      $found = false;
      $cart_contents = $cart->get_cart_contents();

      foreach ($cart_contents as $key => $item) {
        if ($item['product_id'] == $product_id) {
          if ( // product variation 
            $item['variation_id'] &&
            isset($_REQUEST['wcpt_payload']['variations']) &&
            !empty($_REQUEST['wcpt_payload']['variations'][$product_id]) &&
            !isset($_REQUEST['wcpt_payload']['variations'][$product_id][$item['variation_id']])
          ) {
            // request is meant to set qty for another variation of this variable product
            continue;
          }

          if ($item['variation_id']) {
            if (
              isset($_REQUEST['wcpt_payload']['variations']) &&
              isset($_REQUEST['wcpt_payload']['variations'][$product_id][$item['variation_id']])
            ) {
              $variation_qty = $_REQUEST['wcpt_payload']['variations'][$product_id][$item['variation_id']];
              unset($_REQUEST['wcpt_payload']['variations'][$product_id][$item['variation_id']]);

              $cart->set_quantity($key, $variation_qty);
              $found = true;
              continue;

            } else {
              continue;
            }
          }

          $cart->set_quantity($key, $qty);
          $found = true;
        }
      }

      // variations still left for 'Add'
      if (
        $adding_to_cart->get_type() === 'variable' &&
        isset($_REQUEST['wcpt_payload']['variations']) &&
        isset($_REQUEST['wcpt_payload']['variations'][$product_id])
      ) {
        // these variation removal requests could not be dealt with because they didn't exist in the cart to being with
        foreach ($_REQUEST['wcpt_payload']['variations'][$product_id] as $variation_id => $variation_qty) {
          if ($variation_qty == '0') {
            unset($_REQUEST['wcpt_payload']['variations'][$product_id][$variation_id]);
          }
        }

        if (count($_REQUEST['wcpt_payload']['variations'][$product_id])) {
          $found = false;
        }
      }

      if ( // no need to proceed to the 'Add' logic if:
        $qty === '0' || // - we were just removing this product (though maybe didn't find it)
        $found // - did find and modify the quantity
      ) {
        continue;
      }
    }

    // add

    // -- measurement
    $clear_measurement = array();
    if (
      !empty($_REQUEST['wcpt_payload']['measurement']) &&
      !empty($_REQUEST['wcpt_payload']['measurement'][$product_id])
    ) {
      foreach ($_REQUEST['wcpt_payload']['measurement'][$product_id] as $key => $val) {
        $_REQUEST[$key] = $val;
        $_POST[$key] = $val;

        $clear_measurement[] = $key;
      }
    }

    // -- name your price
    $_REQUEST['nyp'] = $_POST['nyp'] = 0; // clear nyp
    if (
      !empty($_REQUEST['wcpt_payload']['nyp']) &&
      !empty($_REQUEST['wcpt_payload']['nyp'][$product_id])
    ) {
      $_REQUEST['nyp'] = $_POST['nyp'] = $_REQUEST['wcpt_payload']['nyp'][$product_id];
    }

    // -- addons - Woocommerce Custom Product Addons
    $clear_addons = array();
    if (
      !empty($_REQUEST['wcpt_payload']['addons']) &&
      !empty($_REQUEST['wcpt_payload']['addons'][$product_id]) &&
      function_exists('wcpa_is_wcpa_product')
    ) {
      foreach ($_REQUEST['wcpt_payload']['addons'][$product_id] as $key => $val) {
        $_REQUEST[$key] = $_POST[$key] = $val;

        $clear_addons[] = $key;
      }
    }

    // -- product data
    $_REQUEST['product_id'] = $product_id;

    $add_to_cart_handler = apply_filters('woocommerce_add_to_cart_handler', $adding_to_cart->get_type(), $adding_to_cart);

    // Variable product handling
    if ('variable' === $add_to_cart_handler) {

      if ( // variation ID not provided in payload
        empty($_REQUEST['wcpt_payload']['variations']) ||
        empty($_REQUEST['wcpt_payload']['variations'][$product_id])
      ) {
        wc_add_notice(esc_attr__('Please select some product options before adding this product to your cart.', 'woocommerce'), 'error');

      } else { // variation ID provided

        foreach ($_REQUEST['wcpt_payload']['variations'][$product_id] as $variation_id => $variation_qty) {
          $_REQUEST['variation_id'] = $variation_id;
          $variation = wc_get_product($_REQUEST['variation_id']);
          $_REQUEST['quantity'] = $variation_qty ? $variation_qty : $variation->get_min_purchase_quantity();

          // unpollute $_REQ
          foreach ($variation->get_attributes() as $key => $val) {
            unset($_REQUEST['attribute_' . $key]);
          }

          // add user entered attributes
          if (!empty($_REQUEST['wcpt_payload']['attributes'][$variation_id])) {
            $_REQUEST += $_REQUEST['wcpt_payload']['attributes'][$variation_id];
            wcpt_woo_hack_invoke_private_method('WC_Form_Handler', 'add_to_cart_handler_variable', $product_id);

            // or give error if no attributes
          } else {
            wc_add_notice(esc_attr__('Please select some product options before adding this product to your cart.', 'woocommerce'), 'error');

          }

          unset($_REQUEST['variation_id']);
          unset($_REQUEST['quantity']);
        }

      }

      continue;
    }

    if (!$qty) {
      $qty = $adding_to_cart->get_min_purchase_quantity();
    }

    $_REQUEST['wcpt_payload']['quantity'] = $_REQUEST['quantity'] = $_POST['post'] = $qty;

    // Grouped Products
    if ('grouped' === $add_to_cart_handler) {
      wcpt_woo_hack_invoke_private_method('WC_Form_Handler', 'add_to_cart_handler_grouped', $product_id);

      // Custom Handler
    } elseif (has_action('woocommerce_add_to_cart_handler_' . $add_to_cart_handler)) {
      do_action('woocommerce_add_to_cart_handler_' . $add_to_cart_handler, $product_id);

      // Simple Products
    } else {
      wcpt_woo_hack_invoke_private_method('WC_Form_Handler', 'add_to_cart_handler_simple', $product_id);
    }

    // clear addons
    foreach ($clear_addons as $key) {
      unset($_REQUEST[$key]);
      unset($_POST[$key]);
    }

    // clear measurement
    foreach ($clear_measurement as $key) {
      unset($_REQUEST[$key]);
      unset($_POST[$key]);
    }

  }

  remove_filter('woocommerce_add_error', 'wcpt__woocommerce_add_error', 10);
}

// helper
function wcpt_woo_hack_invoke_private_method($class_name, $methodName)
{
  if (version_compare(phpversion(), '5.3', '<')) {
    throw new Exception('PHP version does not support ReflectionClass::setAccessible()', __LINE__);
  }

  $args = func_get_args();
  unset($args[0], $args[1]);
  $reflection = new ReflectionClass($class_name);
  $method = $reflection->getMethod($methodName);
  $method->setAccessible(true);

  $args = array_merge(array(new $class_name), $args);

  return call_user_func_array(array($method, 'invoke'), $args);
}

// error - product
add_filter('woocommerce_add_error', 'wcpt__woocommerce_add_error');
function wcpt__woocommerce_add_error($message)
{
  if (
    !empty($_REQUEST['wcpt_payload']) &&
    !empty($_REQUEST['product_id'])
  ) {
    $product = wc_get_product($_REQUEST['product_id']);
    $title = $product->get_title();

    if (
      $product->get_type() == 'variable' &&
      !empty($_REQUEST['variation_id'])
    ) {
      $title = get_the_title($_REQUEST['variation_id']);
    }

    ob_start();
    ?>
      <span class="wcpt-error-product-name">
        <?php
        echo esc_html($title);
        ?>
      </span>
      <?php
      $title_mkp = ob_get_clean();

      if (false === strpos($message, $title_mkp)) {
        $message = $title_mkp . $message;
      }
  }

  return $message;
}

/* ajax add to cart */
add_action('wc_ajax_wcpt_add_to_cart', 'wcpt_add_to_cart');
add_action('wp_ajax_wcpt_add_to_cart', 'wcpt_add_to_cart');
add_action('wp_ajax_nopriv_wcpt_add_to_cart', 'wcpt_add_to_cart');
function wcpt_add_to_cart()
{
  if (
    !empty($_POST['return_notice']) &&
    $_POST['return_notice'] == "false"
  ) {
    wp_die();
  }

  // success
  if (wc_notice_count('success')) {
    ob_start();
    woocommerce_mini_cart();
    $mini_cart = ob_get_clean();

    $cart_items = WC()->cart->get_cart();
    $in_cart = array();

    foreach ($cart_items as $item => $values) {
      if (!empty($values['variation_id'])) {
        if (empty($in_cart[$values['product_id']])) {
          $in_cart[$values['product_id']] = array();
        }
        $in_cart[$values['product_id']][$values['variation_id']] = $values['quantity'];

      } else {
        $in_cart[$values['product_id']] = $values['quantity'];
      }
    }

    $table_data = array(
      'success' => true,
      'fragments' => apply_filters(
        'woocommerce_add_to_cart_fragments',
        array(
          'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
        )
      ),
      'cart_hash' => apply_filters('woocommerce_add_to_cart_hash', WC()->cart->get_cart_for_session() ? md5(json_encode(WC()->cart->get_cart_for_session())) : '', WC()->cart->get_cart_for_session()),
      'cart_quantity' => WC()->cart->get_cart_contents_count(),
      'in_cart' => $in_cart,
    );

    // error
  } else {
    $table_data = array(
      'error' => true,
    );

  }

  // get notice markup
  $table_data['notice'] = "";
  if (wc_notice_count()) {
    ob_start();
    wc_print_notices();
    $table_data['notice'] = ob_get_clean();
  }

  wp_send_json($table_data);
}

/* cart widget */
add_action('wp_ajax_wcpt_cart_widget', 'wcpt_cart_widget');
add_action('wp_ajax_nopriv_wcpt_cart_widget', 'wcpt_cart_widget');
function wcpt_cart_widget()
{
  wp_die(include_once(WCPT_PLUGIN_PATH . 'templates/cart-widget.php'));
}

function wcpt_get_product_details_in_cart_including_variations($product_id)
{
  $result = array(
    "quantity" => 0,
    "cart_item_keys_arr" => array(),
  );
  $cart_contents = WC()->cart->get_cart();
  foreach ($cart_contents as $cart_item_key => $item_details) {
    if ($product_id === $item_details["product_id"]) {
      $result["cart-item-keys-arr"][] = $cart_item_key;
      $result["quantity"] += $item_details["quantity"];
    }
  }
  return $result;
}

add_action('wp_ajax_nopriv_wcpt_get_cart', 'wcpt_get_cart');
add_action('wp_ajax_wcpt_get_cart', 'wcpt_get_cart');
function wcpt_get_cart()
{
  wp_send_json(WC()->cart->get_cart());
}

/**
 * Enable the shortcode
 */
add_shortcode('product_table', 'wcpt_shortcode_product_table');
function wcpt_shortcode_product_table($atts = array())
{
  if (
    !class_exists('WooCommerce') ||
    !empty($GLOBALS['wcpt_table_instance'])
  ) {
    return;
  }

  foreach ($atts as $key => &$val) {
    if (!in_array($key, $GLOBALS['wcpt_permitted_shortcode_attributes'])) {
      unset($atts[$key]);
    }
  }

  if (empty($atts['id'])) {
    $post_title = !empty($atts['name']) ? $atts['name'] : (!empty($atts['title']) ? $atts['title'] : '');
    $atts['id'] = wcpt_get_table_id_from_name($post_title);
  }

  if (empty($atts['id'])) {
    return;
  }

  // Start the query monitor timer
  if (defined('WCPT_DEV') && WCPT_DEV) {
    do_action('qm/start', 'product table id: ' . $atts['id']);
  }

  // gets table data, applies filters and caches in global variable
  $GLOBALS['wcpt_table_data'] = wcpt_get_table_data($atts['id'], 'view');

  // apply filters to shortcode attributes
  $atts = apply_filters('wcpt_shortcode_attributes', (array) $atts, $GLOBALS['wcpt_table_data']);

  if ($error_message = wcpt_sc_error_checks($GLOBALS['wcpt_table_data'], $atts)) {
    $markup = $error_message;

  } else {
    if (!empty($GLOBALS['product'])) {
      $prev_global__product = $GLOBALS['product'];
    }
    if (!empty($GLOBALS['post'])) {
      $prev_global__post = $GLOBALS['post'];
    }

    require_once(WCPT_PLUGIN_PATH . 'class-wc-shortcode-product-table.php');
    $GLOBALS['wcpt_table_instance'] = new WC_Shortcode_Product_Table($atts);
    do_action('wcpt_before_product_table_is_processed');
    $content = wcpt_remove_product_table_shortcode($GLOBALS['wcpt_table_instance']->get_content());
    $markup = apply_filters('wcpt_markup', do_shortcode($content));
    do_action('wcpt_after_product_table_is_processed');

    unset($GLOBALS['wcpt_table_data']);
    unset($GLOBALS['wcpt_table_instance']);

    if (!empty($prev_global__product)) {
      $GLOBALS['product'] = $prev_global__product;
    }
    if (!empty($prev_global__post)) {
      $GLOBALS['post'] = $prev_global__post;
    }

  }

  // Stop the query monitor timer
  if (defined('WCPT_DEV') && WCPT_DEV) {
    do_action('qm/stop', 'product table id: ' . $atts['id']);
  }

  return str_replace("\n", "", $markup);
}

function wcpt_get_table_id_from_name($post_title)
{
  $id = '';

  $loop = new WP_Query(
    array(
      'posts_per_page' => 1,
      'post_type' => 'wc_product_table',
      'post_status' => 'publish',
      'title' => $post_title,
      'fields' => 'ids',
    )
  );

  if ($loop->have_posts()) {
    $id = $loop->posts[0];
  }

  wp_reset_postdata();

  return $id;
}

function wcpt_remove_product_table_shortcode($content)
{
  if (FALSE === strpos($content, '[product_table')) {
    return $content;
  }

  return preg_replace('/\[product_table(.*?)\]/s', '', $content);
}

/**
 * Process styles from $table_data of a table
 */
include(WCPT_PLUGIN_PATH . 'style-functions.php');

/**
 * Parse tpl with shortcodes
 */
function wcpt_parse_2($template, $product = false)
{

  if (gettype($template) !== 'array') {
    return $template;
  }

  if (!$product && isset($GLOBALS['product'])) {
    $product = $GLOBALS['product'];
  }

  $markup = '';
  // parse rows
  foreach ($template as $row) {
    // row condition
    if (
      defined('WCPT_PRO') &&
      !empty($row['condition']) &&
      !wcpt_condition($row['condition'])
    ) {
      continue;
    }

    // row condition
    if (empty($row['html_class'])) {
      $row['html_class'] = '';
    }

    $row_markup = '';
    // parse elements
    if (!empty($row['elements']) && gettype($template) == 'array') {
      foreach ($row['elements'] as $element) {
        if (!$element = apply_filters('wcpt_element', $element)) {
          continue;
        }

        $template_file_name = $element['type'] . '.php';
        $template_path = '';

        wcpt_parse_style_2($element);

        $default_template_path = WCPT_PLUGIN_PATH . 'templates/' . $template_file_name;
        $pro_template_path = WCPT_PLUGIN_PATH . 'pro/templates/' . $template_file_name;

        // lite template
        if (file_exists($default_template_path)) {
          $template_path = $default_template_path;

          // pro template
        } else if (file_exists($pro_template_path)) {
          $template_path = $pro_template_path;

        }

        $template_path = apply_filters('wcpt_template', $template_path, $template_file_name);

        $element_markup = wcpt_parse_ctx_2($element, $template_path, $element['type'], $product);

        $row_markup .= apply_filters('wcpt_element_markup', $element_markup, $element);
      }
    }

    if ($row_markup) {
      $markup .= '<div class="wcpt-item-row wcpt-' . $row['id'] . ' ' . $row['html_class'] . '">' . $row_markup . '</div>';
      wcpt_parse_style_2($row);
    }
  }

  return $markup;
}

function wcpt_parse_ctx_2($element, $elm_tpl, $elm_type, $product = false)
{
  if (!$elm_tpl) {
    return;
  }

  extract($element);
  ob_start();

  if (empty($html_class)) {
    $html_class = '';
  }

  $html_class .= ' wcpt-' . $element['id'];

  include $elm_tpl;
  return ob_get_clean();
}

/**
 * Error checking for shortcode - in case user has made some mistake
 */
function wcpt_sc_error_checks($table_data, $atts)
{
  $message = '';

  if (empty($table_data)) {
    $message = __('No product table settings were found for this id. Please try clicking the "Save settings" button at the bottom of the table editor page to save your table settings. If you have already done this and the issue still persists, the cause may be incompatibility with another script on your site. In that case you should contact the plugin developer <a href="https://wcproducttable.com/support/">here</a> for prompt support.', 'wc-product-table');

  } else if (
    (
      empty($table_data['columns']['laptop']) ||
      !is_array($table_data['columns']['laptop']) ||
      !count($table_data['columns']['laptop'])
    ) &&
    (
      empty($atts['form_mode'])
    )
  ) {
    $message = __('It appears you have not set any Laptop Columns for your product table. Therefore, without any columns, your table does not have any content to display. Please follow these steps:
                    <ol>
                      <li>Go to the table editor > Columns tab > Laptop Columns section and use the \'Add a Column\' button to add at least one column.</li>
                      <li>Within this column that you have added, either in the \'Heading\' or \'Cell template\' please add at least one element using the \'+ Add Element\' button. Otherwise this column will simply be empty.</li>
                      <li>Save your table settings after following the above two steps, and then reload this page.</li>
                    </ol>
                    If you have created at least one Laptop Column, with at least one element in it, and your table is saved, this warning message will be removed and your table will be presented. Please visit the <a href="https://wcproducttable.com/tutorials" target="_blank">plugin\'s  tutorials</a> for a clear guide on how to use this plugin and get the most out of it.', 'wc-product-table');

  } else if (
    wcpt_device_columns_empty($table_data['columns']['laptop']) &&
    empty($atts['form_mode'])
  ) {
    $message = __('While you have created at least one column in the Laptop Columns section for this table, it seems you have not created any elements in the columns. Please create at least one element in at least one Laptop Column for this table, then save your table settings and reload this page to see your table.', 'wc-product-table');

  }

  if (
    $message &&
    (
      !$table_data ||
      current_user_can('edit_wc_product_table', (int) $table_data['id'])
    )
  ) {
    return '<div class="wcpt-notice"><span class="wcpt-notice-heading">' . __('WooCommerce Product Table Notice', 'wc-product-table') . '</span>' . $message . '</div>';

  } else {
    return false;

  }

}

/**
 * Error checking for shortcode - in case user has made some mistake
 */
function wcpt_device_columns_empty($device_columns)
{
  $no_element = true;
  foreach ($device_columns as $column) {
    // iterate rows
    //-- heading
    if (isset($column['heading']['content'])) {
      foreach ($column['heading']['content'] as $row) {
        if (count($row['elements'])) {
          $no_element = false;
        }
      }
    }
    //-- cell
    if (isset($column['cell']['template'])) {
      foreach ($column['cell']['template'] as $row) {
        if (count($row['elements'])) {
          $no_element = false;
        }
      }
    }
  }

  if ($no_element) {
    return true;

  } else {
    return false;

  }

}

function wcpt_get_cheapest_variation($product, $available_variations)
{

  $lowest_price = false;
  $variation_id = false;

  foreach ($available_variations as $variation_details) {
    if (false === $lowest_price || $variation_details['display_price'] < $lowest_price) {
      $lowest_price = $variation_details['display_price'];
      $variation_id = $variation_details['variation_id'];
    }
  }

  return wc_get_product($variation_id);
}

function wcpt_get_most_expensive_variation($product, $available_variations)
{

  $highest_price = false;
  $variation_id = false;

  foreach ($available_variations as $variation_details) {
    if (false === $highest_price || $variation_details['display_price'] > $highest_price) {
      $highest_price = $variation_details['display_price'];
      $variation_id = $variation_details['variation_id'];
    }
  }

  return wc_get_product($variation_id);
}

// @TODO: check on this function
function wcpt_woocommerce_available_variation_filter($variation_details, $product, $variation)
{
  global $wcpt_table_data;

  foreach ($wcpt_table_data['columns'] as $key => $column) {
    $variation_details['column_' . $key] = wcpt_parse_2($column['template'], $product);
  }

  return $variation_details;
}

function wcpt_update_user_filters($new_filter, $single = true)
{
  $found_filter = false;

  foreach ($GLOBALS['wcpt_user_filters'] as &$filter_info) {
    if ($filter_info['filter'] !== $new_filter['filter']) {
      continue;
    }

    if (
      in_array(
        $new_filter['filter'],
        array('orderby', 'price_range', 'search', 'on_sale', 'rating', 'availability')
      )
    ) {
      $found_filter = true;
      break;
    }

    // taxonomy
    if (
      in_array($filter_info['filter'], array('taxonomy', 'attribute', 'category')) &&
      $filter_info['taxonomy'] == $new_filter['taxonomy']
    ) {
      $found_filter = true;
      break;
    }

    // custom field
    if (
      $filter_info['filter'] == 'custom_field' &&
      strtolower($filter_info['meta_key']) == strtolower($new_filter['meta_key'])
    ) {
      $found_filter = true;
      break;
    }
  }

  if ($found_filter) {
    foreach ($new_filter as $key => $val) {
      // add value
      if ($key == 'values') {
        if (!$single) {
          if (!is_array($filter_info['values'])) {
            $filter_info['values'] = array();
          }

          if ($filter_info['filter'] == 'custom_field') { // avoid duplicates
            $new_filter['values'] = array_map('strtolower', $new_filter['values']);
            $filter_info['values'] = array_map('strtolower', $filter_info['values']);
          }

          $diff = array_diff($new_filter['values'], $filter_info['values']);
          $filter_info['values'] = array_merge($filter_info['values'], $diff);
        } else {
          $filter_info['values'] = $val;
        }

        // add clear label
      } else if ($key == 'clear_labels_2') {
        if (!$single) {
          if (!is_array($filter_info['clear_labels_2'])) {
            $filter_info['clear_labels_2'] = array();
          }
          if ($new_filter['clear_labels_2']) {
            foreach ($new_filter['clear_labels_2'] as $key => $val) {
              if (empty($filter_info['clear_labels_2'][$key]) || $filter_info['clear_labels_2'][$key] !== $val) {
                $filter_info['clear_labels_2'][$key] = $val;
              }
            }
          }
        } else {
          $filter_info['clear_labels_2'] = $val;
        }

        // other key
      } else {
        $filter_info[$key] = $val;

      }

    }

  } else {
    $GLOBALS['wcpt_user_filters'][] = $new_filter;

  }
}

// Relabel items
function wcpt_relabel_items(&$items, $relabels = array())
{
  foreach ($items as &$item) {
    foreach ($relabels as $relabel) {
      if (strtolower($item['item']) === strtolower($relabel['item'])) {
        $item['label'] = wcpt_parse_2($relabel['label']);
      }
    }
  }

  return $items;
}

// wcpt price
function wcpt_price($price, $trim_zeros = false)
{

  $num = number_format(
    (float) $price,
    wc_get_price_decimals(),
    wc_get_price_decimal_separator(),
    wc_get_price_thousand_separator()
  );

  if (
    $trim_zeros &&
    strstr($num, '.')
  ) {
    $num = rtrim(rtrim($num, '0'), '.');
  }

  $price = '<span class="wcpt-amount">' . $num . '</span>';
  $currency_symbol = esc_attr(get_woocommerce_currency_symbol());
  $currency = '<span class="wcpt-currency">' . $currency_symbol . '</span>';
  return str_replace(array('%1$s', '%2$s'), array($currency, $price), get_woocommerce_price_format());
}

// safari $ fix
add_filter('wcpt_element_markup', 'wcpt_safari_dollar_fix', 10, 2);
function wcpt_safari_dollar_fix($elm_markup, $elm)
{
  if (
    empty($elm['type']) ||
    $elm['type'] !== 'price' ||
    empty($elm['use_default_template'])
  ) {
    return $elm_markup;
  }

  $currency_symbol = esc_attr(get_woocommerce_currency_symbol());

  return $elm_markup;
}

// product form modal 
// triggered for: variable, addons, measurement, name your price
add_action('wc_ajax_wcpt_get_product_form_modal', 'wcpt_get_product_form_modal');
add_action('wp_ajax_nopriv_wcpt_get_product_form_modal', 'wcpt_get_product_form_modal');
add_action('wp_ajax_wcpt_get_product_form_modal', 'wcpt_get_product_form_modal');
function wcpt_get_product_form_modal()
{
  $product_id = (int) $_REQUEST['product_id'];
  if (get_post_status($product_id) == 'publish') {
    echo wcpt_get_product_form(array('id' => $product_id));
  }
  wp_die();
}

function wcpt_get_product_form($atts)
{
  global $post;

  // Store and restore global post
  $stored_post = !empty($post) ? $post : null;

  $product_id = $atts['id'];
  $product = apply_filters('wcpt_product', wc_get_product($product_id));
  $GLOBALS['product'] = $product;

  $product_type = $product->get_type();

  ob_start();
  include(WCPT_PLUGIN_PATH . 'templates/modal_form.php');

  if ($stored_post) {
    $post = $stored_post;
  }

  return ob_get_clean();
}

function wcpt_include_descendant_slugs($slugs = array(), $taxonomy = null)
{

  if (!$slugs) {
    return array();
  }

  if (!$taxonomy) {
    $taxonomy = 'product_cat';
  }

  // convert slugs to term ids
  $term_ids = get_terms(
    array(
      'taxonomy' => $taxonomy,
      'fields' => 'ids',
      'slug' => $slugs
    )
  );

  // -- include children
  foreach ($term_ids as $term_id) {

    $child_slugs = get_terms(
      array(
        'taxonomy' => $taxonomy,
        'fields' => 'slugs',
        'child_of' => $term_id
      )
    );

    if ($child_slugs) {
      $slugs = array_merge($slugs, $child_slugs);
    }

  }

  return $slugs;
}

// icon
function wcpt_icon($icon_name, $html_class = '', $style = null, $tooltip = '', $title = '', $attrs = array())
{
  $icon_file = WCPT_PLUGIN_PATH . 'assets/feather/' . sanitize_file_name($icon_name) . '.svg';

  if (!file_exists($icon_file)) {
    return '';
  }

  $tooltip_html_class = '';
  if ($tooltip) {
    $tooltip_html_class = 'wcpt-tooltip';
  }

  $attr_string = '';
  if ($attrs) {
    $attr_string = ' ';
    foreach ($attrs as $key => $val) {
      $attr_string .= esc_attr($key) . '="' . esc_attr($val) . '" ';
    }
  }

  echo '<span 
      class="wcpt-icon wcpt-icon-' . esc_attr($icon_name) . ' ' . esc_attr($html_class) . ' ' . esc_attr($tooltip_html_class) . '" '
    . ($style ? ' style="' . esc_attr($style) . '"' : '') . ' '
    . ($title ? ' title="' . esc_attr($title) . '"' : '') . ' '
    . $attr_string .
    ' >';

  if ($tooltip) {
    echo '<span class="wcpt-tooltip-content">' . $tooltip . '</span>';
  }

  include($icon_file);
  echo '</span>';

}

function wcpt_get_icon($icon_name, $html_class = '', $style = null, $tooltip = '', $title = '', $attrs = array())
{
  ob_start();
  wcpt_icon($icon_name, $html_class, $style, $tooltip, $title, $attrs);
  return ob_get_clean();
}

function wcpt_get_column_by_index($column_index = 0, $device = 'laptop', &$table_data = false)
{

  $device_columns = wcpt_get_device_columns($device, $table_data);

  if (!$device_columns) {
    return false;

  } else {
    return $device_columns[$column_index];
  }

}

function wcpt_sortby_get_matching_option_index($match_user_filter, $available_options)
{
  if (!$available_options) {
    return false;
  }

  foreach ($available_options as $option_index => $option) {
    if (wcpt_check_sort_match($option, $match_user_filter)) {
      return $option_index;
    }
  }
  return false;
}

function wcpt_check_sort_match($option, $current_sorting)
{

  // match begins from 'orderby'
  if ($option['orderby'] !== $current_sorting['orderby']) {
    return false;
  }

  // no other params needs to match for these (there is no optional 'order' for these)
  if (in_array($option['orderby'], array('price', 'price-desc', 'rating', 'popularity', 'rand'))) {
    return true;
  }

  // order must also match for remaining - title, custom field, sku, ID, etc
  if (strtolower($option['order']) != strtolower($current_sorting['order'])) {
    return false;
  }

  // enough match for these
  if (in_array($option['orderby'], array('title', 'menu_order', 'id', 'sku', 'sku_num', 'date', 'modified', 'content'))) {
    return true;
  }

  // custom field
  if (
    !empty($option['meta_key']) &&
    !empty($current_sorting['meta_key']) &&
    $option['meta_key'] == $current_sorting['meta_key']
  ) {
    return true;
  }

  // category
  $current_sorting__focus_category = empty($current_sorting['orderby_focus_category']) ? '' : $current_sorting['orderby_focus_category'];
  $current_sorting__ignore_category = empty($current_sorting['orderby_ignore_category']) ? '' : $current_sorting['orderby_ignore_category'];

  $option__focus_category = empty($option['orderby_focus_category']) ? '' : $option['orderby_focus_category'];
  $option__ignore_category = empty($option['orderby_ignore_category']) ? '' : $option['orderby_ignore_category'];

  if (
    $option['orderby'] == 'category' &&
    $current_sorting__focus_category == $option__focus_category &&
    $current_sorting__ignore_category == $option__ignore_category
  ) {
    return true;
  }

  // attribute
  $current_sorting__attribute = empty($current_sorting['orderby_attribute']) ? '' : $current_sorting['orderby_attribute'];
  $current_sorting__focus_attribute_terms = empty($current_sorting['orderby_ignore_attribute_term']) ? '' : $current_sorting['orderby_ignore_attribute_term'];
  $current_sorting__ignore_attribute_terms = empty($current_sorting['orderby_ignore_attribute_term']) ? '' : $current_sorting['orderby_ignore_attribute_term'];

  $option__attribute = empty($option['orderby_attribute']) ? '' : $option['orderby_attribute'];
  $option__focus_attribute_terms = empty($option['orderby_ignore_attribute_term']) ? '' : $option['orderby_ignore_attribute_term'];
  $option__ignore_attribute_terms = empty($option['orderby_ignore_attribute_term']) ? '' : $option['orderby_ignore_attribute_term'];

  if (
    in_array($option['orderby'], array('attribute', 'attribute_num')) &&
    $current_sorting__attribute == $option__attribute &&
    $current_sorting__focus_attribute_terms == $option__focus_attribute_terms &&
    $current_sorting__ignore_attribute_terms == $option__ignore_attribute_terms
  ) {
    return true;
  }


  // taxonomy
  $current_sorting__taxonomy = empty($current_sorting['orderby_taxonomy']) ? '' : $current_sorting['orderby_taxonomy'];
  $current_sorting__focus_taxonomy_terms = empty($current_sorting['orderby_focus_taxonomy_term']) ? '' : $current_sorting['orderby_focus_taxonomy_term'];
  $current_sorting__ignore_taxonomy_terms = empty($current_sorting['orderby_ignore_taxonomy_term']) ? '' : $current_sorting['orderby_ignore_taxonomy_term'];

  $option__taxonomy = empty($option['orderby_taxonomy']) ? '' : $option['orderby_taxonomy'];
  $option__focus_taxonomy_terms = empty($option['orderby_focus_taxonomy_term']) ? '' : $option['orderby_focus_taxonomy_term'];
  $option__ignore_taxonomy_terms = empty($option['orderby_ignore_taxonomy_term']) ? '' : $option['orderby_ignore_taxonomy_term'];

  if (
    $option['orderby'] == 'taxonomy' &&
    $current_sorting__focus_taxonomy_terms == $option__focus_taxonomy_terms &&
    $current_sorting__ignore_taxonomy_terms == $option__ignore_taxonomy_terms
  ) {
    return true;
  }

}

function wcpt_get_column_sort_filter_info()
{

  $field_name_prefix = $GLOBALS['wcpt_table_data']['id'] . '_';

  $column_index = (int) substr($_GET[$field_name_prefix . 'orderby'], 7);
  $device = $_GET[$field_name_prefix . 'device'];
  $order = $_GET[$field_name_prefix . 'order'];

  $column = wcpt_get_column_by_index($column_index, $device);

  $filter_info = array(
    'filter' => 'orderby',
  );

  if ($column['sorting_enabled']) {
    $filter_info['orderby'] = $column['orderby'];
    $filter_info['order'] = $order;
    if ($column['orderby'] == 'meta_value' || $column['orderby'] == 'meta_value_num') {
      $filter_info['meta_key'] = $column['meta_key'];
    }

    // special case price-desc
    if ($column['orderby'] == 'price' && $order == 'DESC') {
      $filter_info['orderby'] = 'price-desc';
    }
  }

  return $filter_info;

}

function wcpt_get_nav_filter($name, $second = false)
{
  foreach ($GLOBALS['wcpt_user_filters'] as $filter_info) {
    if ($filter_info['filter'] == $name) {
      if (!$second) {
        return $filter_info;
      } else {
        switch ($name) {
          case 'custom_field':
            if (strtolower($filter_info['meta_key']) == strtolower($second)) {
              return $filter_info;
            }
            break;

          default: // attribute / taxonomy / product_cat
            if ($filter_info['taxonomy'] == $second) {
              return $filter_info;
            }
            break;
        }
      }

    }
  }

  return false;
}

function wcpt_clear_nav_filter($name, $second = false)
{
  foreach ($GLOBALS['wcpt_user_filters'] as $key => &$filter_info) {
    if ($filter_info['filter'] == $name) {
      if (!$second) {
        unset($GLOBALS['wcpt_user_filters'][$key]);
      } else {
        switch ($name) {
          case 'custom_field':
            if (strtolower($filter_info['meta_key']) == strtolower($second)) {
              unset($GLOBALS['wcpt_user_filters'][$key]);
            }
            break;

          case 'search':
            if (
              $second == 'native' &&
              !empty($GLOBALS['wcpt_user_filters'][$key]['searches'])
            ) {
              foreach ($GLOBALS['wcpt_user_filters'][$key]['searches'] as $key2 => &$search) {
                if (!empty($search['native'])) {
                  unset($GLOBALS['wcpt_user_filters'][$key]['searches'][$key2]);
                }
              }
            }
            break;

          default: // attribute / taxonomy / product_cat
            if (!empty($filter_info['taxonomy']) && $filter_info['taxonomy'] == $second) {
              unset($GLOBALS['wcpt_user_filters'][$key]);
            }
            break;
        }
      }

    }
  }
}

function wcpt_get_sorting_html_classes($col_orderby, $col_meta_key = false, $col_orderby_attribute = false, $col_orderby_taxonomy = false)
{

  extract(wcpt_get_current_sorting());

  $col_sorted = false;

  if ($current_orderby == $col_orderby) {
    if (in_array($current_orderby, array('meta_value', 'meta_value_num'))) {
      if ($current_meta_key == $col_meta_key) {
        $col_sorted = true;
      }

    } else if (in_array($current_orderby, array('attribute', 'attribute_num'))) {
      if (
        !empty($current_orderby_attribute) &&
        !empty($col_orderby_attribute) &&
        $current_orderby_attribute == $col_orderby_attribute
      ) {
        $col_sorted = true;
      }

    } else if (in_array($current_orderby, array('rating'))) {
      // fixed order
      $current_order = 'desc';
      $col_sorted = true;

    } else {
      $col_sorted = true;
    }

  } else if ($current_orderby == 'price-desc' && $col_orderby == 'price') {
    $current_order = 'desc';
    $col_sorted = true;

  } else if (in_array($current_orderby, array('meta_value', 'meta_value_num')) && $current_meta_key == '_sku' && in_array($col_orderby, array('sku', 'sku_num'))) {
    $col_sorted = true;

  }

  if ($col_sorted) {

    // if( $col_orderby == 'rating' || $col_orderby == 'date' ){
    if ($col_orderby == 'rating') {
      return array(
        'sorting_class' => 'wcpt-sorting-' . $current_order,
        'sorting_class_asc' => 'wcpt-hide',
        'sorting_class_desc' => $current_order == 'desc' ? 'wcpt-active' : 'wcpt-inactive',
      );
    }

    return array(
      'sorting_class' => 'wcpt-sorting-' . $current_order,
      'sorting_class_asc' => $current_order == 'asc' ? 'wcpt-active' : 'wcpt-inactive',
      'sorting_class_desc' => $current_order == 'desc' ? 'wcpt-active' : 'wcpt-inactive',
    );
  }

  // column not sorted
  return array(
    'sorting_class' => '',
    'sorting_class_asc' => ($col_orderby == 'rating') ? 'wcpt-hide' : 'wcpt-inactive',
    'sorting_class_desc' => 'wcpt-inactive',
  );

}

function wcpt_get_current_sorting()
{
  $current_sorting = array();
  foreach (wcpt_get_nav_filter('orderby') as $key => $val) {
    $current_sorting['current_' . $key] = ($key == 'order') ? strtolower($val) : $val;
  }

  return $current_sorting;
}

function wcpt_get_column_sorting_info($col_index, $device = 'laptop')
{
  $col_index = (int) $col_index;
  if (!in_array($device, array('laptop', 'tablet', 'phone'))) {
    $device = 'laptop';
  }

  // rows
  if (!empty($GLOBALS['wcpt_table_data']['columns'][$device][$col_index]['heading']['content'])) {
    foreach ($GLOBALS['wcpt_table_data']['columns'][$device][$col_index]['heading']['content'] as $row) {
      // elements
      foreach ($row['elements'] as $element) {
        if ($element['type'] == 'sorting') {
          return $element;
        }
      }
    }
  }

  return NULL;
}

/* get table data from post or cache */
function wcpt_get_table_data($table_id = false, $context = 'view')
{
  if ($table_id) {
    // get true wcpt post id
    if (
      get_post_type($table_id) !== 'wc_product_table' ||
      (get_post_status($table_id) !== 'publish' && $context !== 'edit')
    ) {
      return false;
    }

    $table_data = json_decode(get_post_meta($table_id, 'wcpt_data', true), true);
    $table_data['id'] = $table_id;

    return apply_filters('wcpt_data', $table_data, $context);

  } else {
    // return current cached table
    return !empty($GLOBALS['wcpt_table_data']) ? $GLOBALS['wcpt_table_data'] : false;

  }
}

// ensure sc_attrs
add_filter('wcpt_data', function ($table_data, $context) {
  if (
    $context === "view" &&
    empty($table_data['query']['sc_attrs'])
  ) {
    $table_data['query']['sc_attrs'] = array();
  }
  return $table_data;
}, 10, 2);

// get price with filters applied
function wcpt_get_price_to_display($product = null)
{
  if (!$product) {
    global $product;
  }

  if (apply_filters('wcpt_product_is_on_sale', $product->is_on_sale(), $product)) {

    $price = apply_filters(
      'wcpt_product_get_sale_price',
      wc_get_price_to_display(
        $product,
        array(
          'qty' => 1,
          'price' => $product->get_sale_price(),
        )
      ),
      $product
    );

  } else {

    $price = apply_filters(
      'wcpt_product_get_regular_price',
      wc_get_price_to_display(
        $product,
        array(
          'qty' => 1,
          'price' => $product->get_regular_price(),
        )
      ),
      $product
    );

  }

  return $price;
}

// when user has added query > category to a table, this will automatically include new subcategories as well
add_filter('wcpt_data', 'wcpt_include_new_child_categories', 10, 2);
function wcpt_include_new_child_categories($table_data, $context)
{
  if (!empty($GLOBALS['sitepress'])) { // messes with WPML, ends up including translated categories
    return $table_data;
  }

  // auto-include new category children
  if (!empty($table_data) && !empty($table_data['query']['category'])) {
    $terms = wcpt_get_terms('product_cat', $table_data['query']['category'], false);
    if ($terms && !is_wp_error($terms)) {
      $term_taxonomy_id = array();
      foreach ($terms as $term) {
        $term_taxonomy_id[] = (string) $term->term_taxonomy_id;
      }

      $table_data['query']['category'] = $term_taxonomy_id;
    }
  }

  return $table_data;
}

/* columns related */
function wcpt_get_device_columns($device, &$table_data = false)
{
  if (!$table_data) {
    $table_data =& $GLOBALS['wcpt_table_data'];
  }

  return !empty($table_data['columns'][$device]) ? $table_data['columns'][$device] : false;
}

/* columns related */
function wcpt_get_device_columns_2($device, &$table_data = false)
{
  if (!$table_data) {
    $table_data =& $GLOBALS['wcpt_table_data'];
  }

  return !empty($table_data['columns'][$device]) ? $table_data['columns'][$device] : false;
}

/* elements related */
function wcpt_get_shortcode_element_manager($shortcode_tag)
{
  if ('_filter' == substr($shortcode_tag, -7) || in_array($shortcode_tag, array('sort_by', 'result_count'))) {
    return 'navigation';
  } else {
    return 'column';
  }
}

function wcpt_get_column_elements($table_data = false)
{
  if (!$table_data) {
    $table_data = wcpt_get_table_data();
  }
  return $table_data['elements']['column'];
}

function wcpt_get_navigation_elements($table_data = false)
{
  if (!$table_data) {
    $table_data = wcpt_get_table_data();
  }
  return $table_data['elements']['navigation'];
}

/* debug */
function wcpt_console_log()
{
  $arguments = func_get_args();
  if (!count($arguments)) {
    return;
  }
  // $arguments[] = debug_backtrace();  
  ?>
    <script>
      console.log(
        <?php
        foreach ($arguments as $arg) {
          echo json_encode($arg);
          echo ', ';
        }
        ?>
      );
    </script>
    <?php
}


/* navigation */
// header
function wcpt_parse_navigation($table_data = false)
{

  if (!$table_data) {
    $table_data = wcpt_get_table_data();
  }

  if (empty($table_data['navigation'])) {
    return;
  }

  ob_start();

  // laptop
  if (
    isset($table_data['navigation']['laptop']['left_sidebar']) &&
    !empty($table_data['navigation']['laptop']['left_sidebar'][0]) &&
    count($table_data['navigation']['laptop']['left_sidebar'][0]['elements'])
  ) {
    //  {{maybe-always}} placeholder for hiding in responsive mode
    ?>
      <div
        class="<?php echo esc_attr(apply_filters('wcpt_nav_sidebar_class', 'wcpt-navigation wcpt-left-sidebar {{maybe-always}}')); ?>"
        style="<?php echo esc_attr(apply_filters('wcpt_nav_sidebar_style', '')); ?>">
        <?php
        echo wcpt_parse_2($table_data['navigation']['laptop']['left_sidebar']);
        ?>
      </div>
      <?php
  }

  ?>
    <div
      class="<?php echo esc_attr(apply_filters('wcpt_nav_header_class', 'wcpt-navigation wcpt-header {{maybe-always}}')); ?>"
      style="<?php echo esc_attr(apply_filters('wcpt_nav_header_style', '')); ?>"><?php

          foreach ($table_data['navigation']['laptop']['header']['rows'] as $row) {

            if (empty($row['ratio']))
              $row['ratio'] = '100-0'; // default val
            $empty_row = true;

            ob_start(); // will feed $row_markup
            ?>
        <div class="wcpt-filter-row wcpt-ratio-<?php echo esc_attr($row['ratio']); ?> %maybe_hide%">
          <?php
          foreach (array('left', 'center', 'right') as $position) {
            if (false !== strpos($row['columns_enabled'], $position)) {
              echo '<div class="wcpt-filter-column wcpt-' . esc_attr($position) . '">';
              if ($column_content = wcpt_parse_2($row['columns'][$position]['template'])) {
                $empty_row = false;
              }
              echo $column_content;
              echo '</div>';
            }
          }
          ?>
        </div>
        <?php
        $row_markup = ob_get_clean();

        if ($empty_row) {
          $row_markup = '';
        } else {
          $row_markup = str_replace('%maybe_hide%', '', $row_markup);
        }

        echo $row_markup;

          }
          do_action('wcpt_header_navgiation_close');
          echo '</div>';

          // phone and tablet
          ?>
      <div class="wcpt-responsive-navigation"><?php
      if (empty($table_data['navigation']['phone'])) {
        $table_data['navigation']['phone'] = '';
      }
      $res_nav = wcpt_parse_2($table_data['navigation']['phone']);
      echo $res_nav;
      ?></div>
      <?php
      include(WCPT_PLUGIN_PATH . 'templates/modals.php');

      $mkp = ob_get_clean();
      $always_show = 'wcpt-always-show';
      if ($res_nav) {
        $always_show = '';
      }
      $mkp = str_replace('{{maybe-always}}', $always_show, $mkp);

      return $mkp;
}

// filter header
add_filter('wcpt_navigation', 'wcpt_navigation_filter');
function wcpt_navigation_filter($navigation_header)
{
  global $wcpt_products;

  $paged = max(1, $wcpt_products->get('paged'));
  $per_page = $wcpt_products->get('posts_per_page');
  $total = $wcpt_products->found_posts;
  $first = ($per_page * $paged) - $per_page + 1;
  $last = min($total, $wcpt_products->get('posts_per_page') * $paged);

  $result_count_html_class = '';

  if ($total == 1) {
    $result_count_html_class = 'wcpt-single-result';
  } else if ($total == 0) {
    $result_count_html_class = 'wcpt-no-results';
  } else if ($total <= (int) $per_page || -1 === (int) $per_page) {
    $result_count_html_class = 'wcpt-single-page';
  }

  $search = array(
    '[result-count-html-class]',
    '[displayed_results]',
    '[total_results]',
    '[first_result]',
    '[last_result]',
  );
  $replace = array(
    $result_count_html_class,
    $last - $first + 1,
    $total,
    $first,
    $last,
  );

  return str_replace($search, $replace, $navigation_header);

}

function wcpt_corner_options($args = array())
{
  $defaults = array('prepend' => '', 'append' => '');
  $args = shortcode_atts($defaults, $args);

  ?>
      <div class="wcpt-editor-corner-options">
        <?php echo $args['prepend']; ?>
        <i class="wcpt-editor-row-move-up wcpt-sortable-handle" wcpt-move-up title="Move up">
          <?php wcpt_icon('chevron-up'); ?>
        </i>
        <i class="wcpt-editor-row-move-down wcpt-sortable-handle" wcpt-move-down title="Move down">
          <?php wcpt_icon('chevron-down'); ?>
        </i>
        <i class="wcpt-editor-row-duplicate" wcpt-duplicate-row title="Duplicate">
          <?php wcpt_icon('copy'); ?>
        </i>
        <i class="wcpt-editor-row-remove" wcpt-remove-row title="Delete">
          <?php wcpt_icon('trash-2'); ?>
        </i>
        <?php echo $args['append']; ?>
      </div>
      <?php
}

function wcpt_get_cart_item_quantity($product_id)
{
  global $woocommerce;
  $in_cart = 0;

  if (is_object($woocommerce->cart)) {
    $contents = $woocommerce->cart->cart_contents;
    if ($contents) {
      foreach ($contents as $key => $details) {
        if ($details['product_id'] == $product_id) {
          $in_cart += $details['quantity'];
        }
      }
    }
  }

  return $in_cart;
}

add_action('wp_ajax_wcpt_get_terms', 'wcpt_get_terms_ajax');
function wcpt_get_terms_ajax()
{
  $term_taxonomy_id = !empty($_POST['limit_terms']) ? $_POST['limit_terms'] : false;
  $terms = wcpt_get_terms($_POST['taxonomy'], $term_taxonomy_id, false);

  $relabels = array();
  $timestamp = time();
  foreach ($terms as $term) {
    // code...
    $relabels[] = array(
      'term' => wp_specialchars_decode($term->name),
      'ttid' => $term->term_taxonomy_id,
      'label' => array(
        array(
          'id' => $timestamp++,
          'style' => array(),
          'elements' => array(
            array(
              'id' => $timestamp++,
              'style' => array(),
              'type' => 'text',
              'text' => '[term]',
            ),
          ),
        )
      ),
      'tooltip' => '',
      'link' => '',
      'target' => '_self',
      'id' => $timestamp++,
    );
  }

  wp_send_json($relabels);
}

// gets terms include children
function wcpt_get_terms($taxonomy, $terms = false, $hide_empty = false)
{
  // user has set terms
  if (!empty($terms)) {

    $args = array(
      'taxonomy' => $taxonomy,
      'hide_empty' => $hide_empty,
      'orderby' => 'menu_order',
      'fields' => 'ids',
    );

    $terms_string = gettype($terms) == 'string' ? $terms : implode(',', $terms);
    $terms_array = array_map('trim', explode(',', $terms_string));

    // slugs
    if (preg_match("/[a-z]/i", $terms_string)) {
      $args['slug'] = $terms_array;

      // term taxonomy ids
    } else {
      $args['term_taxonomy_id'] = $terms_array;

    }

    // get term ids
    $term_ids = get_terms($args);

    // include all child terms
    foreach ($term_ids as $term_id) {
      // get its children
      $child_terms = get_term_children($term_id, $taxonomy);
      // include if not already there
      if ($child_terms && !is_wp_error($child_terms)) {
        $term_ids = array_unique(array_merge($term_ids, $child_terms));
      }
    }

    global $sitepress;
    if (
      !empty($sitepress) &&
      $taxonomy == 'product_cat'
    ) {
      $filter_exists = remove_filter('terms_clauses', array($sitepress, 'terms_clauses'), 10);
    }

    // get correct order
    $terms = get_terms(
      array(
        'taxonomy' => $taxonomy,
        'hide_empty' => $hide_empty,
        'include' => $term_ids,
        // 'orderby' => 'parent',
        'orderby' => 'menu_order',
      )
    );

    if (
      !empty($sitepress) &&
      !empty($filter_exists) &&
      $taxonomy == 'product_cat'
    ) {
      add_filter('terms_clauses', array($sitepress, 'terms_clauses'), 10, 3);
    }

    // user didn't set terms, so get all
  } else {
    $terms = get_terms(
      array(
        'taxonomy' => $taxonomy,
        'hide_empty' => $hide_empty,
        'orderby' => 'menu_order',
      )
    );

  }

  return $terms;
}
// get product terms by hierarchy
function wcpt_get_product_terms_by_menu_order($product_id, $taxonomy)
{
  $terms = get_the_terms($product_id, $taxonomy);

  if ($terms && !is_wp_error($terms)) {
    // Create an array to store term objects with their hierarchy levels
    $term_hierarchy = array();

    foreach ($terms as $category) {
      $ancestors = get_ancestors($category->term_id, 'product_cat');
      $category->hierarchy_level = count($ancestors);
      $term_hierarchy[] = $category;
    }

    // Sort the category hierarchy array by hierarchy levels
    usort($term_hierarchy, function ($a, $b) {
      return $a->hierarchy_level - $b->hierarchy_level;
    });

    return $term_hierarchy;
  } else {
    return false;
  }
}

function wcpt_include_taxonomy_walker()
{
  if (!class_exists('WCPT_Taxonomy_Walker')) {
    class WCPT_Taxonomy_Walker extends Walker
    {

      var $db_fields = array('parent' => 'parent', 'id' => 'term_id');
      var $args;

      function __construct($args)
      {
        if (empty($args)) {
          $args = array();
        }

        if (empty($args['taxonomy'])) {
          $args['taxonomy'] = 'product_cat';
        }

        if (!$args['taxonomy_obj'] = get_taxonomy($args['taxonomy'])) {
          return false;
        }

        if (!empty($args['exclude'])) {
          $exclude_term_ids = array();
          foreach ($args['exclude'] as $term_name) {
            if ($term = get_term_by('name', $term_name, $args['taxonomy'])) {
              $exclude_term_ids[] = $term->term_id;
            }
          }
          $args['exclude'] = $exclude_term_ids;

        } else {
          $args['exclude'] = array();

        }

        if (empty($args['single'])) {
          $args['single'] = false;
        }

        if (empty($args['hide_empty'])) {
          $args['hide_empty'] = false;
        }

        if (!isset($args['pre_open_depth'])) {
          $args['pre_open_depth'] = 1;
        }

        if (!isset($args['option_class'])) {
          $args['option_class'] = 'wcpt-dropdown-option';
        }

        if (empty($args['redirect'])) {
          $args['redirect'] = false;
        }

        if (empty($args['category'])) {
          $args['category'] = false;
        }

        if (empty($args['_field_name'])) {
          $args['_field_name'] = $args['field_name'];
        }

        $this->args = $args;
      }

      public function walk($elements, $max_depth, ...$args)
      {
        $output = '';

        if ($max_depth < -1 || empty($elements)) {
          return $output;
        }

        $parent_field = $this->db_fields['parent'];

        if (-1 == $max_depth) {
          $empty_array = array();
          foreach ($elements as $e) {
            $this->display_element($e, $empty_array, 1, 0, $args, $output);
          }
          return $output;
        }

        /* wcpt modified begins */
        $term_ids = array_column($elements, 'term_id');

        $top_level_elements = array();
        $children_elements = array();
        foreach ($elements as $e) {
          if (
            empty($e->$parent_field) ||
            !in_array($e->$parent_field, $term_ids)
          ) {
            $top_level_elements[] = $e;
          } else {
            $children_elements[$e->$parent_field][] = $e;
          }
        }
        /* wcpt modified ends */

        if (empty($top_level_elements)) {

          $first = array_slice($elements, 0, 1);
          $root = $first[0];

          $top_level_elements = array();
          $children_elements = array();
          foreach ($elements as $e) {
            if ($root->$parent_field == $e->$parent_field) {
              $top_level_elements[] = $e;
            } else {
              $children_elements[$e->$parent_field][] = $e;
            }
          }
        }

        foreach ($top_level_elements as $e) {
          $this->display_element($e, $children_elements, $max_depth, 0, $args, $output);
        }

        if (($max_depth == 0) && count($children_elements) > 0) {
          $empty_array = array();
          foreach ($children_elements as $orphans) {
            foreach ($orphans as $op) {
              $this->display_element($op, $empty_array, 1, 0, $args, $output);
            }
          }
        }

        return $output;
      }

      function start_el(&$output, $category, $depth = 0, $args = array(), $id = 0)
      {
        $category = (object) $category;
        $children = get_terms(
          $this->args['taxonomy'],
          array(
            'parent' => $category->term_id,
            'hide_empty' => 0,
            'exclude' => $this->args['exclude'],
            'fields' => 'ids',
          )
        );

        $has_children = false;
        $child_checked = false;
        if (!is_wp_error($children) && count($children)) {

          $has_children = true;
          $child_checked = false;
          if (
            !empty($_GET[$this->args['_field_name']]) &&
            count(array_intersect(get_term_children($category->term_id, $this->args['taxonomy']), $_GET[$this->args['_field_name']]))
          ) {
            $child_checked = true;
          }
        }

        $checked = false;
        if (
          !empty($_GET[$this->args['_field_name']]) &&
          in_array($category->term_taxonomy_id, $_GET[$this->args['_field_name']])
        ) {
          $checked = true;
          // use filter in query
          $filter_info = array(
            'filter' => ($this->args['taxonomy'] == 'product_cat') ? 'category' : 'taxonomy',
            'taxonomy' => $this->args['taxonomy'],
            'values' => array($category->term_taxonomy_id),
            'operator' => !empty($this->args['operator']) ? $this->args['operator'] : 'IN',
            'clear_label' => $this->args['taxonomy_obj']->labels->singular_name,
          );

          if (!empty($category->clear_label)) {
            $filter_info['clear_labels_2'] = array(
              $category->value => str_replace(
                array('[option]', '[filter]'),
                array($category->name, $this->args['taxonomy_obj']->labels->singular_name),
                $category->clear_label
              ),
            );
          } else {
            $clear_filter_markup = '<span class="wcpt-filter-label">' . $this->args['taxonomy_obj']->labels->singular_name . '</span><span class="wcpt-separator wcpt-colon">: </span><span class="wcpt-selected-filter">' . $category->name . '</span>';


            $filter_info['clear_labels_2'] = array(
              $category->value => $clear_filter_markup,
            );
          }

          wcpt_update_user_filters($filter_info, $this->args['single']);
        }

        ob_start();

        if ($this->args['redirect']) {

          if ($this->args['category'] == $category->slug):
            ?>
                <div
                  class="wcpt-dropdown-option wcpt-current-term <?php echo $this->args['option_class'] ?> <?php echo $has_children ? 'wcpt-filter-hierarchy-accordion' : ''; ?> <?php echo ($checked || $child_checked) ? 'wcpt-filter-hierarchy-accordion--open' : ''; ?> <?php echo $this->args['pre_open_depth'] > $depth ? 'wcpt-filter-hierarchy-accordion--open' : ''; ?>"
                  data-wcpt-value="<?php echo $category->term_taxonomy_id; ?>"
                  data-wcpt-open="<?php echo $this->args['pre_open_depth']; ?>" data-wcpt-depth="<?php echo $depth; ?>">
                  <label class="<?php echo $checked ? 'wcpt-active' : ''; ?>"
                    data-wcpt-value="<?php echo $category->term_taxonomy_id; ?>" data-wcpt-slug="<?php echo $category->slug; ?>">
                    <?php echo $category->label; ?>
                    <?php echo $has_children ? wcpt_icon('chevron-down', 'wcpt-filter-hierarchy-accordion__trigger') : ''; ?>
                  </label>
                </div>
              <?php else: ?>
                <div
                  class="wcpt-nav-redirect-option <?php echo $this->args['option_class'] ?> <?php echo $has_children ? 'wcpt-filter-hierarchy-accordion' : ''; ?> <?php echo ($checked || $child_checked) ? 'wcpt-filter-hierarchy-accordion--open' : ''; ?> <?php echo $this->args['pre_open_depth'] > $depth ? 'wcpt-filter-hierarchy-accordion--open' : ''; ?>"
                  data-wcpt-value="<?php echo $category->term_taxonomy_id; ?>"
                  data-wcpt-open="<?php echo $this->args['pre_open_depth']; ?>" data-wcpt-depth="<?php echo $depth; ?>">
                  <label class="<?php echo $checked ? 'wcpt-active' : ''; ?>"
                    data-wcpt-value="<?php echo $category->term_taxonomy_id; ?>" data-wcpt-slug="<?php echo $category->slug; ?>">
                    <a href="<?php
                    if (defined('WCPT_PRO')) {
                      echo strtok(get_term_link($category->term_taxonomy_id), '?') . wcpt_get_archive_query_string('category', $category->term_taxonomy_id);
                    } else {
                      echo get_term_link($category->term_taxonomy_id);
                    }
                    ?>" class="wcpt-nav-redirect-link">
                      <?php echo $category->label; ?>
                    </a>
                    <?php echo $has_children ? wcpt_icon('chevron-down', 'wcpt-filter-hierarchy-accordion__trigger') : ''; ?>
                  </label>
                </div>
                <?php
          endif;
        } else {
          ?>
              <div
                class="<?php echo $this->args['option_class'] ?> <?php echo $has_children ? 'wcpt-filter-hierarchy-accordion' : ''; ?> <?php echo ($checked || $child_checked) ? 'wcpt-filter-hierarchy-accordion--open' : ''; ?> <?php echo $this->args['pre_open_depth'] > $depth ? 'wcpt-filter-hierarchy-accordion--open' : ''; ?>"
                data-wcpt-value="<?php echo $category->term_taxonomy_id; ?>"
                data-wcpt-open="<?php echo $this->args['pre_open_depth']; ?>" data-wcpt-depth="<?php echo $depth; ?>">
                <label class="<?php echo $checked ? 'wcpt-active' : ''; ?>"
                  data-wcpt-value="<?php echo $category->term_taxonomy_id; ?>"
                  data-wcpt-slug="<?php echo $category->slug; ?>"><input
                    class="<?php echo (is_wp_error($children) || !count($children)) ? '' : 'wcpt-hr-parent-term'; ?>"
                    type="<?php echo $this->args['single'] ? 'radio' : 'checkbox'; ?>"
                    name="<?php echo $this->args['field_name'] ?>[]" value="<?php echo $category->term_taxonomy_id; ?>" <?php echo $checked ? ' checked="checked" ' : ''; ?> /><span>
                    <?php echo $category->label; ?>
                  </span>
                  <?php echo $has_children ? wcpt_icon('chevron-down', 'wcpt-filter-hierarchy-accordion__trigger') : ''; ?>
                </label>
              </div>
              <?php
        }

        $output .= ob_get_clean();
      }

      function end_el(&$output, $object, $depth = 0, $args = array())
      {
        $output .= '';
      }

      function start_lvl(&$output, $depth = 0, $args = array())
      {
        $output .= '<div class="wcpt-hr-child-terms-wrapper wcpt-dropdown-sub-menu wcpt-filter-hierarchy-accordion__content">';
      }

      function end_lvl(&$output, $depth = 0, $args = array())
      {
        $output .= '</div>';
      }
    }
  }
}

// decides whether orderby: relevance should be force applied
function wcpt_maybe_apply_sortby_relevance()
{
  $table_data = wcpt_get_table_data();
  $table_id = $table_data['id'];
  $sc_attrs =& $table_data['query']['sc_attrs'];

  if (
    !empty($_GET['s']) &&
    !empty($sc_attrs['_archive'])
  ) {
    return true;
  }

  $local_seach = false;
  foreach ($_GET as $key => $val) {
    if (
      strpos($key, $table_id . '_search') !== false &&
      $val
    ) {
      $local_seach = true;
    }
  }

  if (
    !empty($_GET[$table_id . '_orderby']) &&
    $_GET[$table_id . '_orderby'] == 'relevance' &&
    $local_seach
  ) {
    return true;
  }

  return false;
}

// search
require_once(WCPT_PLUGIN_PATH . 'search.php');

// WCPT PRO buttons, covers and markers
function wcpt_elm_type_list($element_types, $heading = false)
{

  if (defined('WCPT_PRO')) {
    sort($element_types);

  } else {
    $pro_elements = array();
    $lite_elements = array();

    foreach ($element_types as $item) {
      if (strpos($item, '[pro]') !== false) {
        $pro_elements[] = $item;
      } else {
        $lite_elements[] = $item;
      }
    }

    sort($lite_elements);
    sort($pro_elements);

    $element_types = array_merge($lite_elements, array('_divider'), $pro_elements);
  }

  if ($heading) {
    ?>
        <span class="wcpt-block-editor-element-type-heading">
          <?php echo $heading; ?>
        </span>
        <?php
  }

  ?>
      <div class="wcpt-block-editor-element-type-list">
        <div class="wcpt-block-editor-element-type-list__search">
          <input type="text" class="wcpt-block-editor-element-type-list__search__input" placeholder="Search for element">
          <?php echo wcpt_icon('search', 'wcpt-block-editor-element-type-list__search__icon'); ?>
        </div>
        <?php
        ob_start();
        wcpt_pro_badge();
        $pro_badge = ob_get_clean();

        foreach ($element_types as $element_type) {

          if (
            $element_type == 'Availability Filter [pro]' &&
            get_option('woocommerce_hide_out_of_stock_items', 'no') == 'yes'
          ) {
            continue;
          }

          if (!$element_type) {
            echo '<div class="wcpt-clear"></div>';

          } else if ($element_type == '_divider') {
            echo '<hr class="wcpt-block-editor-element-type-_divider" />';

          } else {

            $slug = strtolower(str_replace(' ', '_', str_replace(' / ', '_', str_replace(' [pro]', '', $element_type))));

            if ($pro_badge && (false !== strpos($element_type, '[pro]'))) {
              $lock = 'wcpt-pro-lock wcpt-disabled';
              $element_type = str_replace(' [pro]', '', $element_type);
              $_pro_badge = ' ' . $pro_badge;

            } else {
              $lock = '';
              $_pro_badge = '';
            }

            $label = str_replace(' [pro]', '', $element_type);
            if (false !== strpos($label, "__")) {
              $label = substr($label, 0, strpos($label, "__"));
            }

            ?>
              <span class="wcpt-block-editor-element-type <?php echo $lock; ?>" data-elm="<?php echo $slug; ?>">
              <?php echo $label . $_pro_badge; ?>
              </span>
            <?php
          }

        }
        ?>
      </div>
      <?php
}

function wcpt_how_to_use_link($link)
{
  ?>
      <a href="<?php echo $link; ?>" target="_blank" class="wcpt-how-to-use">
        <?php wcpt_icon('file-text'); ?>
        <span>How to use</span>
      </a>
      <?php
}

// add the import export markup
add_action('admin_footer', 'wcpt_insert_import_export_markup');
function wcpt_insert_import_export_markup()
{
  $arr = explode('/', $_SERVER['PHP_SELF']);
  $page = end($arr);

  if (
    $page !== 'edit.php' ||
    !empty($_GET['page']) ||
    (
      empty($_GET['post_type']) ||
      $_GET['post_type'] !== 'wc_product_table'
    )
  ) {
    return;
  }
  $wcpt_import_export_button_label_append = 'tables';
  $wcpt_import_export_button_context = 'tables';
  require_once('editor/settings-partials/import-export.php');
  ?>
      <style>
        .wcpt-import-export-wrapper {
          display: none;
        }
      </style>

      <script>
        (function ($) {
          $('.wcpt-import-export-wrapper').appendTo('#wpbody-content').show();
        })(jQuery)
      </script>
      <?php
}

// checks if template is empty
function wcpt_is_template_empty($tpl)
{
  if (empty($tpl)) {
    return true;
  }

  if (in_array(gettype($tpl), array('string', 'number'))) {
    return false;
  }

  $has_content = false;
  foreach ($tpl as $row) {
    if (
      !empty($row['elements']) &&
      count($row['elements'])
    ) {
      $has_content = true;
    }
  }

  return !$has_content;
}

// list image sizes
function wcpt_get_all_image_sizes()
{
  global $_wp_additional_image_sizes;

  $default_image_sizes = get_intermediate_image_sizes();

  $image_sizes = array();

  foreach ($default_image_sizes as $size) {
    $image_sizes[$size] = array(
      'width' => intval(get_option("{$size}_size_w")),
      'height' => intval(get_option("{$size}_size_h")),
      'crop' => get_option("{$size}_crop") ? get_option("{$size}_crop") : false,
    );
  }

  if (isset($_wp_additional_image_sizes) && count($_wp_additional_image_sizes)) {
    $image_sizes = array_merge($image_sizes, $_wp_additional_image_sizes);
  }

  return $image_sizes;
}

// WooCommerce Product Search compatibility
add_filter('wcpt_query_args', 'wcpt_compatibility__woocommerce_product_search', 10, 1);
function wcpt_compatibility__woocommerce_product_search($args)
{
  if (class_exists('WooCommerce_Product_Search_Service')) {
    remove_filter('pre_get_posts', 'WooCommerce_Product_Search_Service::wps_pre_get_posts', 10);
  }

  return $args;
}

function wcpt_price_decimal($price)
{
  $unformatted_price = $price;
  $negative = $price < 0;
  $price = apply_filters('raw_woocommerce_price', floatval($negative ? $price * -1 : $price));
  $price = apply_filters(
    'formatted_woocommerce_price',
    number_format(
      $price,
      wc_get_price_decimals(),
      wc_get_price_decimal_separator(),
      wc_get_price_thousand_separator()
    ),
    $price,
    wc_get_price_decimals(),
    wc_get_price_decimal_separator(),
    wc_get_price_thousand_separator()
  );

  if (apply_filters('woocommerce_price_trim_zeros', false) && wc_get_price_decimals() > 0) {
    $price = wc_trim_zeros($price);
  }

  return $price;
}

/**
 * Replace min/max price placeholders in navigation markup
 *
 * @param string $nav Navigation markup
 * @return string Updated navigation markup
 */
add_filter('wcpt_navigation', 'wcpt_replace_min_max_price');
function wcpt_replace_min_max_price($nav)
{
  // $price_range = defined('WCPT_PRO') ? wcpt_get_min_max_price() : wcpt_get_post_meta_min_max('_price');
  $price_range = wcpt_get_post_meta_min_max('_price');

  // Replace placeholders with actual min/max values
  $replacements = array(
    '%min-price%' => $price_range['min'],
    '%max-price%' => $price_range['max']
  );

  remove_filter('wcpt_navigation', 'wcpt_replace_min_max_price');
  return strtr($nav, $replacements);
}

/**
 * Get minimum and maximum values for a custom field across all posts
 *
 * @param string $custom_field The meta key to get min/max values for
 * @return array Array with 'min' and 'max' numeric values
 */
function wcpt_get_post_meta_min_max($custom_field)
{
  global $wpdb;

  // Get all values for this meta key, ordered numerically
  $query = $wpdb->prepare("
    SELECT MIN(CAST(meta_value AS DECIMAL(10,2))) as min_val,
           MAX(CAST(meta_value AS DECIMAL(10,2))) as max_val
    FROM $wpdb->postmeta 
    WHERE meta_key = %s
    AND meta_value REGEXP '^[0-9]'
  ", $custom_field);

  $result = $wpdb->get_row($query);

  // Default to 0 if no values found
  $min = $result ? (float) $result->min_val : 0;
  $max = $result ? (float) $result->max_val : 0;

  return array(
    'min' => $min,
    'max' => $max
  );
}

function wcpt_get_translation($mixed)
{
  if (FALSE === strpos($mixed, ":")) {
    return $mixed;
  }

  $chopped = array_map('trim', explode("|", $mixed));

  foreach ($chopped as $translation_info) {
    $translation_info_chopped = array_map('trim', explode(":", $translation_info));
    if ('default' == strtolower($translation_info_chopped[0])) {
      $default = $translation_info_chopped[1];
    }

    if (strtolower(get_locale()) == strtolower($translation_info_chopped[0])) {
      $translation = $translation_info_chopped[1];
      break;
    }
  }

  if (empty($translation)) {
    if ($default) {
      $translation = $default;
    } else {
      $translation = '';
    }
  }

  return $translation;
}

add_filter('wcpt_element', 'wcpt_content__max_width', 10, 1);
function wcpt_content__max_width($elm)
{
  if (in_array($elm['type'], array('content', 'excerpt'))) {
    if (
      empty($elm['style']) ||
      empty($elm['style']['[id]']) ||
      (
        empty($elm['style']['[id]']['width']) &&
        empty($elm['style']['[id]']['max-width'])
      )
    ) {
      if (empty($elm['html_class'])) {
        $elm['html_class'] = '';
      }
      $elm['html_class'] .= " wcpt-content--max-width ";
    }
  }

  return $elm;
}

// salient qty width fix
$wcpt_salient_fixed_qty_ids = array();
add_filter('wcpt_element_markup', 'wcpt_salient_qty_width_fix', 100, 2);
function wcpt_salient_qty_width_fix($markup, $element)
{
  if (
    !empty($element) &&
    !empty($element['type']) &&
    $element['type'] === 'quantity' &&
    !in_array($element['id'], $GLOBALS['wcpt_salient_fixed_qty_ids'])
  ) {
    $theme = wp_get_theme();
    if ('Salient' == $theme->name || 'Salient' == $theme->parent_theme) {
      if ( // force width
        !empty($element['style']) &&
        !empty($element['style']['[id].wcpt-display-type-input']) &&
        !empty($element['style']['[id].wcpt-display-type-input']['width'])
      ) {
        if (is_numeric($element['style']['[id].wcpt-display-type-input']['width'])) {
          $width = $element['style']['[id].wcpt-display-type-input']['width'] . 'px';
        } else {
          $width = $element['style']['[id].wcpt-display-type-input']['width'];
        }

        $markup .= ' <style> .woocommerce .wcpt-table .wcpt-' . $element['id'] . ' {width: ' . $width . ' !important; } </style>';
      } else { // default width
        $markup .= ' <style> .woocommerce .wcpt-table .wcpt-' . $element['id'] . ' {width: 50px !important; } </style>';
      }
      $GLOBALS['wcpt_salient_fixed_qty_ids'][] = $element['id'];
    }
  }

  return $markup;
}


// content and excerpt template filter hooks

// -- remove [product_table] shortcode
add_filter('wcpt_content', 'wcpt_remove_product_table_shortcode');
add_filter('wcpt_excerpt', 'wcpt_remove_product_table_shortcode');

// -- excerpt only - do media & other shortcodes
add_filter('wcpt_excerpt', 'wcpt_do_inner_shortcode', 100, 1);
function wcpt_do_inner_shortcode($excerpt)
{
  global $wp_embed;
  return do_shortcode($wp_embed->autoembed($wp_embed->run_shortcode($excerpt)));
}

function wcpt_truncate_string($text, $limit)
{
  if (str_word_count($text, 0) > $limit) {
    $words = str_word_count($text, 2);
    $pos = array_keys($words);
    $text = substr($text, 0, $pos[$limit]);
  }
  return $text;
}

// use default search
add_filter('wcpt_shortcode_attributes', 'wcpt_shortcode_attributes__use_default_search');
function wcpt_shortcode_attributes__use_default_search($atts)
{
  if (!empty($atts['use_default_search'])) {
    add_filter('wcpt_search_args', 'wcpt_search_args__use_default_search');
  } else {
    remove_filter('wcpt_search_args', 'wcpt_search_args__use_default_search');
  }

  return $atts;
}

function wcpt_search_args__use_default_search($args)
{
  $args['use_default_search'] = true;
  return $args;
}

// LiteSpeed Cache compatbility fix
// force js > wcpt_cart to run on every page load
add_action('wp_enqueue_scripts', 'wcpt_lightspeed_cache_compatibility_fix', 1000);
function wcpt_lightspeed_cache_compatibility_fix()
{
  if (class_exists('LiteSpeed\Core')) {
    wp_add_inline_script('wcpt', "wcpt_params.initially_empty_cart = false;", 'after');
  }
}

// Jupiter theme v ~ 6.8 compatibility
// stop regenerating images each time Select Variation is called  
add_action('wcpt_before_loop', 'wcpt_jupiter_remove_image_regen_handler');
function wcpt_jupiter_remove_image_regen_handler()
{
  if (has_filter('image_downsize', 'gambit_otf_regen_thumbs_media_downsize')) {
    remove_filter('image_downsize', 'gambit_otf_regen_thumbs_media_downsize', 10, 3);
    add_filter('wcpt_container_close', 'wcpt_jupiter_reattach_image_regen_handler');
  }
}

function wcpt_jupiter_reattach_image_regen_handler()
{
  add_filter('image_downsize', 'gambit_otf_regen_thumbs_media_downsize', 10, 3);
}

// genral placeholders
// -- print options
function wcpt_general_placeholders__print_placeholders($destination = false)
{
  ob_start();
  ?>
      <small style="cursor: default;">
        <strong>Available placeholders:</strong>
        <?php wcpt_pro_badge(); ?><br>
        <div class="<?php wcpt_pro_cover(); ?>">
          [product_id]: Product ID<br>
          <!-- [parent_id]: parent ID in case of variation, else product ID<br> -->
          <!-- [variation_id]: variation ID in case of variation, else empty<br> -->
          [product_url]: Product url (no trailing slash "/")<br>
          <!-- [parent_url]: Parent url in case of variation, else product url<br> -->
          [custom_field: <em>name</em>]: Replace <em>name</em> with custom field name<br>
          [attribute: <em>slug</em>]: Replace <em>slug</em> with attribute slug<br>
          [product_slug]: Product slug, eg: red-shoes-02<br>
          <!-- [parent_slug]: parent slug if variation, else product slug<br> -->
          [product_sku]: Product SKU<br>
          <!-- [parent_sku]: parent SKU if variation, else product SKU<br> -->
          [product_name]: Product name<br>
          <!-- [parent_name]: parent name in case of variation, else product name<br> -->
          [product_menu_order]: Product menu order<br>
          <!-- [parent_menu_order]: parent menu order in case of variation, else product menu order<br> -->
          [site_url]: Site URL (no trailing slash "/")<br>
          [page_url]: Current page URL (no trailing slash "/")<br>
        </div>
      </small>
      <?php

      $mkp = ob_get_clean();

      if ($destination == 'shortcode') {
        $mkp = str_replace(array('[', ']'), array('%', '%'), $mkp);
      }

      echo $mkp;
}

// -- parse
function wcpt_general_placeholders__parse($str, $source = false)
{
  if (function_exists('wcpt_general_placeholders__parse__pro')) {
    return wcpt_general_placeholders__parse__pro($str, $source);
  } else {
    return $str;
  }
}

// iterate over arr and refresh all ids
function wcpt_new_ids(&$arr, $fresh = true)
{
  if ($fresh) {
    $GLOBALS['wcpt_new_ids_id'] = time() + (rand(0, 100000));
  }
  global $wcpt_new_ids_id;
  foreach ($arr as $key => &$val) {
    if ($key === 'id') {
      $val = ++$wcpt_new_ids_id;
    } else if (gettype($val) == 'array') {
      wcpt_new_ids($val, false);
    }
  }
}

// presets
if (file_exists(WCPT_PLUGIN_PATH . 'presets/presets.php')) {
  require_once(WCPT_PLUGIN_PATH . 'presets/presets.php');
}

// auto scroll on Lite
// add_filter('wcpt_shortcode_attributes', 'wcpt_lite_auto_scroll');
// function wcpt_lite_auto_scroll($atts = array())
// {

//   if (!empty($atts['auto_scroll'])) {
//     return $atts;
//   }

//   if (empty($atts['laptop_auto_scroll'])) {
//     $atts['laptop_auto_scroll'] = 'true';
//   }

//   if (empty($atts['tablet_auto_scroll'])) {
//     $atts['tablet_auto_scroll'] = 'true';
//   }

//   if (empty($atts['phone_auto_scroll'])) {
//     $atts['phone_auto_scroll'] = 'true';
//   }

//   return $atts;
// }

// skip default relabels
function wcpt_is_default_relabel($rule)
{
  if (
    !$rule['label'][0]['elements'] ||
    (
      count($rule['label'][0]['elements']) == 1 &&
      !empty($rule['label'][0]['elements'][0]['text']) &&
      $rule['label'][0]['elements'][0]['text'] == "[term]"
    )
  ) {
    return true;
  }
}

// replace < and > with htmlentities
function wcpt_esc_tag($text)
{
  return str_replace('>', '&gt;', str_replace('<', '&lt;', $text));
}

// print icon select dropdown
function wcpt_print_icon_dopdown($model_key = 'name', $options = [])
{
  ?>
      <select class="wcpt-select-icon" wcpt-model-key="<?php echo $model_key ?>" style="width: 100%;">
        <?php
        $path = WCPT_PLUGIN_PATH . 'assets/feather';
        $icons = array_diff(scandir($path), array('..', '.', '.DS_Store'));
        foreach ($icons as $icon) {
          if ($icon) {
            $icon_name = substr($icon, 0, -4);
            if (!empty($options) && !in_array($icon_name, $options))
              continue;
            echo '<option value="' . $icon_name . '">' . str_replace('-', ' ', ucfirst($icon_name)) . '</option>';
          }
        }
        ?>
      </select>
      <?php
}

// module permission
$wcpt_disabled_modules = [];

// -- collect disabled modules
add_action('wcpt_before_product_table_is_processed', 'wcpt_collect_disabled_modules');
function wcpt_collect_disabled_modules()
{
  global $wcpt_disabled_modules;
  $wcpt_disabled_modules = apply_filters('wcpt_disabled_modules', array());
}

// -- check if module is permitted or not
function wcpt_module_is_disabled($module)
{
  global $wcpt_disabled_modules;

  foreach ($wcpt_disabled_modules as $key => $val) {
    if ($val['module'] == $module) {
      return true;
    }
  }

  return false;
}

// check if a plugin is active
function wcpt_is_plugin_active($plugin)
{
  return in_array($plugin, (array) get_option('active_plugins', array()));
}

// HPOS compatibility
add_action('before_woocommerce_init', function () {
  if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
  }
});

// get the current loop product index relative to total products
function wcpt_get_current_product_index()
{
  global $wcpt_products;

  if (!$wcpt_products) {
    return false;
  }

  if ($wcpt_products->get('posts_per_page') == -1) {
    $index = $wcpt_products->current_post;
  } else {
    $index = $wcpt_products->current_post + ($wcpt_products->get('posts_per_page') * ($wcpt_products->get('paged') - 1));
  }

  return $index;
}

require("query_editor_v2/query_editor_v2.php");

// add encrypted params attribute to container
function wcpt_encrypt($data)
{
  // Check for the availability of required OpenSSL functions
  if (!function_exists('openssl_cipher_iv_length') || !function_exists('openssl_encrypt')) {
    return false; // OpenSSL functions are not available
  }

  // Compress data if gzencode function exists
  $data = function_exists('gzencode') ? gzencode($data) : $data;

  // Generate an initialization vector (IV) for AES-256 encryption
  $ivLength = openssl_cipher_iv_length('aes-256-cbc');
  $iv = openssl_random_pseudo_bytes($ivLength);

  // Encrypt the data using AES-256-CBC algorithm
  $encryptedData = openssl_encrypt($data, 'aes-256-cbc', wp_salt('auth'), 0, $iv);

  // Return the base64 encoded encrypted data along with IV
  return base64_encode($encryptedData . '::' . $iv);
}

function wcpt_decrypt($data)
{
  // Check for the availability of the OpenSSL decrypt function
  if (!function_exists('openssl_decrypt')) {
    return false; // OpenSSL function is not available
  }

  // Decode the base64 encoded data and split it into encrypted data and IV
  $parts = explode('::', base64_decode($data), 2);
  if (count($parts) !== 2) {
    return false; // Data format is incorrect
  }
  list($encryptedData, $iv) = $parts;

  // Decompress data if gzdecode function exists
  $decryptedData = openssl_decrypt($encryptedData, 'aes-256-cbc', wp_salt('auth'), 0, $iv);
  return function_exists('gzdecode') ? gzdecode($decryptedData) : $decryptedData;
}

// simulate get_products() from the product table class to get the same table based results from outside it
function wcpt_simulate_get_products($table_id, $query_args, $user_filters)
{
  $table_data = $GLOBALS['wcpt_table_data'] = wcpt_get_table_data($table_id, "view");
  $GLOBALS['wcpt_user_filters'] = $user_filters;

  if (
    !empty($query_args['p']) &&
    !empty($table_data['query']['sc_attrs']['ids'])
  ) {
    $query_args['post__in'] = array_map('trim', explode(',', $table_data['query']['sc_attrs']['ids']));
    unset($query_args['p']);
  }

  $wc_ordering_args = WC()->query->get_catalog_ordering_args($query_args['orderby'], $query_args['order']);
  $query_args['orderby'] = $wc_ordering_args['orderby'];
  $query_args['order'] = $wc_ordering_args['order'];

  $query_args = apply_filters('wcpt_query_args', $query_args);

  if (!empty($table_data['query']['sc_attrs']['_return_query_args'])) {
    return $query_args;
  }

  $GLOBALS['wcpt_query_args'] = $query_args;

  $products = new WP_Query($query_args);

  WC()->query->remove_ordering_args();

  $products = apply_filters('wcpt_products', $products);

  unset($GLOBALS['wcpt_table_data']);

  return $products;
}

// reduce query vars to relevant ones
function wcpt_cull_query_vars($query_vars)
{
  global $wp_query;
  foreach ($query_vars as $key => $value) {
    if (empty($value) || $value === array()) {
      unset($query_vars[$key]);
    }
  }

  return $query_vars;
}

// whitelists the url param so it is communiated over nav
$wcpt_whitelist_url_params = ['s', 'post_type', 'term', 'taxonomy'];

function wcpt_permit_param($param_name)
{
  if (is_array($param_name)) {
    $GLOBALS['wcpt_whitelist_url_params'] = array_merge($GLOBALS['wcpt_whitelist_url_params'], $param_name);
  } else {
    $GLOBALS['wcpt_whitelist_url_params'][] = $param_name;
  }
}

//-- pass params through frontend (js)
add_action('wp_enqueue_scripts', 'wcpt_whitelist_url_params_js');
function wcpt_whitelist_url_params_js()
{
  global $wcpt_whitelist_url_params;
  if (count($wcpt_whitelist_url_params)) {
    wp_localize_script('wcpt', 'wcpt_persist_params', array_map('sanitize_text_field', $wcpt_whitelist_url_params));
  }
}
//-- pass params through bankend (php)
add_filter('wcpt_permitted_params', 'wcpt_whitelist_url_params_php', 100, 1);
function wcpt_whitelist_url_params_php($params)
{
  global $wcpt_whitelist_url_params;
  if (count($wcpt_whitelist_url_params)) {
    $params = array_merge(array_map('sanitize_text_field', $wcpt_whitelist_url_params), $params);
  }
  return $params;
}

// -- lang param
if (!empty($_GET['lang'])) {
  $wcpt_whitelist_url_params[] = $_GET['lang'];
}

// react app (dom ui) fns
// -- get taxonomy terms
function wcpt_react_app_get_taxonomy_terms_with_children($taxonomy_slug)
{
  $terms = get_terms(
    array(
      'taxonomy' => $taxonomy_slug,
      'hide_empty' => false,
      'parent' => 0 // Get only top-level terms initially
    )
  );

  if (is_wp_error($terms)) {
    return false;
  }

  $formatted_terms = array();
  foreach ($terms as $term) {
    $formatted_term = wcpt_react_app_format_term_with_children($term, $taxonomy_slug);
    if ($formatted_term) {
      $formatted_terms[] = $formatted_term;
    }
  }

  return $formatted_terms;
}

// -- -- helper to format the terms
function wcpt_react_app_format_term_with_children($term, $taxonomy_slug)
{
  $children_terms = get_terms(
    array(
      'taxonomy' => $taxonomy_slug,
      'hide_empty' => false,
      'parent' => $term->term_id
    )
  );

  $label = wcpt_maybe_decode_term_label($term->name);

  if (gettype($label) !== 'string') {
    return false;
  }

  $formatted_term = array(
    'label' => $label,
    'id' => $term->term_id,
    'slug' => $term->slug,
  );

  if (!empty($children_terms)) {
    $children = array();
    foreach ($children_terms as $child) {
      $children[] = wcpt_react_app_format_term_with_children($child, $taxonomy_slug); // Recursively find children
    }

    // Add the 'children' key only if there are child terms
    $formatted_term['children'] = $children;
  }

  return $formatted_term;
}

// -- helper: maybe decode html entities
function wcpt_maybe_decode_term_label($string)
{
  // Common HTML entities to check for
  $entities_patterns = [
    '&quot;',
    '&amp;',
    '&apos;',
    '&#34;',
    '&#38;',
    '&#39;'
  ];

  // Check if any of these patterns exist in the label
  $needs_decode = false;
  foreach ($entities_patterns as $pattern) {
    if (stripos($string, $pattern) !== false) {
      $needs_decode = true;
      break;
    }
  }

  // Only decode if we found entities
  if ($needs_decode) {
    return html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  }

  // Return original if no entities found
  return $string;
}

// -- get product taxonomies
function wcpt_react_app_get_product_taxonomies()
{
  $taxonomies = get_object_taxonomies('product', 'objects');

  $taxonomy_data = array();

  foreach ($taxonomies as $taxonomy) {
    $type = 'custom';
    $label = $taxonomy->label;
    if ($taxonomy->name === 'product_cat') {
      $type = 'category';
    } elseif ($taxonomy->name === 'product_tag') {
      $type = 'tag';
    } elseif (strpos($taxonomy->name, 'pa_') !== false) {
      $type = 'attribute';
      $label = preg_replace('/^Product\s+/', '', $taxonomy->label);
    } elseif ($taxonomy->name === 'product_type') {
      continue;
    }

    $taxonomy_data[] = array(
      'label' => $label,
      'slug' => $taxonomy->name,
      'terms' => null,
      'type' => $type,
    );
  }

  if (!in_array("product_cat", array_column($taxonomy_data, 'slug'))) {
    $taxonomy_data[] = array(
      'label' => 'Product category',
      'slug' => 'product_cat',
      'terms' => null,
      'type' => 'category',
    );
  }

  return $taxonomy_data;
}

// -- get product authors
function wcpt_react_app_get_product_authors()
{
  $args = array(
    'fields' => array('ID', 'display_name'), // Adjust this to get the fields you need
    'orderby' => 'display_name',
    'order' => 'ASC',
    'capability' => 'edit_products' // Only get users with edit_products capability
  );

  $users = get_users($args);

  $user_list = [];
  foreach ($users as $user) {
    if (user_can($user->ID, 'edit_products')) { // Check if the user can edit products
      $user_list[] = [
        'value' => $user->ID,
        'label' => $user->display_name
      ];
    }
  }

  return $user_list;
}

// -- get tables as options
function wcpt_react_app_get_product_tables_select_options()
{
  $options = array();

  $args = array(
    'post_type' => 'wc_product_table',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids',
  );

  $query = new WP_Query($args);

  if ($query->have_posts()) {
    while ($query->have_posts()) {
      $query->the_post();
      $options[] = array(
        'label' => get_the_title(),
        'value' => (string) get_the_ID(),
      );
    }
  }

  wp_reset_postdata();

  return $options;
}

// make the table inherit columns from the previous device if not set
add_filter('wcpt_data', 'wcpt_table_inherit_columns', 10, 2);
function wcpt_table_inherit_columns($table_data, $context)
{
  if ($context !== "view")
    return $table_data;

  $device_priority = ['laptop', 'tablet', 'phone'];
  $previous_columns = [];

  foreach ($device_priority as $device) {
    if (empty($table_data['columns'][$device])) {
      $table_data['columns'][$device] = $previous_columns;
    } else {
      $previous_columns = $table_data['columns'][$device];
    }
  }

  return $table_data;
}

add_filter('wcpt_element', 'wcpt_keep_sidebar_filters_without_header_always_open');
function wcpt_keep_sidebar_filters_without_header_always_open($element)
{
  if (
    !empty($element['type']) &&
    (strpos($element['type'], '_filter') !== false || $element['type'] === 'sort_by') &&
    wcpt_is_filter_heading_empty($element)
  ) {
    $element['accordion_always_open'] = true;
    $element['html_class'] = " wcpt-filter-no-heading";
  }

  return $element;
}

function wcpt_is_filter_heading_empty($element)
{
  if (
    empty($element['heading']) ||
    (
      is_array($element['heading']) && (
        !count($element['heading']) ||
        !is_array($element['heading'][0]) ||
        empty($element['heading'][0]['elements']) ||
        !is_array($element['heading'][0]['elements']) ||
        !count($element['heading'][0]['elements'])
      )
    )
  ) {
    return true;
  }

  return false;
}

function wcpt_editor_more_options_container_start()
{
  ?>
      <!-- more options -->
      <div class="wcpt-editor-row-option wcpt-editor-more-options">
        <?php
}

function wcpt_editor_more_options_container_end()
{
  ?>
        <div class="wcpt-editor-more-options__trigger">
          <span class="wcpt-editor-more-options__trigger__more">+ More options</span>
          <span class="wcpt-editor-more-options__trigger__less">- Less options</span>
        </div>
      </div>
      <?php
}

// Add speculation rules to exclude certain links from prefetching
add_filter('wp_speculation_rules_href_exclude_paths', 'wcpt_exclude_selectors_from_prefetch', 100, 1);
function wcpt_exclude_selectors_from_prefetch($href_exclude_paths)
{
  // Ensure input is array
  if (!is_array($href_exclude_paths)) {
    $href_exclude_paths = array();
  }

  // Get cart page slug safely
  $cart_page_id = wc_get_page_id('cart');
  $cart_page_slug = $cart_page_id ? basename(get_permalink($cart_page_id)) : 'cart';

  // Add WooCommerce paths that should not be prefetched
  $href_exclude_paths[] = '/' . $cart_page_slug . '/*'; // Cart pages
  $href_exclude_paths[] = '/cart/*'; // Default cart pages
  $href_exclude_paths[] = '/checkout/*';  // Checkout pages  
  $href_exclude_paths[] = '/my-account/*'; // Account pages

  return array_unique($href_exclude_paths); // Remove any duplicates
}

// module: list layout
add_filter('wcpt_style_prop_val', 'wcpt_list_layout_prop_fix', 10, 1);
function wcpt_list_layout_prop_fix($arr)
{
  if (
    $arr['prop'] == 'border-spacing' &&
    !empty($arr['val'])
  ) {
    $val = intval($arr['val']);
    if ($val > 0) {
      $arr['val'] = "0 {$val}px";
    }
  }

  if (
    $arr['prop'] == 'list_layout_enabled'
  ) {
    $arr['prop'] = "";
    $arr['val'] = "";
  }

  return $arr;
}

add_filter('wcpt_container_html_class', 'wcpt_style__list_layout_html_class', 10, 2);
function wcpt_style__list_layout_html_class($class)
{
  if (wcpt_module_is_disabled('list_layout')) {
    return $class;
  }

  $table_data = wcpt_get_table_data();
  $device = wcpt_get_device();

  if (
    !empty($table_data['style'][$device]['[container].wcpt-list-view']) &&
    !empty($table_data['style'][$device]['[container].wcpt-list-view']['list_layout_enabled'])
  ) {
    $class .= ' wcpt-list-view wcpt-list-view--calc';
  }

  return $class;
}

function wcpt_get_container_attributes()
{
  $table_data = wcpt_get_table_data();

  $attributes = sprintf(
    'data-wcpt-table-id="%1$s"
    data-wcpt-query-string="%2$s" 
    data-wcpt-sc-attrs="%3$s"
    data-wcpt-encrypted-query-vars="%4$s"
    data-wcpt-encrypted-user-filters="%5$s"',
    $table_data['id'],
    esc_attr(wcpt_get_table_query_string()),
    esc_attr(json_encode($table_data['query']['sc_attrs'])),
    wcpt_encrypt(json_encode(isset($GLOBALS['wcpt_products']) ? wcpt_cull_query_vars($GLOBALS['wcpt_products']->query_vars) : [])),
    wcpt_encrypt(json_encode(isset($GLOBALS['wcpt_user_filters']) ? $GLOBALS['wcpt_user_filters'] : []))
  );

  return apply_filters('wcpt_container_html_attributes', $attributes);
}

/**
 * Completes unclosed HTML tags in a string using DOMDocument
 * 
 * @param string $content The HTML content to process
 * @return string The content with completed HTML tags
 */
function wcpt_complete_unclosed_tags($content)
{
  // Only process if required libxml version is available
  if (
    defined('LIBXML_DOTTED_VERSION') &&
    version_compare(LIBXML_DOTTED_VERSION, '2.7.0', '>')
  ) {
    $dom = new DOMDocument();
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div>' . $content . '</div></body></html>';

    // Save the current state of error handling
    $_errors = libxml_use_internal_errors(true);

    // Load the HTML with proper encoding and without adding default doctype
    $dom->loadHTML($html, LIBXML_HTML_NODEFDTD);

    // Extract just the div content
    $div = $dom->getElementsByTagName('div')->item(0);
    $processedContent = '';

    // Process each child node of the div
    if ($div) {
      foreach ($div->childNodes as $child) {
        $processedContent .= $dom->saveHTML($child);
      }
    }

    // Restore the previous error handling state
    libxml_use_internal_errors($_errors);

    return $processedContent;
  }

  // Return original content if requirements not met
  return $content;
}

function wcpt_general_style_accordion_open($heading, $selector)
{
  ?>
      <div class="wcpt-toggle-options">
        <span class="wcpt-toggle-label">
          <?php wcpt_icon('paint-brush'); ?>
          <?php echo $heading; ?>
          <?php wcpt_icon('chevron-down'); ?>
        </span>
        <div class="wcpt-wrapper" wcpt-model-key="<?php echo $selector; ?>">
          <?php
}

function wcpt_general_style_accordion_close()
{
  ?>
        </div>
      </div>
      <?php
}

// stock status
add_filter('wcpt_query_args', 'wcpt__query_args__stock_status');
function wcpt__query_args__stock_status($query_args)
{
  $sc_attrs = wcpt_get_table_data()['query']['sc_attrs'];
  if (!empty($sc_attrs['stock_status'])) {
    $stock_status = explode(',', $sc_attrs['stock_status']);
    if (empty($query_args['meta_query'])) {
      $query_args['meta_query'] = array();
    }
    $query_args['meta_query'][] = array(
      'key' => '_stock_status',
      'value' => $stock_status,
      'compare' => 'IN',
    );
  }
  return $query_args;
}

// Enable admin search product table by ID and title
add_action('pre_get_posts', 'wcpt_admin_enable_table_search_by_id');
function wcpt_admin_enable_table_search_by_id($query)
{
  // Bail if we are not in the admin area
  if (!is_admin()) {
    return;
  }

  // Bail if this is not the search query
  if (!$query->is_main_query() || !$query->is_search()) {
    return;
  }

  // Get the current post type
  if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'wc_product_table') {
    return;
  }

  // Get the search term
  $search_term = $query->get('s');

  // Check if the search term is a numeric ID
  if (is_numeric($search_term) && intval($search_term) > 0) {
    // Create a meta query to search by ID or title
    global $wpdb;

    // Remove the original search
    $query->set('s', '');

    // Add search by ID or title using search query
    add_filter('posts_search', function ($search, $wp_query) use ($search_term, $wpdb) {
      // Only modify our specific query
      if ($wp_query->get('post_type') !== 'wc_product_table') {
        return $search;
      }

      $id = intval($search_term);
      $like = '%' . $wpdb->esc_like($search_term) . '%';

      // Search by ID or title
      $search = " AND (($wpdb->posts.ID = $id) OR ($wpdb->posts.post_title LIKE '$like'))";

      return $search;
    }, 10, 2);
  }
}

// Add placeholder text to search input in admin product table search
add_filter('get_search_query', 'wcpt_admin_product_table_search_placeholder');
function wcpt_admin_product_table_search_placeholder($query)
{
  // Only modify the search form on the product table admin page
  global $pagenow, $typenow;

  if (is_admin() && $pagenow === 'edit.php' && $typenow === 'wc_product_table') {
    // Add placeholder text
    add_filter('admin_search_box_placeholder', function ($placeholder) {
      return 'Search by Title or ID';
    });
  }

  return $query;
}

// navigation - other settings
add_filter('wcpt_data', 'wcpt_navigation_other_settings', 10, 2);
function wcpt_navigation_other_settings($table_data, $context)
{
  if ($context !== "view")
    return $table_data;

  $navigation_settings = empty($table_data['navigation_settings']) ? [] : $table_data['navigation_settings'];

  // adaptive filtering
  $options = array(
    "dynamicHideFilters" => "dynamic_hide_filters",
    "dynamicRecount" => "dynamic_recount",
    "dynamicFiltersLazyLoad" => "dynamic_filters_lazy_load",
    "dynamicRecountMax" => "dynamic_recount_max",
  );

  foreach ($options as $react_key => $sc_attr_key) {
    if (!empty($navigation_settings[$react_key])) {

      if ($react_key == "dynamicRecountMax") {
        $table_data['query']['sc_attrs'][$sc_attr_key] = $navigation_settings[$react_key];
      } else {
        $table_data['query']['sc_attrs'][$sc_attr_key] = "true";
      }
    }
  }

  // auto scroll
  if (empty($navigation_settings['autoScroll'])) {
    $navigation_settings['autoScroll'] = [];
  }

  foreach (['laptop', 'tablet', 'phone'] as $device) {
    if (empty($table_data['query']['sc_attrs'][$device . "_auto_scroll"])) {
      if (in_array($device, $navigation_settings['autoScroll'])) {
        $table_data['query']['sc_attrs'][$device . "_auto_scroll"] = "true";
        if (empty($table_data['query']['sc_attrs'][$device . "_scroll_offset"])) {
          if (!empty($navigation_settings[$device . 'ScrollOffset'])) {
            $table_data['query']['sc_attrs'][$device . "_scroll_offset"] = $navigation_settings[$device . 'ScrollOffset'];
          }
        }
      } else {
        $table_data['query']['sc_attrs'][$device . "_auto_scroll"] = "false";
      }
    }
  }

  // misc.
  $options = array(
    "disableAjax" => "disable_ajax",
    "disableUrlUpdate" => "disable_url_update",
    "lazyLoad" => "lazy_load",
    "noResultsMessage" => "no_results_message",
  );

  foreach ($options as $react_key => $sc_attr_key) {
    if (!empty($navigation_settings[$react_key])) {
      $table_data['query']['sc_attrs'][$sc_attr_key] = $navigation_settings[$react_key];
    }
  }

  return $table_data;
}

// live styler - beta faciliy for dev only
require_once(WCPT_PLUGIN_PATH . '/theme_customizer/theme_customizer.php');

/* PRO */

// add a small badge next to pro features
function wcpt_pro_badge()
{
  if (!defined('WCPT_PRO')) {
    ?>
        <span class="wcpt-pro-badge">PRO</span>
        <?php
  }
}

// a disable cover over set of PRO features
function wcpt_pro_cover()
{
  if (!defined('WCPT_PRO')) {
    echo 'wcpt-pro-cover';
  }
}

// disable and print a PRO select option with badge
function wcpt_pro_option($val, $label)
{
  if (!defined('WCPT_PRO')) {
    $label = $label . ' (PRO only)';
    $disabled = 'disabled';
  } else {
    $disabled = '';
  }
  ?>
      <option value="<?php echo $val; ?>" <?php echo $disabled; ?>>
        <?php echo $label; ?>
      </option>
      <?php
}

// disable and print a PRO radio option with badge
function wcpt_pro_radio($val, $label, $mkey)
{
  ?>
      <label>
        <input type="radio" value="<?php echo $val; ?>" wcpt-model-key="<?php echo $mkey; ?>" <?php echo defined('WCPT_PRO') ? '' : 'disabled'; ?>>
        <?php echo $label;
        wcpt_pro_badge(); ?>
      </label>
      <?php
}

// disable and print a PRO checkbox option with badge
function wcpt_pro_checkbox($val, $label, $mkey)
{
  ?>
      <label>
        <input type="checkbox" value="<?php echo $val; ?>" wcpt-model-key="<?php echo $mkey; ?>" <?php echo defined('WCPT_PRO') ? '' : 'disabled'; ?>>
        <?php echo $label;
        wcpt_pro_badge(); ?>
      </label>
      <?php
}

// include PRO materials
if (file_exists(WCPT_PLUGIN_PATH . 'pro/')) {
  require_once(WCPT_PLUGIN_PATH . 'pro/functions.php');
  require_once(WCPT_PLUGIN_PATH . 'pro/condition.php');
}

// manage WCPT All Product Tables page columns
add_filter('manage_wc_product_table_posts_columns', 'wcpt_set_shortcode_column');
function wcpt_set_shortcode_column($columns)
{
  $new_columns = array();
  foreach ($columns as $name => $label) {
    $new_columns[$name] = $label;
    if ($name == 'title') {
      $new_columns['shortcode'] = __('Shortcode', 'wc-product-table');
    }
  }
  return $new_columns;
}

// add shortcode column in WCPT All Product Tables page
add_action('manage_wc_product_table_posts_custom_column', 'wcpt_shortcode_column', 10, 2);
function wcpt_shortcode_column($column, $post_id)
{
  switch ($column) {
    case 'shortcode':
      $shortcode = '[product_table id="' . $post_id . '"]';
      ?>
          <div class="wcpt-sc-display-wrapper">
            <input class="wcpt-sc-display" value="<?php echo esc_html($shortcode); ?>"
              onClick="this.setSelectionRange(0, this.value.length)" readonly />
            <span class="wcpt-sc-display-copy-button" title="Click to copy">
              <span class="wcpt-sc-display-copy-button-icon">
                <?php echo wcpt_icon('copy'); ?>
              </span>
              <span class="wcpt-sc-display-copy-button-success">
                <?php echo wcpt_icon('check'); ?>
              </span>
            </span>
          </div>
          <?php
          break;
  }
}

// Add styles and scripts to product table index pages
add_action('admin_head-edit.php', 'wcpt_insert_shortcode_column_styles');
function wcpt_insert_shortcode_column_styles()
{
  global $post_type;
  if ($post_type !== 'wc_product_table')
    return;
  ?>
      <style>
        .wcpt-sc-display-wrapper {
          display: flex;
          align-items: center;
          width: 250px;
          position: relative;
        }

        .wcpt-sc-display {
          flex: 1;
          min-width: 0;
          padding: 8px 32px 8px 8px;
          border: 1px solid #ddd;
          border-radius: 4px;
          background: #f7f7f7;
          font-family: monospace;
          font-size: 13px;
          line-height: 1.4;
        }

        .wcpt-sc-display-copy-button {
          position: absolute;
          right: 8px;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          padding: 4px;
          top: 50%;
          transform: translateY(-50%);
        }

        .wcpt-sc-display-copy-button svg {
          width: 14px;
          height: 14px;
          display: block;
        }

        .wcpt-sc-display-copy-button-success {
          display: none;
        }

        .wcpt-sc-display-copy-button-success svg {
          color: #4CAF50;
        }

        .wcpt-sc-display-copy-button.copied .wcpt-sc-display-copy-button-icon {
          display: none;
        }

        .wcpt-sc-display-copy-button.copied .wcpt-sc-display-copy-button-success {
          display: block;
        }
      </style>
      <script>
        jQuery(document).ready(function ($) {
          $('.wcpt-sc-display-copy-button').on('click', function () {
            var $this = $(this);
            var $input = $this.siblings('.wcpt-sc-display');
            $input.select();
            document.execCommand('copy');
            $this.addClass('copied');
            setTimeout(function () {
              $this.removeClass('copied');
            }, 700);
          });
        });
      </script>
      <?php
}

// make shortcode column sortable in WCPT All Product Tables page
add_filter('manage_edit-wc_product_table_sortable_columns', 'wcpt_shortcode_column_sortable');
function wcpt_shortcode_column_sortable($columns)
{
  $columns['shortcode'] = 'id';
  return $columns;
}

// terms for variation
add_action('wp_ajax_wcpt_get_attribute_terms', 'wcpt_get_attribute_terms_ajax');
function wcpt_get_attribute_terms_ajax()
{
  if (empty($_POST['taxonomy'])) {
    return false;
  }

  $terms = get_terms(
    array(
      'taxonomy' => (string) $_POST['taxonomy'],
      'hide_empty' => false,
      'orderby' => 'menu_order',
    )
  );

  if (is_wp_error($terms)) {
    return false;
  }

  foreach ($terms as &$term) {
    $term_obj = get_term($term->term_id, (string) $_POST['taxonomy']);
    $term->name = esc_html($term_obj->name);
  }

  wp_send_json($terms);
}

// get matching variation from attribute_terms
function wcpt_find_matching_product_variation($product, $attributes)
{
  foreach ($attributes as $key => $value) {
    if (strpos($key, 'attribute_') === 0) {
      continue;
    }

    unset($attributes[$key]);
    $attributes[sprintf('attribute_%s', $key)] = $value;
  }

  if (class_exists('WC_Data_Store')) {
    $table_data_store = WC_Data_Store::load('product');
    return $table_data_store->find_matching_product_variation($product, $attributes);

  } else {
    return $product->get_matching_variation($attributes);

  }
}

function wcpt_find_closests_matching_product_variation($product, $attributes)
{
  // iterate the variations
  $partial_match = false; // variation has some extra attributes
  $matched_variation = false;
  $variation_attributes = array(); // attributes of the complete / partial variation (used for pre-set in form)
  $last_attributes_diff = 100000; // extra attributes in the last partial match variation
  $total_attributes = count(array_keys($attributes));

  // wmpl
  global $sitepress;
  if (
    !empty($sitepress) &&
    $sitepress->get_default_language() !== $sitepress->get_current_language()
  ) {
    $_attributes = array();
    foreach ($attributes as $attr => $term_slug) {
      $term = get_term_by('slug', $term_slug, substr($attr, 10));
      $_attributes[$attr] = $term->slug;
    }

    $attributes = $_attributes;
  }

  $variations = wcpt_get_variations($product);
  foreach ($variations as $variation) {
    // skip if variation has too few attributes
    $total_variation_attributes = count(array_keys($variation['attributes']));
    if ($total_variation_attributes < $total_attributes) {
      continue;
    }

    // all the desired attributes must be in the variation
    $match = true;
    foreach ($attributes as $attribute => $term) {
      // skip variation if it does not have a desired attribute / match
      if (
        empty($variation['attributes'][$attribute]) ||
        $variation['attributes'][$attribute] !== $term
      ) {
        $match = false;
        break;
      }
    }

    if (!$match) {
      continue;

    } else {

      // complete match
      $attributes_diff = $total_variation_attributes - $total_attributes;
      if (!$attributes_diff) {
        return array(
          'type' => 'complete_match',
          'variation' => $variation,
          'variation_id' => $variation['variation_id'],
          'variation_attributes' => $variation['attributes']
        );

        // partial match
      } else if ($attributes_diff < $last_attributes_diff) {
        $partial_match = $variation['variation_id'];
        $variation_attributes = $variation['attributes'];
        $last_attributes_diff = $attributes_diff;
        $matched_variation = $variation;

      }

    }

  }

  if ($partial_match) {
    return array(
      'type' => 'partial_match',
      'variation' => $matched_variation,
      'variation_id' => $partial_match,
      'variation_attributes' => $variation_attributes,
    );

  } else {
    return false;

  }

}

// get variations array for the product
$wcpt_variations_cache = array();
function wcpt_get_variations($product = '')
{
  global $wcpt_variations_cache;

  if (gettype($product) !== 'object') {
    $product = wc_get_product($product);
  }

  if (!$product->is_type('variable')) {
    return false;
  }

  $id = $product->get_id();

  if (!empty($wcpt_variations_cache[$id])) {
    return $wcpt_variations_cache[$id];

  } else {
    $wcpt_variations_cache[$id] = apply_filters('wcpt_get_variations', $product->get_available_variations());

    foreach ($wcpt_variations_cache[$id] as &$variation) {
      foreach ($variation['attributes'] as $attr => $term) {
        $taxonomy = substr($attr, 10);

        // Only check taxonomy existence for pa_ attributes
        if (
          substr($taxonomy, 0, 3) === 'pa_' &&
          !taxonomy_exists(urldecode($taxonomy))
        ) {
          unset($variation['attributes'][$attr]);
        }
      }

      if ($variation['display_price']) {
        $variation['display_price'] = wcpt_price_decimal($variation['display_price']);
      }

      if ($variation['display_regular_price']) {
        $variation['display_regular_price'] = wcpt_price_decimal($variation['display_regular_price']);
      }

      $variation['wcpt_gtin'] = get_post_meta($variation['variation_id'], '_global_unique_id', true);
    }

    return $wcpt_variations_cache[$id];
  }
}

// get default variation for current product
function wcpt_get_default_variation($product)
{
  if (!$default_attributes = $product->get_default_attributes()) {
    return false;
  }

  $_default_attributes = array();
  foreach ($default_attributes as $key => $value) {
    $_default_attributes['attribute_' . $key] = $value;
  }

  return wcpt_find_closests_matching_product_variation($product, $_default_attributes);
}

// check if current variation is incomplete
function wcpt_is_incomplete_variation($product, $variation)
{

  foreach ($product->get_variation_attributes() as $attribute => $terms) {
    if (substr($attribute, 0, 3) !== 'pa_') { // custom attribute
      $attribute = sanitize_title($attribute);
    }

    if (empty($variation['attributes']['attribute_' . $attribute])) {
      return true;
    }
  }

  return false;
}

// check if all variations are out of stock
function wcpt_all_variations_out_of_stock($product_id)
{
  $product = wc_get_product($product_id);
  $children = $product->get_children();
  $out_of_stock = true;

  foreach ($children as $variation_id) {
    $variation = wc_get_product($variation_id);
    if ($variation->is_in_stock()) {
      $out_of_stock = false;
      break;
    }
  }

  return $out_of_stock;
}

/* clear product transients */
add_action('before_delete_post', 'wcpt_clear_product_transients');
add_action('save_post', 'wcpt_clear_product_transients');
function wcpt_clear_product_transients($post_id)
{
  if (get_post_type($post_id) == 'product') {
    delete_transient('wcpt_variations_' . $post_id);
  }
}

// links to duplicate / export product table post
add_filter('post_row_actions', 'wcpt_duplicate_export_post_link', 10000, 2);
function wcpt_duplicate_export_post_link($actions, $post)
{
  if (
    current_user_can('edit_posts') &&
    $post->post_type == 'wc_product_table'
  ) {
    if (defined('WCPT_PRO')) {
      $actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=wcpt_duplicate_post_as_draft&post=' . $post->ID, WCPT_PLUGIN_PATH, 'duplicate_nonce') . '" title="Duplicate this table">Duplicate</a>';
      $actions['export'] = '<a href="#" class="wcpt-export-single-table" data-id="' . $post->ID . '" title="Export this table">Export</a>';
    } else {
      $actions['duplicate'] = '<span style="color: #999">Duplicate (PRO)</span>';
      $actions['export'] = '<span style="color: #999">Export (PRO)</span>';
    }

  }
  return $actions;
}

// gets the required filter from nav -- recursive
function wcpt_check_if_nav_has_filter($arr, $type, $second = false)
{
  if (null === $arr) {
    $arr = wcpt_get_table_data();
  }
  foreach ($arr as $key => &$val) {
    if (
      $key === 'type' &&
      $val === $type &&
      (
        !$second ||
        $type === 'taxonomy_filter' && $second === $arr['taxonomy'] ||
        $type === 'attribute_filter' && $second === $arr['attribute_name'] ||
        $type === 'brand_filter' && $second === 'product_brand'
      )
    ) {
      return true;
    } else if (
      gettype($val) == 'array' &&
      TRUE === wcpt_check_if_nav_has_filter($val, $type, $second)
    ) {
      return true;
    }
  }
}

// ensure search settings are attached
add_filter('wcpt_settings', 'wcpt_settings__search', 2, 10);
function wcpt_settings__search($table_data, $ctx)
{
  if (!function_exists('wc_get_attribute_taxonomies')) {
    return $table_data;
  }

  // update attribute and custom field list

  // attribute integrity
  $attributes = array();
  foreach (wc_get_attribute_taxonomies() as $attribute) {
    $match = false;
    if (isset($table_data['search']['attribute'])) {
      foreach ($table_data['search']['attribute']['items'] as $item) {
        if ($item['item'] === $attribute->attribute_name) {
          $attributes[] = $item;
          $match = true;
          break;
        }
      }
    }

    if (!$match) {
      $attributes[] = array(
        'item' => $attribute->attribute_name,
        'label' => $attribute->attribute_label,
        'enabled' => true,
        'custom_rules_enabled' => false,
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
      );
    }
  }

  $table_data['search']['attribute']['items'] = $attributes;

  // custom field integrity
  if (!isset($table_data['search']['custom_field'])) {
    $table_data['search']['custom_field'] = array();
  }
  if (!isset($table_data['search']['custom_field']['items'])) {
    $table_data['search']['custom_field']['items'] = array();
  }

  $custom_fields = array();
  foreach (wcpt_get_product_custom_fields() as $meta_name) {
    $match = false;

    // get previous settings
    foreach ($table_data['search']['custom_field']['items'] as $item) {
      if ($item['item'] == $meta_name) {
        $custom_fields[] = $item;
        $match = true;
        break;
      }
    }

    // generate fresh settings
    if (!$match) {
      $custom_fields[] = array(
        'item' => $meta_name,
        'label' => $meta_name,
        'enabled' => true,
        'custom_rules_enabled' => false,
        'rules' => array(
          'phrase_exact_enabled' => true,
          'phrase_exact_score' => 80,

          'phrase_like_enabled' => true,
          'phrase_like_score' => 60,

          'keyword_exact_enabled' => true,
          'keyword_exact_score' => 40,

          'keyword_like_enabled' => true,
          'keyword_like_score' => 20,
        )
      );
    }
  }

  $table_data['search']['custom_field']['items'] = $custom_fields;

  return $table_data;
}

function wcpt_get_product_custom_fields()
{
  if (!empty($GLOBALS['WCPT_CF'])) {
    return $GLOBALS['WCPT_CF'];
  }

  if (!$custom_fields = get_transient('wcpt_custom_fields')) {
    global $wpdb;

    $query = "SELECT DISTINCT $wpdb->postmeta.meta_key 
    FROM $wpdb->postmeta 
    INNER JOIN $wpdb->posts ON $wpdb->posts.ID = $wpdb->postmeta.post_id
    WHERE $wpdb->posts.post_type = 'product'
    LIMIT 200";

    // // product ids
    // $query = "SELECT ID FROM $wpdb->posts WHERE post_type='product'";
    // $product_ids = $wpdb->get_col($query);

    // // custom fields
    // $query = "SELECT DISTINCT meta_key FROM $wpdb->postmeta meta WHERE post_id IN (". implode( ", ", $product_ids ) .")";

    $custom_fields = array();
    foreach ($wpdb->get_col($query) as $meta_name) {
      if (
        '_' == substr($meta_name, 0, 1) ||
        'total_sales' == $meta_name
      ) {
        continue;
      } else {
        $custom_fields[] = $meta_name;
      }
    }

    set_transient('wcpt_custom_fields', $custom_fields, WEEK_IN_SECONDS);

  }

  $GLOBALS['WCPT_CF'] = $custom_fields;
  return $custom_fields;
}

// ensure product brand taxonomy is accounted for in the archive override settings
add_filter('wcpt_settings', 'wcpt_settings__archive_override_taxonomies', 2, 10);
function wcpt_settings__archive_override_taxonomies($settings, $ctx)
{
  if (empty($settings['archive_override']['brand'])) {
    $settings['archive_override']['brand'] = array(
      "default" => "default",
      "other_rules" => array(
        "attribute" => array(),
        "table_id" => ""
      )
    );
  }
  return $settings;
}


// refresh custom field list 
add_action('admin_init', 'wcpt_refresh_custom_fields');
function wcpt_refresh_custom_fields()
{
  if (
    is_admin() &&
    !empty($_GET['wcpt_refresh_custom_fields']) &&
    current_user_can('manage_options') // Only allow administrators
  ) {
    delete_transient('wcpt_custom_fields');
    wp_safe_redirect(admin_url('edit.php?post_type=wc_product_table&page=wcpt-settings#search'));
  }
}

/* ajax add to cart */
add_action('wc_ajax_wcpt_cart', 'wcpt_cart');
add_action('wp_ajax_wcpt_cart', 'wcpt_cart');
add_action('wp_ajax_nopriv_wcpt_cart', 'wcpt_cart');
function wcpt_cart()
{
  // note: 'wcpt_process_cart_payload' runs earlier at wp_loaded this is just response to the ajax request

  $in_cart = array();
  $in_cart_total = array();

  $product_table_cart_action = !empty($_REQUEST['wcpt_payload']) && (!empty($_REQUEST['wcpt_payload']['products']) || !empty($_REQUEST['wcpt_payload']['variation_form']));

  foreach (WC()->cart->get_cart() as $item => $values) {
    if ( // criteria for skipping item from count
      !$values['quantity'] ||
      !apply_filters('wcpt_permit_item_in_cart_count', TRUE, $values)
    ) {
      continue;
    }

    // variation
    if (!empty($values['variation_id'])) {
      if (empty($in_cart[$values['product_id']])) {
        $in_cart[$values['product_id']] = array();
        $in_cart_total[$values['product_id']] = array();
      }

      if (empty($in_cart[$values['product_id']][$values['variation_id']])) {
        $in_cart[$values['product_id']][$values['variation_id']] = (string) $values['quantity'];
      } else {
        $in_cart[$values['product_id']][$values['variation_id']] = (string) ((float) $in_cart[$values['product_id']][$values['variation_id']] + $values['quantity']);
      }

      if (empty($in_cart_total[$values['product_id']][$values['variation_id']])) {
        $in_cart_total[$values['product_id']][$values['variation_id']] = $values['line_subtotal'];
      } else {
        $in_cart_total[$values['product_id']][$values['variation_id']] = (string) ((float) $in_cart_total[$values['product_id']][$values['variation_id']] + $values['line_subtotal']);
      }

      // other than variation
    } else {
      if (!isset($in_cart[$values['product_id']])) {
        $in_cart[$values['product_id']] = 0;
      }
      $in_cart[$values['product_id']] += (string) $values['quantity'];

      if (!isset($in_cart_total[$values['product_id']])) {
        $in_cart_total[$values['product_id']] = 0;
      }

      $in_cart_total[$values['product_id']] += $values['line_subtotal'];

    }

  }

  $notice = '';
  $success = !wc_notice_count('error');
  $mini_cart = '';
  if ($product_table_cart_action) {
    // collect errors
    if (!$success) {
      ob_start();
      wc_print_notices();
      $notice = ob_get_clean();
    }

    // clear add to cart notices
    wc_clear_notices();

    // collect mini cart
    ob_start();
    woocommerce_mini_cart();
    $mini_cart = ob_get_clean();
  }

  ob_start();
  include_once(apply_filters('wcpt_template', WCPT_PLUGIN_PATH . 'templates/cart-widget.php', 'cart-widget.php'));
  $cart_widget = ob_get_clean();

  $payload = array(); // @TODO: only use one
  if (!empty($_REQUEST['_wcpt_payload'])) {
    $payload = $_REQUEST['_wcpt_payload'];
  } else if (!empty($_REQUEST['wcpt_payload'])) {
    $payload = $_REQUEST['wcpt_payload'];
  }

  $table_data = array(
    'success' => $success,
    'cart_hash' => apply_filters('woocommerce_add_to_cart_hash', WC()->cart->get_cart_for_session() ? md5(json_encode(WC()->cart->get_cart_for_session())) : '', WC()->cart->get_cart_for_session()),
    'cart_quantity' => WC()->cart->get_cart_contents_count(),
    'in_cart' => $in_cart,
    'in_cart_total' => $in_cart_total,
    'notice' => $notice,
    'payload' => $payload,
    'cart_widget' => $cart_widget,
    'fragments' => apply_filters(
      'woocommerce_add_to_cart_fragments',
      array(
        'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
      )
    ),
    'cart_hash' => apply_filters('woocommerce_add_to_cart_hash', WC()->cart->get_cart_for_session() ? md5(json_encode(WC()->cart->get_cart_for_session())) : '', WC()->cart->get_cart_for_session()),
    'cart_quantity' => WC()->cart->get_cart_contents_count(),
    'product_table_cart_action' => $product_table_cart_action,
  );

  wp_send_json($table_data);
}

add_filter('wcpt_template', 'wcpt_get_template_from_theme', 10, 2);
function wcpt_get_template_from_theme($location, $template)
{
  if (file_exists(get_stylesheet_directory() . '/wc-product-table/' . $template)) { // child theme
    return get_stylesheet_directory() . '/wc-product-table/' . $template;

  } else if (file_exists(get_template_directory() . '/wc-product-table/' . $template)) { // parent theme
    return get_template_directory() . '/wc-product-table/' . $template;
  }

  return $location;
}

function wcpt_get_table_query_string()
{

  $table_data = wcpt_get_table_data();
  $table_id = $table_data['id'];

  $query_string_arr = array();

  foreach ($_GET as $key => $val) {

    if (
      !in_array(
        strtolower($key),
        apply_filters('wcpt_permitted_params', [])
      ) &&
      (
        empty($val) ||
        0 !== strpos($key, (string) $table_id) || // table id should be key prefix
        in_array(
          strtolower($key),
          array( // excluded
            $table_id . '_sc_attrs',
            $table_id . '_url',
          )
        )
      )

    ) {
      continue;
    }

    if (
      is_array($val)
      &&
      count(array_filter($val, 'is_string')) === count($val)
    ) {
      $imploded_val = implode('', $val);
      if (!$imploded_val) {

        continue;
      }

      $val = array_unique(array_values($val));
    }


    if (
      0 !== strpos($key, 'search') &&
      !is_array($val)
    ) {
      // $val = htmlentities( stripslashes( $val ) );
      $val = htmlentities($val);
    }

    $query_string_arr[$key] = $val;
  }

  return add_query_arg($query_string_arr, '');
}

function wcpt_get_archive_query_string($archive, $term_id = '')
{
  if ($archive == 'category') {
    $term = get_term_by('id', $term_id, 'product_cat');
    extract(wcpt_archive_override__get_table_vars($archive, $term->slug));

  } else if ($archive == 'shop') {
    extract(wcpt_archive_override__get_table_vars($archive));

  }

  if (!$wcpt_table_id) {
    return '';
  }

  if (
    $wcpt_table_id == 'custom' &&
    $wcpt_custom_table
  ) {
    $wcpt_table_id = wcpt_extract_id_from_shortcode($wcpt_custom_table);
  }

  if (is_numeric($wcpt_table_id)) {
    $table_data = wcpt_get_table_data();
    $table_id = $table_data['id'];

    $query_string = wcpt_get_table_query_string();
    parse_str(ltrim($query_string, '?'), $params);

    if (isset($params[$table_id . '_product_cat'])) {
      unset($params[$table_id . '_product_cat']);
    }

    if (isset($params[$table_id . '_paged'])) {
      unset($params[$table_id . '_paged']);
    }

    $query_string = '?' . http_build_query($params);

    // from shop
    if (
      empty($params[$table_id . '_from_shop']) &&
      (
        !empty($table_data['query']['sc_attrs']['_archive']) &&
        $table_data['query']['sc_attrs']['_archive'] == 'shop'
      )
    ) {
      $query_string .= '&' . $table_data['id'] . '_from_shop=true';
    }

    return str_replace($table_id . '_', $wcpt_table_id . '_', $query_string);
  }

  return '';

}

function wcpt_extract_id_from_shortcode($shortcode)
{
  $id = '';

  // get id from shortcode
  $arr = array();
  preg_match('/id\s*=\s*[\'"](.*)[\'"]/U', $shortcode, $arr);

  if (!empty($arr[1])) {
    $id = $arr[1];
  }

  // get id from name in shortcode
  if (!$id) {
    preg_match('/name\s*=\s*[\'"](.*)[\'"]/U', $shortcode, $arr);

    if (!empty($arr[1])) {
      $name = $arr[1];
      $id = wcpt_get_table_id_from_name($name);
    }
  }

  return $id;
}


function wcpt_get_grouped_product_price($product = false)
{
  $prices = array(
    'min_price' => 0,
    'max_price' => 0
  );

  if (!$product) {
    global $product;
  }

  $tax_display_mode = get_option('woocommerce_tax_display_shop');
  $child_prices = array();
  $children = array_filter(array_map('wc_get_product', $product->get_children()), 'wc_products_array_filter_visible_grouped');

  foreach ($children as $child) {
    if ('' !== $child->get_price()) {
      // $child_price = 'incl' === $tax_display_mode ? wc_get_price_including_tax( $child ) : wc_get_price_excluding_tax( $child );

      if (apply_filters('wcpt_product_is_on_sale', $child->is_on_sale(), $child)) {
        $child_prices[] = apply_filters('wcpt_product_get_sale_price', $child->get_sale_price(), $child);
      } else {
        $child_prices[] = apply_filters('wcpt_product_get_regular_price', $child->get_price(), $child);
      }
    }
  }

  if (!empty($child_prices)) {
    $min_price = $prices['min_price'] = min($child_prices);
    $max_price = $prices['max_price'] = max($child_prices);
  } else {
    $min_price = '';
    $max_price = '';
  }

  if ('' !== $min_price) {
    $is_free = 0 === $min_price && 0 === $max_price;

    if ($is_free) {
      $prices = apply_filters('woocommerce_grouped_free_price_html', __('Free!', 'woocommerce'), $product);
    }
  } else {
    $prices = apply_filters('woocommerce_grouped_empty_price_html', '', $product);
  }

  return $prices;
}

function wcpt_get_the_browser()
{
  global $wcpt_browser;
  if (!empty($wcpt_browser)) {
    return $wcpt_browser;
  }

  if (strpos($_SERVER['HTTP_USER_AGENT'], 'MSIE') !== false)
    $wcpt_browser = 'Internet explorer';
  elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Trident') !== false)
    $wcpt_browser = 'Internet explorer';
  elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Firefox') !== false)
    $wcpt_browser = 'Mozilla Firefox';
  elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== false)
    $wcpt_browser = 'Google Chrome';
  elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Opera Mini') !== false)
    $wcpt_browser = "Opera Mini";
  elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Opera') !== false)
    $wcpt_browser = "Opera";
  elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Safari') !== false)
    $wcpt_browser = "Safari";
  else
    $wcpt_browser = 'Other';

  return $wcpt_browser;
}

function wcpt_get_shop_table_id()
{
  $shop_table_id = false;

  if (defined('WCPT_PRO')) {
    $shop_vars = wcpt_archive_override__get_table_vars('shop');
    if ($shop_vars['wcpt_table_id'] == 'custom') {
      $shop_table_id = wcpt_extract_id_from_shortcode($shop_vars['wcpt_custom_table']);
    } else {
      $shop_table_id = $shop_vars['wcpt_table_id'];
    }
  }

  return $shop_table_id;
}

// mobile detect
$wcpt_device = null;

function wcpt_get_device()
{
  global $wcpt_device;

  if (defined('DOING_AJAX')) {
    $table_data = wcpt_get_table_data();
    if (!empty($table_data)) {
      $table_id = $table_data['id'];
      if (
        !empty($_REQUEST[$table_id . '_device']) &&
        in_array($_REQUEST[$table_id . '_device'], ['laptop', 'tablet', 'phone'])
      ) {
        return $_REQUEST[$table_id . '_device'];
      }
    }
  }

  if (!empty($wcpt_device)) {
    return $wcpt_device;
  }

  if (!class_exists('Mobile_Detect')) {
    require(WCPT_PLUGIN_PATH . 'vendor/Mobile_Detect.php');
  }

  $mobile_detect = new Mobile_Detect();

  $device = 'laptop';

  if ($mobile_detect->isMobile()) {
    $device = 'phone';
  }

  $wcpt_device = $device;

  return $wcpt_device;
}