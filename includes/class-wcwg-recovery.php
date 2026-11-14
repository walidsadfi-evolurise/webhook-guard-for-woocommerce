<?php
/**
 * Re-enables webhooks that WooCommerce disabled, then replays what was lost meanwhile.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Disabled-webhook recovery.
 */
class WCWG_Recovery {

	const ACTION_REENABLE = 'wcwg_reenable_webhook';
	const STATE_OPTION    = 'wcwg_webhook_state';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_webhook_disabled_due_delivery_failures', array( __CLASS__, 'on_disabled' ) );
		add_action( self::ACTION_REENABLE, array( __CLASS__, 'run_reenable' ), 10, 1 );
		add_action( 'wcwg_delivery_succeeded', array( __CLASS__, 'on_success' ), 10, 2 );
	}

	/**
	 * A successful delivery proves the receiver is back: forget the re-enable counter.
	 *
	 * @param int   $row_id Row ID.
	 * @param array $row    Row.
	 */
	public static function on_success( $row_id, array $row ) {
		$state = self::state( (int) $row['webhook_id'] );
		if ( $state['disabled_at'] || $state['reenable_attempts'] ) {
			self::clear_state( (int) $row['webhook_id'] );
		}
	}

	/**
	 * Per-webhook recovery state.
	 *
	 * @param int $webhook_id Webhook ID.
	 * @return array
	 */
	public static function state( $webhook_id ) {
		$all = get_option( self::STATE_OPTION, array() );
		$all = is_array( $all ) ? $all : array();
		return isset( $all[ $webhook_id ] ) ? $all[ $webhook_id ] : array(
			'disabled_at'       => 0,
			'reenable_attempts' => 0,
			'last_reenabled_at' => 0,
		);
	}

	/**
	 * Save state.
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param array $state      State.
	 */
	public static function set_state( $webhook_id, array $state ) {
		$all                = get_option( self::STATE_OPTION, array() );
		$all                = is_array( $all ) ? $all : array();
		$all[ $webhook_id ] = $state;
		update_option( self::STATE_OPTION, $all, false );
	}

	/**
	 * Reset state (manual re-enable from the UI, or a success).
	 *
	 * @param int $webhook_id Webhook ID.
	 */
	public static function clear_state( $webhook_id ) {
		$all = get_option( self::STATE_OPTION, array() );
		if ( is_array( $all ) && isset( $all[ $webhook_id ] ) ) {
			unset( $all[ $webhook_id ] );
			update_option( self::STATE_OPTION, $all, false );
		}
	}

	/**
	 * WooCommerce just disabled a webhook.
	 *
	 * @param int $webhook_id Webhook ID.
	 */
	public static function on_disabled( $webhook_id ) {
		$webhook_id = (int) $webhook_id;
		$state      = self::state( $webhook_id );

		$state['disabled_at'] = time();
		self::set_state( $webhook_id, $state );

		/**
		 * Fires when WooCommerce disabled a webhook and Webhook Guard recorded it.
		 *
		 * @param int   $webhook_id Webhook ID.
		 * @param array $state      Recovery state.
		 */
		do_action( 'wcwg_webhook_disabled', $webhook_id, $state );

		WCWG_Alerts::send( 'webhook_disabled', $webhook_id, array( 'reenable_attempts_so_far' => (int) $state['reenable_attempts'] ) );

		if ( ! WCWG_Settings::get( 'auto_reenable' ) ) {
			return;
		}

		$max = (int) WCWG_Settings::get( 'reenable_max' );
		if ( $state['reenable_attempts'] >= $max ) {
			WCWG_Alerts::send( 'reenable_gave_up', $webhook_id, array( 'attempts' => (int) $state['reenable_attempts'] ) );
			return;
		}

		$delay = self::reenable_delay( (int) $state['reenable_attempts'] );
		if ( false === as_next_scheduled_action( self::ACTION_REENABLE, array( 'webhook_id' => $webhook_id ), WCWG_Retry::GROUP ) ) {
			as_schedule_single_action( time() + $delay, self::ACTION_REENABLE, array( 'webhook_id' => $webhook_id ), WCWG_Retry::GROUP );
		}
	}

	/**
	 * Delay before the next re-enable: base × 2^attempts, capped at 6 hours.
	 *
	 * @param int $attempts Re-enable attempts so far.
	 * @return int Seconds.
	 */
	public static function reenable_delay( $attempts ) {
		$base = (int) WCWG_Settings::get( 'reenable_delay' );
		return (int) min( 6 * HOUR_IN_SECONDS, $base * pow( 2, max( 0, $attempts ) ) );
	}

	/**
	 * Scheduled re-enable.
	 *
	 * @param int $webhook_id Webhook ID.
	 */
	public static function run_reenable( $webhook_id ) {
		self::reenable( (int) $webhook_id, false );
	}

	/**
	 * Re-enable a webhook, reset WooCommerce's failure counter and replay dead deliveries.
	 *
	 * @param int  $webhook_id Webhook ID.
	 * @param bool $manual     True when a human clicked the button (resets the attempt counter).
	 * @return int|WP_Error Number of replays queued.
	 */
	public static function reenable( $webhook_id, $manual = false ) {
		$webhook = wc_get_webhook( (int) $webhook_id );
		if ( ! $webhook ) {
			return new WP_Error( 'wcwg_no_webhook', __( 'Webhook not found.', 'webhook-guard-for-woocommerce' ) );
		}

		$state = self::state( $webhook_id );
		if ( $manual ) {
			$state['reenable_attempts'] = 0;
		} else {
			++$state['reenable_attempts'];
		}
		$state['last_reenabled_at'] = time();
		self::set_state( $webhook_id, $state );

		if ( 'active' !== $webhook->get_status() ) {
			$webhook->set_status( 'active' );
		}
		$webhook->set_failure_count( 0 );
		$webhook->save();

		/**
		 * Fires after Webhook Guard re-enabled a webhook.
		 *
		 * @param int  $webhook_id Webhook ID.
		 * @param bool $manual     Manual or automatic.
		 */
		do_action( 'wcwg_webhook_reenabled', $webhook_id, $manual );
		WCWG_Alerts::send( 'webhook_reenabled', $webhook_id, array( 'manual' => $manual ? 'yes' : 'no' ) );

		$dead = WCWG_Store::dead_for_webhook( $webhook_id, 200 );
		$ids  = array();
		foreach ( $dead as $row ) {
			if ( '' !== $row->resource_arg ) {
				$ids[] = (int) $row->id;
			}
		}
		// Oldest first, 3 seconds apart: a receiver that just came back is not flooded.
		return WCWG_Retry::queue_replays( $ids, 3 );
	}
}
