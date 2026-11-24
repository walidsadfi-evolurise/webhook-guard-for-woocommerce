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
class EVOWG_Plugin {

	const CRON_PURGE = 'evowg_purge_log';

	/**
	 * Singleton.
	 *
	 * @var EVOWG_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Instance.
	 *
	 * @return EVOWG_Plugin
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

		if ( get_option( 'evowg_db_version' ) !== EVOWG_VERSION ) {
			EVOWG_Store::install();
		}

		EVOWG_Retry::init();
		EVOWG_Recovery::init();
		EVOWG_Site_Health::init();

		add_action( self::CRON_PURGE, array( $this, 'purge' ) );
		if ( ! wp_next_scheduled( self::CRON_PURGE ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_PURGE );
		}

		if ( is_admin() ) {
			require_once EVOWG_DIR . 'admin/class-evowg-admin.php';
			EVOWG_Admin::init();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once EVOWG_DIR . 'includes/class-evowg-cli.php';
			WP_CLI::add_command( 'wc webhook-recovery', 'EVOWG_CLI' );
		}
	}

	/**
	 * Notice when WooCommerce is absent.
	 */
	public function missing_woocommerce_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Evolurise Webhook Delivery Recovery for WooCommerce needs WooCommerce 8.0 or newer to be active.', 'evolurise-webhook-delivery-recovery' ) . '</p></div>';
	}

	/**
	 * Daily purge.
	 */
	public function purge() {
		EVOWG_Store::purge( (int) EVOWG_Settings::get( 'retention_days' ) );
	}

	/**
	 * Activation: create the table.
	 */
	public static function activate() {
		EVOWG_Store::install();
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
			as_unschedule_all_actions( EVOWG_Retry::ACTION_RETRY, array(), EVOWG_Retry::GROUP );
			as_unschedule_all_actions( EVOWG_Retry::ACTION_REPLAY, array(), EVOWG_Retry::GROUP );
			as_unschedule_all_actions( EVOWG_Recovery::ACTION_REENABLE, array(), EVOWG_Retry::GROUP );
		}
	}
}
