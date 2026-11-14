<?php
/**
 * Admin screen: deliveries list, settings, actions.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

require_once WCWG_DIR . 'admin/class-wcwg-list-table.php';

/**
 * WooCommerce → Webhook Guard.
 */
class WCWG_Admin {

	const PAGE = 'wcwg';

	/**
	 * Register.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_wcwg_action', array( __CLASS__, 'handle_action' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WCWG_FILE ), array( __CLASS__, 'plugin_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'disabled_notice' ) );
	}

	/**
	 * Menu entry.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Webhook Guard', 'webhook-guard-for-woocommerce' ),
			__( 'Webhook Guard', 'webhook-guard-for-woocommerce' ),
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
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wcwg&tab=settings' ) ) . '">' . esc_html__( 'Settings', 'webhook-guard-for-woocommerce' ) . '</a>' );
		return $links;
	}

	/**
	 * Settings API.
	 */
	public static function register_settings() {
		register_setting(
			'wcwg_settings_group',
			WCWG_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WCWG_Settings', 'sanitize' ),
				'default'           => WCWG_Settings::defaults(),
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
		if ( ! function_exists( 'wc_load_webhooks' ) ) {
			return;
		}
		$disabled = wc_load_webhooks( 'disabled' );
		if ( ! $disabled ) {
			return;
		}
		$names = array();
		foreach ( $disabled as $w ) {
			$names[] = $w->get_name();
		}
		printf(
			'<div class="notice notice-error"><p><strong>%s</strong> %s <a href="%s">%s</a></p></div>',
			esc_html__( 'Webhook Guard:', 'webhook-guard-for-woocommerce' ),
			esc_html(
				sprintf(
					/* translators: %s: webhook names */
					_n( 'the webhook %s is disabled, its events are not being delivered.', 'these webhooks are disabled, their events are not being delivered: %s.', count( $names ), 'webhook-guard-for-woocommerce' ),
					implode( ', ', $names )
				)
			),
			esc_url( admin_url( 'admin.php?page=wcwg' ) ),
			esc_html__( 'Re-enable and replay', 'webhook-guard-for-woocommerce' )
		);
	}

	/**
	 * POST actions (replay, re-enable, bulk replay).
	 */
	public static function handle_action() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'webhook-guard-for-woocommerce' ) );
		}
		check_admin_referer( 'wcwg_action' );

		$do       = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wcwg' );
		$msg      = '';

		switch ( $do ) {
			case 'replay':
				$id  = isset( $_POST['row_id'] ) ? (int) $_POST['row_id'] : 0;
				$r   = WCWG_Retry::replay( $id );
				$msg = is_wp_error( $r ) ? $r->get_error_message() : __( 'Delivery replayed. Check the newest row for the result.', 'webhook-guard-for-woocommerce' );
				break;

			case 'bulk_replay':
				$ids = isset( $_POST['row_ids'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['row_ids'] ) ) : array();
				$n   = WCWG_Retry::queue_replays( $ids, 2 );
				/* translators: %d: count */
				$msg = sprintf( __( '%d replays queued in the background.', 'webhook-guard-for-woocommerce' ), $n );
				break;

			case 'reenable':
				$wid = isset( $_POST['webhook_id'] ) ? (int) $_POST['webhook_id'] : 0;
				$r   = WCWG_Recovery::reenable( $wid, true );
				/* translators: %d: count */
				$msg = is_wp_error( $r ) ? $r->get_error_message() : sprintf( __( 'Webhook re-enabled; %d dead deliveries queued for replay.', 'webhook-guard-for-woocommerce' ), (int) $r );
				break;

			case 'replay_dead':
				$wid  = isset( $_POST['webhook_id'] ) ? (int) $_POST['webhook_id'] : 0;
				$dead = WCWG_Store::dead_for_webhook( $wid, 500 );
				$n    = WCWG_Retry::queue_replays( wp_list_pluck( $dead, 'id' ), 2 );
				/* translators: %d: count */
				$msg = sprintf( __( '%d dead deliveries queued for replay.', 'webhook-guard-for-woocommerce' ), $n );
				break;

			case 'purge':
				$n = WCWG_Store::purge( (int) WCWG_Settings::get( 'retention_days' ) );
				/* translators: %d: count */
				$msg = sprintf( __( '%d old rows deleted.', 'webhook-guard-for-woocommerce' ), $n );
				break;
		}

		wp_safe_redirect( add_query_arg( 'wcwg_msg', rawurlencode( $msg ), remove_query_arg( 'wcwg_msg', $redirect ) ) );
		exit;
	}

	/**
	 * Render the page.
	 */
	public static function render() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'deliveries'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="wrap wcwg-wrap"><h1>' . esc_html__( 'Webhook Guard', 'webhook-guard-for-woocommerce' ) . '</h1>';

		if ( isset( $_GET['wcwg_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( sanitize_text_field( wp_unslash( $_GET['wcwg_msg'] ) ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		$tabs = array(
			'deliveries' => __( 'Deliveries', 'webhook-guard-for-woocommerce' ),
			'webhooks'   => __( 'Webhooks', 'webhook-guard-for-woocommerce' ),
			'settings'   => __( 'Settings', 'webhook-guard-for-woocommerce' ),
		);
		echo '<nav class="nav-tab-wrapper">';
		foreach ( $tabs as $k => $label ) {
			printf( '<a class="nav-tab %s" href="%s">%s</a>', $tab === $k ? 'nav-tab-active' : '', esc_url( admin_url( 'admin.php?page=wcwg&tab=' . $k ) ), esc_html( $label ) );
		}
		echo '</nav>';

		switch ( $tab ) {
			case 'settings':
				include WCWG_DIR . 'admin/views/settings.php';
				break;
			case 'webhooks':
				include WCWG_DIR . 'admin/views/webhooks.php';
				break;
			default:
				include WCWG_DIR . 'admin/views/deliveries.php';
		}
		echo '</div>';
	}
}
