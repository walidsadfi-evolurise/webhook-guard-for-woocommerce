<?php
/**
 * Admin screen: deliveries list, settings, actions.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

require_once EVOWG_DIR . 'admin/class-evowg-list-table.php';

/**
 * WooCommerce → Webhook Guard.
 */
class EVOWG_Admin {

	const PAGE = 'evowg';

	/**
	 * Register.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_evowg_action', array( __CLASS__, 'handle_action' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( EVOWG_FILE ), array( __CLASS__, 'plugin_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'disabled_notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Styles and script for the plugin's own screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		if ( 'woocommerce_page_' . self::PAGE !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'evowg-admin', plugins_url( 'assets/admin.css', EVOWG_FILE ), array(), EVOWG_VERSION );
		wp_enqueue_script( 'evowg-admin', plugins_url( 'assets/admin.js', EVOWG_FILE ), array(), EVOWG_VERSION, true );
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Webhook Guard', 'evolurise-webhook-guard-for-woocommerce' ),
			__( 'Webhook Guard', 'evolurise-webhook-guard-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Settings link on the plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function plugin_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=evowg&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'evolurise-webhook-guard-for-woocommerce' ) . '</a>' );
		return $links;
	}

	/**
	 * Settings API.
	 */
	public static function register_settings() {
		register_setting(
			'evowg_settings_group',
			EVOWG_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'EVOWG_Settings', 'sanitize' ),
				'default'           => EVOWG_Settings::defaults(),
			)
		);
	}

	/**
	 * Global notice on WooCommerce screens when a webhook is disabled.
	 */
	public static function disabled_notice() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( (string) $screen->id, 'woocommerce' ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$disabled = EVOWG_Recovery::webhooks( 'disabled' );
		if ( ! $disabled ) {
			return;
		}
		$names = array();
		foreach ( $disabled as $w ) {
			$names[] = $w->get_name();
		}
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Webhook Guard:', 'evolurise-webhook-guard-for-woocommerce' ),
			esc_html(
				sprintf(
					/* translators: %s: webhook names */
					_n( 'the webhook %s is disabled, its events are not being delivered.', 'these webhooks are disabled, their events are not being delivered: %s.', count( $names ), 'evolurise-webhook-guard-for-woocommerce' ),
					implode( ', ', $names )
				)
			),
			esc_url( admin_url( 'admin.php?page=evowg' ) ),
			esc_html__( 'Re-enable and replay', 'evolurise-webhook-guard-for-woocommerce' )
		);
	}

	/**
	 * POST actions (replay, re-enable, bulk replay).
	 */
	public static function handle_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'evolurise-webhook-guard-for-woocommerce' ) );
		}
		check_admin_referer( 'evowg_action' );

		$do       = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=evowg' );
		$msg      = '';

		switch ( $do ) {
			case 'replay':
				$id  = isset( $_POST['row_id'] ) ? (int) $_POST['row_id'] : 0;
				$r   = EVOWG_Retry::replay( $id );
				$msg = is_wp_error( $r ) ? $r->get_error_message() : __( 'Delivery replayed. Check the newest row for the result.', 'evolurise-webhook-guard-for-woocommerce' );
				break;

			case 'bulk_replay':
				$ids = isset( $_POST['row_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['row_ids'] ) ) : array();
				$n   = EVOWG_Retry::queue_replays( $ids, 2 );
				/* translators: %d: count */
				$msg = sprintf( __( '%d replays queued in the background.', 'evolurise-webhook-guard-for-woocommerce' ), $n );
				break;

			case 'reenable':
				$wid = isset( $_POST['webhook_id'] ) ? (int) $_POST['webhook_id'] : 0;
				$r   = EVOWG_Recovery::reenable( $wid, true );
				/* translators: %d: count */
				$msg = is_wp_error( $r ) ? $r->get_error_message() : sprintf( __( 'Webhook re-enabled; %d dead deliveries queued for replay.', 'evolurise-webhook-guard-for-woocommerce' ), (int) $r );
				break;

			case 'replay_dead':
				$wid  = isset( $_POST['webhook_id'] ) ? (int) $_POST['webhook_id'] : 0;
				$dead = EVOWG_Store::dead_for_webhook( $wid, 500 );
				$n    = EVOWG_Retry::queue_replays( wp_list_pluck( $dead, 'id' ), 2 );
				/* translators: %d: count */
				$msg = sprintf( __( '%d dead deliveries queued for replay.', 'evolurise-webhook-guard-for-woocommerce' ), $n );
				break;

			case 'purge':
				$n = EVOWG_Store::purge( (int) EVOWG_Settings::get( 'retention_days' ) );
				/* translators: %d: count */
				$msg = sprintf( __( '%d old rows deleted.', 'evolurise-webhook-guard-for-woocommerce' ), $n );
				break;
		}

		if ( '' !== $msg ) {
			// Shown once on the next page load; never read back from the URL.
			set_transient( 'evowg_msg_' . get_current_user_id(), $msg, MINUTE_IN_SECONDS );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render the page.
	 */
	public static function render() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'deliveries'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap evowg-wrap"><h1>' . esc_html__( 'Webhook Guard', 'evolurise-webhook-guard-for-woocommerce' ) . '</h1>';

		$msg = get_transient( 'evowg_msg_' . get_current_user_id() );
		if ( false !== $msg ) {
			delete_transient( 'evowg_msg_' . get_current_user_id() );
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
		}

		$tabs = array(
			'deliveries' => __( 'Deliveries', 'evolurise-webhook-guard-for-woocommerce' ),
			'webhooks'   => __( 'Webhooks', 'evolurise-webhook-guard-for-woocommerce' ),
			'settings'   => __( 'Settings', 'evolurise-webhook-guard-for-woocommerce' ),
		);
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $label ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', $tab === $k ? 'nav-tab-active' : '', esc_url( admin_url( 'admin.php?page=evowg&tab=' . $k ) ), esc_html( $label ) );
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'settings':
				include EVOWG_DIR . 'admin/views/settings.php';
				break;
			case 'webhooks':
				include EVOWG_DIR . 'admin/views/webhooks.php';
				break;
			default:
				include EVOWG_DIR . 'admin/views/deliveries.php';
		}
		echo '</div>';
	}
}
