<?php
/**
 * Bootstrap.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires everything together once WooCommerce is loaded.
 */
class WCWG_Plugin {

	const CRON_PURGE = 'wcwg_purge_log';

	/**
	 * Singleton.
	 *
	 * @var WCWG_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return WCWG_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'as_schedule_single_action' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_woocommerce_notice' ) );
			return;
		}

		load_plugin_textdomain( 'webhook-guard-for-woocommerce', false, dirname( plugin_basename( WCWG_FILE ) ) . '/languages' );

		if ( get_option( 'wcwg_db_version' ) !== WCWG_VERSION ) {
			WCWG_Store::install();
		}

		WCWG_Retry::init();
		WCWG_Recovery::init();
		WCWG_Site_Health::init();

		add_action( self::CRON_PURGE, array( $this, 'purge' ) );
		if ( ! wp_next_scheduled( self::CRON_PURGE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_PURGE );
		}

		if ( is_admin() ) {
			require_once WCWG_DIR . 'admin/class-wcwg-admin.php';
			WCWG_Admin::init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once WCWG_DIR . 'includes/class-wcwg-cli.php';
			WP_CLI::add_command( 'wc webhook-guard', 'WCWG_CLI' );
		}
	}

	/**
	 * Notice when WooCommerce is absent.
	 */
	public function missing_woocommerce_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Webhook Guard for WooCommerce needs WooCommerce 8.0 or newer to be active.', 'webhook-guard-for-woocommerce' ) . '</p></div>';
	}

	/**
	 * Daily purge.
	 */
	public function purge() {
		WCWG_Store::purge( (int) WCWG_Settings::get( 'retention_days' ) );
	}

	/**
	 * Activation: create the table.
	 */
	public static function activate() {
		WCWG_Store::install();
	}

	/**
	 * Deactivation: unschedule our cron and pending Action Scheduler jobs.
	 */
	public static function deactivate() {
		$ts = wp_next_scheduled( self::CRON_PURGE );
		if ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_PURGE );
		}
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( WCWG_Retry::ACTION_RETRY, array(), WCWG_Retry::GROUP );
			as_unschedule_all_actions( WCWG_Retry::ACTION_REPLAY, array(), WCWG_Retry::GROUP );
			as_unschedule_all_actions( WCWG_Recovery::ACTION_REENABLE, array(), WCWG_Retry::GROUP );
		}
	}
}
