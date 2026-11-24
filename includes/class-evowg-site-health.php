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
class EVOWG_Site_Health {

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
		$tests['direct']['evowg_disabled_webhooks'] = array(
			'label' => __( 'WooCommerce webhooks are active', 'evolurise-webhook-delivery-recovery' ),
			'test'  => array( __CLASS__, 'test_disabled' ),
		);
		$tests['direct']['evowg_dead_deliveries']   = array(
			'label' => __( 'WooCommerce webhook deliveries are reaching their receivers', 'evolurise-webhook-delivery-recovery' ),
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
		$disabled = EVOWG_Recovery::webhooks( 'disabled' );
		$base     = array(
			'label'       => __( 'All WooCommerce webhooks are active', 'evolurise-webhook-delivery-recovery' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'WooCommerce', 'evolurise-webhook-delivery-recovery' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'No webhook is currently disabled.', 'evolurise-webhook-delivery-recovery' ) . '</p>',
			'actions'     => '',
			'test'        => 'evowg_disabled_webhooks',
		);
		if ( empty( $disabled ) ) {
			return $base;
		}
		$names = array();
		foreach ( $disabled as $w ) {
			$names[] = $w->get_name();
		}
		$base['label'] = sprintf(
			/* translators: %d: number of webhooks */
			_n( '%d WooCommerce webhook is disabled', '%d WooCommerce webhooks are disabled', count( $disabled ), 'evolurise-webhook-delivery-recovery' ),
			count( $disabled )
		);
		$base['status']         = 'critical';
		$base['badge']['color'] = 'red';
		$base['description']    = '<p>' . esc_html__( 'Events are not being sent to these receivers:', 'evolurise-webhook-delivery-recovery' ) . ' <strong>' . esc_html( implode( ', ', $names ) ) . '</strong></p>';
		$base['actions']        = '<a href="' . esc_url( admin_url( 'admin.php?page=evowg' ) ) . '">' . esc_html__( 'Open Webhook Delivery Recovery', 'evolurise-webhook-delivery-recovery' ) . '</a>';
		return $base;
	}

	/**
	 * Dead deliveries test.
	 *
	 * @return array
	 */
	public static function test_dead() {
		$counts = EVOWG_Store::counts( 24 );
		$dead   = (int) $counts[ EVOWG_Store::STATUS_DEAD ];
		$base   = array(
			'label'       => __( 'No webhook delivery was lost in the last 24 hours', 'evolurise-webhook-delivery-recovery' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'WooCommerce', 'evolurise-webhook-delivery-recovery' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Every delivery either succeeded or was recovered by a retry.', 'evolurise-webhook-delivery-recovery' ) . '</p>',
			'actions'     => '',
			'test'        => 'evowg_dead_deliveries',
		);
		if ( 0 === $dead ) {
			return $base;
		}
		$base['label'] = sprintf(
			/* translators: %d: number of deliveries */
			_n( '%d webhook delivery gave up in the last 24 hours', '%d webhook deliveries gave up in the last 24 hours', $dead, 'evolurise-webhook-delivery-recovery' ),
			$dead
		);
		$base['status']         = 'recommended';
		$base['badge']['color'] = 'orange';
		$base['description']    = '<p>' . esc_html__( 'These events never reached their receiver. They can be replayed from the Webhook Delivery Recovery screen.', 'evolurise-webhook-delivery-recovery' ) . '</p>';
		$base['actions']        = '<a href="' . esc_url( admin_url( 'admin.php?page=evowg&status=dead' ) ) . '">' . esc_html__( 'Review dead deliveries', 'evolurise-webhook-delivery-recovery' ) . '</a>';
		return $base;
	}
}
