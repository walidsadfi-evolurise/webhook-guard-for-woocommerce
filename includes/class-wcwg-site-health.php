<?php
/**
 * Site Health checks.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Two tests: disabled webhooks, dead deliveries in the last 24 h.
 */
class WCWG_Site_Health {

	/**
	 * Register.
	 */
	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'register_tests' ) );
	}

	/**
	 * Add tests.
	 *
	 * @param array $tests Tests.
	 * @return array
	 */
	public static function register_tests( $tests ) {
		$tests['direct']['wcwg_disabled_webhooks'] = array(
			'label' => __( 'WooCommerce webhooks are active', 'webhook-guard-for-woocommerce' ),
			'test'  => array( __CLASS__, 'test_disabled' ),
		);
		$tests['direct']['wcwg_dead_deliveries']   = array(
			'label' => __( 'WooCommerce webhook deliveries are reaching their receivers', 'webhook-guard-for-woocommerce' ),
			'test'  => array( __CLASS__, 'test_dead' ),
		);
		return $tests;
	}

	/**
	 * Disabled webhooks test.
	 *
	 * @return array
	 */
	public static function test_disabled() {
		$disabled = function_exists( 'wc_load_webhooks' ) ? wc_load_webhooks( 'disabled' ) : array();
		$base     = array(
			'label'       => __( 'All WooCommerce webhooks are active', 'webhook-guard-for-woocommerce' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'WooCommerce', 'webhook-guard-for-woocommerce' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'No webhook is currently disabled.', 'webhook-guard-for-woocommerce' ) . '</p>',
			'actions'     => '',
			'test'        => 'wcwg_disabled_webhooks',
		);
		if ( empty( $disabled ) ) {
			return $base;
		}
		$names = array();
		foreach ( $disabled as $w ) {
			$names[] = $w->get_name();
		}
		$base['label']       = sprintf(
			/* translators: %d: number of webhooks */
			_n( '%d WooCommerce webhook is disabled', '%d WooCommerce webhooks are disabled', count( $disabled ), 'webhook-guard-for-woocommerce' ),
			count( $disabled )
		);
		$base['status']      = 'critical';
		$base['badge']['color'] = 'red';
		$base['description'] = '<p>' . esc_html__( 'Events are not being sent to these receivers:', 'webhook-guard-for-woocommerce' ) . ' <strong>' . esc_html( implode( ', ', $names ) ) . '</strong></p>';
		$base['actions']     = '<a href="' . esc_url( admin_url( 'admin.php?page=wcwg' ) ) . '">' . esc_html__( 'Open Webhook Guard', 'webhook-guard-for-woocommerce' ) . '</a>';
		return $base;
	}

	/**
	 * Dead deliveries test.
	 *
	 * @return array
	 */
	public static function test_dead() {
		$counts = WCWG_Store::counts( 24 );
		$dead   = (int) $counts[ WCWG_Store::STATUS_DEAD ];
		$base   = array(
			'label'       => __( 'No webhook delivery was lost in the last 24 hours', 'webhook-guard-for-woocommerce' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'WooCommerce', 'webhook-guard-for-woocommerce' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Every delivery either succeeded or was recovered by a retry.', 'webhook-guard-for-woocommerce' ) . '</p>',
			'actions'     => '',
			'test'        => 'wcwg_dead_deliveries',
		);
		if ( 0 === $dead ) {
			return $base;
		}
		$base['label']       = sprintf(
			/* translators: %d: number of deliveries */
			_n( '%d webhook delivery gave up in the last 24 hours', '%d webhook deliveries gave up in the last 24 hours', $dead, 'webhook-guard-for-woocommerce' ),
			$dead
		);
		$base['status']      = 'recommended';
		$base['badge']['color'] = 'orange';
		$base['description'] = '<p>' . esc_html__( 'These events never reached their receiver. They can be replayed from the Webhook Guard screen.', 'webhook-guard-for-woocommerce' ) . '</p>';
		$base['actions']     = '<a href="' . esc_url( admin_url( 'admin.php?page=wcwg&status=dead' ) ) . '">' . esc_html__( 'Review dead deliveries', 'webhook-guard-for-woocommerce' ) . '</a>';
		return $base;
	}
}
