<?php
/**
 * End-to-end: a webhook whose receiver is down gets a retry scheduled; after the receiver
 * recovers the retry succeeds; a disabled webhook is re-enabled and its dead rows replayed.
 *
 * @package WebhookGuard
 */

/**
 * Runs against a real WordPress + WooCommerce (see .github/workflows/ci.yml).
 */
class RetryIntegrationTest extends WP_UnitTestCase {

	/**
	 * What the fake receiver answers: int status, or 'error' for a WP_Error.
	 *
	 * @var int|string
	 */
	private $receiver = 503;

	/**
	 * Requests seen by the fake receiver.
	 *
	 * @var array
	 */
	private $seen = array();

	/**
	 * Set up a webhook and intercept HTTP.
	 */
	public function set_up() {
		parent::set_up();
		$this->seen = array();
		add_filter( 'pre_http_request', array( $this, 'fake_receiver' ), 10, 3 );
		add_filter( 'woocommerce_webhook_deliver_async', '__return_false' );
		update_option( WCWG_Settings::OPTION, WCWG_Settings::sanitize( array( 'max_retries' => 2, 'base_delay' => 60, 'alert_throttle' => 0 ) ) );
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . WCWG_Store::table() ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_receiver' ), 10 );
		remove_filter( 'woocommerce_webhook_deliver_async', '__return_false' );
		parent::tear_down();
	}

