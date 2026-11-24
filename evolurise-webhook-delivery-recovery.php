<?php
/**
 * Plugin Name:       Evolurise Webhook Delivery Recovery for WooCommerce
 * Plugin URI:        https://github.com/walidsadfi-evolurise/webhook-guard-for-woocommerce
 * Description:       Retries failed WooCommerce webhook deliveries with exponential backoff, keeps a replayable delivery log, alerts you when a webhook is disabled and re-enables it automatically.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Evolurise
 * Author URI:        https://evolurise.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       evolurise-webhook-delivery-recovery
 * WC requires at least: 8.0
 * WC tested up to:   11.3
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

define( 'EVOWG_VERSION', '1.0.0' );
define( 'EVOWG_FILE', __FILE__ );
define( 'EVOWG_DIR', plugin_dir_path( __FILE__ ) );
define( 'EVOWG_URL', plugin_dir_url( __FILE__ ) );

require_once EVOWG_DIR . 'includes/class-evowg-backoff.php';
require_once EVOWG_DIR . 'includes/class-evowg-settings.php';
require_once EVOWG_DIR . 'includes/class-evowg-store.php';
require_once EVOWG_DIR . 'includes/class-evowg-alerts.php';
require_once EVOWG_DIR . 'includes/class-evowg-retry.php';
require_once EVOWG_DIR . 'includes/class-evowg-recovery.php';
require_once EVOWG_DIR . 'includes/class-evowg-site-health.php';
require_once EVOWG_DIR . 'includes/class-evowg-plugin.php';

register_activation_hook( __FILE__, array( 'EVOWG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EVOWG_Plugin', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'EVOWG_Plugin', 'instance' ), 20 );
