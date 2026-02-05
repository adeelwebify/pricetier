<?php

/**
 * Plugin Name:       PriceTier
 * Plugin URI:        https://adeelm.com/plugin/pricetier
 * Description:       Cost-based pricing tiers for selected WooCommerce users.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Adeel M.
 * Author URI:        https://adeelm.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pricetier
 * Domain Path:       /languages
 *
 * WC requires at least: 6.0
 * WC tested up to:     8.0
 */


defined('ABSPATH') || exit;

/**
 *
 * Plugin constants
 *
 */
define('PRICETIER_VERSION', '1.0.0');
define('PRICETIER_FILE', __FILE__);
define('PRICETIER_DIR', plugin_dir_path(__FILE__));
define('PRICETIER_URL', plugin_dir_url(__FILE__));

/**
 *
 * Plugin activation and deactivation hooks
 *
 */
register_activation_hook(PRICETIER_FILE, function() {
  if (!get_option('pricetier_version')) update_option('pricetier_version', PRICETIER_VERSION);
  if (!get_option('pricetier_settings')) update_option('pricetier_settings', ['enabled' => true, 'cost_meta_key' => '']);
  if (!get_option('pricetier_rules')) update_option('pricetier_rules', []);
});

register_deactivation_hook(PRICETIER_FILE, function() {
  // Intentionally empty.
});

/**
 *
 * WooCommerce compatibility declaration
 *
 */
add_action('before_woocommerce_init', function () {
  if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
      'custom_order_tables',
      PRICETIER_FILE,
      true
    );
  }
});


/**
 *
 * Hide WordPress notices
 *
 */
add_action('in_admin_header', function () {

  if (!isset($_GET['page']) || $_GET['page'] !== 'pricetier') {
    return;
  }

  remove_all_actions('admin_notices');
  remove_all_actions('all_admin_notices');
  remove_all_actions('network_admin_notices');
});



/**
 * Plugin initialization
 *
 * Bootstraps the plugin by loading required files and initializing the main plugin class.
 * This function checks for WooCommerce dependency, loads the Composer autoloader if available,
 * and instantiates the main Plugin class.
 *
 * @return void
 *
 */
function pricetier_init(): void {

  // WooCommerce is required.
  if (! class_exists('WooCommerce')) {
    return;
  }

  // Load Composer autoloader if present.
  $autoload = PRICETIER_DIR . 'vendor/autoload.php';
  if (file_exists($autoload)) {
    require_once $autoload;
  }

  // Load core class.
  require_once PRICETIER_DIR . 'includes/Plugin.php';

  // Load text domain.
  load_plugin_textdomain('pricetier', false, dirname(plugin_basename(__FILE__)) . '/languages');

  // Bootstrap plugin.
  if (class_exists('PriceTier\Plugin')) {
    PriceTier\Plugin::instance();
  }
}

add_action('plugins_loaded', 'pricetier_init');