	/**
	 * Fake HTTP layer.
	 *
	 * @param false|array $pre  Short-circuit.
	 * @param array       $args Request args.
	 * @param string      $url  URL.
	 * @return array|WP_Error
	 */
	public function fake_receiver( $pre, $args, $url ) {
		if ( false === strpos( $url, 'receiver.example' ) ) {
			return $pre;
		}
		$this->seen[] = array( 'url' => $url, 'args' => $args );
		if ( 'error' === $this->receiver ) {
			return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		}
		return array(
			'headers'  => array(),
			'body'     => '{"ok":true}',
			'response' => array( 'code' => (int) $this->receiver, 'message' => 'x' ),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Create an active order.created webhook.
	 *
	 * @return WC_Webhook
	 */
	private function make_webhook() {
		$w = new WC_Webhook();
		$w->set_name( 'ERP' );
		$w->set_status( 'active' );
		$w->set_topic( 'order.created' );
		$w->set_delivery_url( 'https://receiver.example/hook' );
		$w->set_secret( 's3cret' );
		$w->set_api_version( 3 );
		$w->set_user_id( 1 );
		$w->save();
		return $w;
	}

	/**
	 * A 503 is logged as "failed" with a retry scheduled; the retry succeeds when the receiver is back.
	 */
	public function test_failed_delivery_is_retried_until_success() {
		$w     = $this->make_webhook();
		$order = wc_create_order();

		$this->receiver = 503;
		$w->deliver( $order->get_id() );

		$rows = WCWG_Store::query( array( 'webhook_id' => $w->get_id() ) );
		$this->assertSame( 1, $rows['total'] );
		$this->assertSame( WCWG_Store::STATUS_FAILED, $rows['items'][0]->status );
		$this->assertSame( '503', $rows['items'][0]->response_code );
		$this->assertNotFalse( as_next_scheduled_action( WCWG_Retry::ACTION_RETRY, null, WCWG_Retry::GROUP ) );

		// Receiver recovers; run the scheduled retry by hand.
		$this->receiver = 200;
		WCWG_Retry::run_retry( $w->get_id(), $order->get_id(), 2, (int) $rows['items'][0]->id );

		$rows = WCWG_Store::query( array( 'webhook_id' => $w->get_id(), 'orderby' => 'id', 'order' => 'ASC' ) );
		$this->assertSame( 2, $rows['total'] );
		$this->assertSame( WCWG_Store::STATUS_SUCCESS, $rows['items'][1]->status );
		$this->assertSame( 2, (int) $rows['items'][1]->attempt );
		$this->assertSame( (int) $rows['items'][0]->id, (int) $rows['items'][1]->parent_id );
		$this->assertCount( 2, $this->seen );
		$this->assertSame( 'order.created', $this->seen[1]['args']['headers']['X-WC-Webhook-Topic'] );
	}

	/**
	 * A 404 is not retried: it is dead at once and an alert is fired.
	 */
	public function test_4xx_is_dead_without_retry() {
		$w     = $this->make_webhook();
		$order = wc_create_order();
		$alerts = array();
		add_action(
			'wcwg_alert_sent',
			function ( $event ) use ( &$alerts ) {
				$alerts[] = $event;
			}
		);

		$this->receiver = 404;
		$w->deliver( $order->get_id() );

		$rows = WCWG_Store::query( array( 'webhook_id' => $w->get_id() ) );
		$this->assertSame( WCWG_Store::STATUS_DEAD, $rows['items'][0]->status );
		$this->assertFalse( as_next_scheduled_action( WCWG_Retry::ACTION_RETRY, null, WCWG_Retry::GROUP ) );
		$this->assertContains( 'dead_delivery', $alerts );
	}

	/**
	 * Retries stop after max_retries and the last row is dead.
	 */
	public function test_retries_exhaust_to_dead() {
		$w     = $this->make_webhook();
		$order = wc_create_order();

		$this->receiver = 'error';
		$w->deliver( $order->get_id() );                                   // attempt 1 → failed
		$first = WCWG_Store::query( array( 'webhook_id' => $w->get_id() ) )['items'][0];
		WCWG_Retry::run_retry( $w->get_id(), $order->get_id(), 2, (int) $first->id ); // attempt 2 → failed
		WCWG_Retry::run_retry( $w->get_id(), $order->get_id(), 3, (int) $first->id ); // attempt 3 → dead

		$rows = WCWG_Store::query( array( 'webhook_id' => $w->get_id(), 'orderby' => 'id', 'order' => 'ASC' ) );
		$this->assertSame( 3, $rows['total'] );
		$this->assertSame( WCWG_Store::STATUS_FAILED, $rows['items'][0]->status );
		$this->assertSame( WCWG_Store::STATUS_FAILED, $rows['items'][1]->status );
		$this->assertSame( WCWG_Store::STATUS_DEAD, $rows['items'][2]->status );
		$this->assertSame( 'http_request_failed', $rows['items'][2]->response_code );
	}

	/**
	 * When WooCommerce disables the webhook, recovery schedules a re-enable; re-enabling
	 * resets the failure counter and queues replays of dead rows.
	 */
	public function test_disabled_webhook_is_recovered() {
		$w     = $this->make_webhook();
		$order = wc_create_order();
		update_option( WCWG_Settings::OPTION, WCWG_Settings::sanitize( array( 'max_retries' => 0, 'alert_throttle' => 0 ) ) );

		$this->receiver = 500;
		for ( $i = 0; $i < 7; $i++ ) {
			$w = wc_get_webhook( $w->get_id() );
			$w->deliver( $order->get_id() );
		}
		$w = wc_get_webhook( $w->get_id() );
		$this->assertSame( 'disabled', $w->get_status(), 'WooCommerce should have disabled the webhook' );
		$this->assertNotFalse( as_next_scheduled_action( WCWG_Recovery::ACTION_REENABLE, array( 'webhook_id' => $w->get_id() ), WCWG_Retry::GROUP ) );
		$this->assertGreaterThan( 0, count( WCWG_Store::dead_for_webhook( $w->get_id() ) ) );

		$this->receiver = 200;
		$queued = WCWG_Recovery::reenable( $w->get_id(), false );
		$w      = wc_get_webhook( $w->get_id() );
		$this->assertSame( 'active', $w->get_status() );
		$this->assertSame( 0, $w->get_failure_count() );
		$this->assertGreaterThan( 0, $queued );
		$this->assertNotFalse( as_next_scheduled_action( WCWG_Retry::ACTION_REPLAY, null, WCWG_Retry::GROUP ) );

		// Run one replay by hand and check it succeeds and marks the source row replayed.
		$dead = WCWG_Store::dead_for_webhook( $w->get_id() );
		$res  = WCWG_Retry::replay( (int) $dead[0]->id );
		$this->assertTrue( $res );
		$this->assertSame( WCWG_Store::STATUS_REPLAYED, WCWG_Store::get( (int) $dead[0]->id )->status );
		$latest = WCWG_Store::query( array( 'webhook_id' => $w->get_id(), 'per_page' => 1 ) )['items'][0];
		$this->assertSame( WCWG_Store::STATUS_SUCCESS, $latest->status );
		$this->assertSame( (int) $dead[0]->id, (int) $latest->parent_id );
	}
}
