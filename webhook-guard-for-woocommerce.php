<?php
/**
 * Plugin Name:       Webhook Guard for WooCommerce
 * Plugin URI:        https://github.com/walidsadfi-evolurise/webhook-guard-for-woocommerce
 * Description:       Retries failed WooCommerce webhook deliveries with exponential backoff, keeps a replayable delivery log, alerts you when a webhook is disabled and re-enables it automatically.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Walid Sadfi
 * Author URI:        https://walidsadfi.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       webhook-guard-for-woocommerce
 * Domain Path:       /languages
 * WC requires at least: 8.0
 * WC tested up to:   11.4
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

define( 'WCWG_VERSION', '1.0.0' );
define( 'WCWG_FILE', __FILE__ );
define( 'WCWG_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCWG_URL', plugin_dir_url( __FILE__ ) );

require_once WCWG_DIR . 'includes/class-wcwg-backoff.php';
require_once WCWG_DIR . 'includes/class-wcwg-settings.php';
require_once WCWG_DIR . 'includes/class-wcwg-store.php';
require_once WCWG_DIR . 'includes/class-wcwg-alerts.php';
require_once WCWG_DIR . 'includes/class-wcwg-retry.php';
require_once WCWG_DIR . 'includes/class-wcwg-recovery.php';
require_once WCWG_DIR . 'includes/class-wcwg-site-health.php';
require_once WCWG_DIR . 'includes/class-wcwg-plugin.php';

register_activation_hook( __FILE__, array( 'WCWG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WCWG_Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'WCWG_Plugin', 'instance' ), 20 );
