<?php
/**
 * Records every delivery and schedules retries.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hooks into woocommerce_webhook_delivery (fired after every attempt WooCommerce makes).
 */
class WCWG_Retry {

	const ACTION_RETRY  = 'wcwg_retry_delivery';
	const ACTION_REPLAY = 'wcwg_replay_delivery';
	const GROUP         = 'wc-webhook-guard';

	/**
	 * Context of the attempt currently running, set by the retry/replay handlers so the
	 * delivery hook knows it is not looking at a fresh event.
	 *
	 * @var array|null
	 */
	private static $context = null;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'woocommerce_webhook_delivery', array( __CLASS__, 'on_delivery' ), 10, 5 );
		add_action( self::ACTION_RETRY, array( __CLASS__, 'run_retry' ), 10, 4 );
		add_action( self::ACTION_REPLAY, array( __CLASS__, 'run_replay' ), 10, 1 );
		add_filter( 'woocommerce_max_webhook_delivery_failures', array( __CLASS__, 'max_failures' ) );
	}

	/**
	 * Optionally raise WooCommerce's "disable after N failures" threshold.
	 *
	 * @param int $max Default (5).
	 * @return int
	 */
	public static function max_failures( $max ) {
		$custom = (int) WCWG_Settings::get( 'max_failures' );
		return $custom > 0 ? $custom : $max;
	}

	/**
	 * Extract a code and message from the HTTP response.
	 *
	 * @param array|WP_Error $response Response.
	 * @return array{0:string,1:string,2:string} code, message, body
	 */
	public static function describe_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return array( (string) $response->get_error_code(), $response->get_error_message(), '' );
		}
		$code = (string) wp_remote_retrieve_response_code( $response );
		$msg  = wp_remote_retrieve_response_message( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		return array( $code, $msg, $body );
	}

	/**
	 * Delivery hook.
	 *
	 * @param array          $http_args  Request args.
	 * @param array|WP_Error $response   Response.
	 * @param float          $duration   Seconds.
	 * @param mixed          $arg        First hook argument (resource ID for standard topics).
	 * @param int            $webhook_id Webhook ID.
	 */
	public static function on_delivery( $http_args, $response, $duration, $arg, $webhook_id ) {
		$ctx           = self::$context;
		self::$context = null;

		$attempt   = $ctx ? (int) $ctx['attempt'] : 1;
		$parent_id = $ctx ? (int) $ctx['parent_id'] : 0;

		list( $code, $message, $body ) = self::describe_response( $response );
		$failed                        = WCWG_Backoff::is_failure( $code );
		$webhook                       = wc_get_webhook( (int) $webhook_id );
		$topic                         = $webhook ? $webhook->get_topic() : '';
		$store_body                    = (bool) WCWG_Settings::get( 'store_body' );

		$row = array(
			'webhook_id'    => (int) $webhook_id,
			'delivery_id'   => isset( $http_args['headers']['X-WC-Webhook-Delivery-ID'] ) ? (string) $http_args['headers']['X-WC-Webhook-Delivery-ID'] : '',
			'topic'         => $topic,
			'resource_arg'  => is_scalar( $arg ) ? (string) $arg : '',
			'attempt'       => $attempt,
			'parent_id'     => $parent_id,
			'status'        => $failed ? WCWG_Store::STATUS_DEAD : WCWG_Store::STATUS_SUCCESS,
			'response_code' => $code,
			'error_message' => $failed ? $message : null,
			'duration'      => (float) $duration,
			'request_body'  => $store_body && isset( $http_args['body'] ) ? (string) $http_args['body'] : null,
			'response_body' => $store_body ? mb_substr( $body, 0, 20000 ) : null,
		);

		if ( ! $failed ) {
			$id = WCWG_Store::insert( $row );
			/**
			 * Fires after a successful delivery was recorded.
			 *
			 * @param int   $id  Row ID.
			 * @param array $row Row.
			 */
			do_action( 'wcwg_delivery_succeeded', $id, $row );
			return;
		}

		$backoff   = WCWG_Settings::backoff();
		$retryable = WCWG_Backoff::is_retryable( $code ) || ( WCWG_Settings::get( 'retry_4xx' ) && is_numeric( $code ) );
		$can_retry = $retryable && is_scalar( $arg ) && $backoff->can_retry( $attempt );

		/**
		 * Let integrations veto or force a retry.
		 *
		 * @param bool   $can_retry  Decision.
		 * @param string $code       Response code.
		 * @param int    $attempt    Attempt that failed.
		 * @param int    $webhook_id Webhook ID.
		 * @param mixed  $arg        Hook argument.
		 */
		$can_retry = apply_filters( 'wcwg_should_retry', $can_retry, $code, $attempt, $webhook_id, $arg );

		if ( $can_retry ) {
			$delay                = $backoff->delay_for( $attempt );
			$row['status']        = WCWG_Store::STATUS_FAILED;
			$row['next_retry_at'] = gmdate( 'Y-m-d H:i:s', time() + $delay );
			$id                   = WCWG_Store::insert( $row );
			$root                 = $parent_id ? $parent_id : $id;

			as_schedule_single_action(
				time() + $delay,
				self::ACTION_RETRY,
				array(
					'webhook_id' => (int) $webhook_id,
					'arg'        => $arg,
					'attempt'    => $attempt + 1,
					'parent_id'  => $root,
				),
				self::GROUP
			);
			return;
		}

		$id = WCWG_Store::insert( $row );
		/**
		 * Fires when a delivery is given up (retries exhausted or not retryable).
		 *
		 * @param int   $id  Row ID.
		 * @param array $row Row.
		 */
		do_action( 'wcwg_delivery_dead', $id, $row );
		WCWG_Alerts::send(
			'dead_delivery',
			$webhook_id,
			array(
				'topic'         => $topic,
				'resource'      => is_scalar( $arg ) ? $arg : '',
				'attempts'      => $attempt,
				'response_code' => $code,
				'error'         => $message,
			)
		);
	}

	/**
	 * Deliver again with an attempt context.
	 *
	 * @param int   $webhook_id Webhook ID.
	 * @param mixed $arg        Hook argument.
	 * @param int   $attempt    Attempt number.
	 * @param int   $parent_id  Root row ID.
	 */
	public static function run_retry( $webhook_id, $arg, $attempt, $parent_id ) {
		$webhook = wc_get_webhook( (int) $webhook_id );
		if ( ! $webhook ) {
			return;
		}
		if ( 'active' !== $webhook->get_status() ) {
			// WooCommerce disabled it meanwhile: park the event as dead; recovery replays dead rows after a re-enable.
			WCWG_Store::insert(
				array(
					'webhook_id'    => (int) $webhook_id,
					'topic'         => $webhook->get_topic(),
					'resource_arg'  => is_scalar( $arg ) ? (string) $arg : '',
					'attempt'       => (int) $attempt,
					'parent_id'     => (int) $parent_id,
					'status'        => WCWG_Store::STATUS_DEAD,
					'response_code' => 'webhook_disabled',
					'error_message' => __( 'Webhook was disabled when the retry ran.', 'webhook-guard-for-woocommerce' ),
				)
			);
			return;
		}
		self::$context = array(
			'attempt'   => (int) $attempt,
			'parent_id' => (int) $parent_id,
		);
		$webhook->deliver( $arg );
		self::$context = null;
	}

	/**
	 * Manual replay of a recorded delivery: builds the payload again from the current resource.
	 *
	 * @param int $row_id Row to replay.
	 * @return true|WP_Error
	 */
	public static function replay( $row_id ) {
		$row = WCWG_Store::get( $row_id );
		if ( ! $row ) {
			return new WP_Error( 'wcwg_not_found', __( 'Delivery not found.', 'webhook-guard-for-woocommerce' ) );
		}
		if ( '' === $row->resource_arg ) {
			return new WP_Error( 'wcwg_no_arg', __( 'This delivery carried a non-scalar argument and cannot be rebuilt.', 'webhook-guard-for-woocommerce' ) );
		}
		$webhook = wc_get_webhook( (int) $row->webhook_id );
		if ( ! $webhook ) {
			return new WP_Error( 'wcwg_no_webhook', __( 'The webhook no longer exists.', 'webhook-guard-for-woocommerce' ) );
		}
		if ( 'active' !== $webhook->get_status() ) {
			return new WP_Error( 'wcwg_inactive', __( 'The webhook is not active. Re-enable it first.', 'webhook-guard-for-woocommerce' ) );
		}

		$arg = is_numeric( $row->resource_arg ) ? (int) $row->resource_arg : $row->resource_arg;
		WCWG_Store::update( $row_id, array( 'status' => WCWG_Store::STATUS_REPLAYED ) );

		self::$context = array(
			'attempt'   => 1,
			'parent_id' => (int) $row_id,
		);
		$webhook->deliver( $arg );
		self::$context = null;

		return true;
	}

	/**
	 * Background replay (used by bulk actions and recovery, spaced out so a burst does not knock the receiver over again).
	 *
	 * @param int $row_id Row ID.
	 */
	public static function run_replay( $row_id ) {
		self::replay( (int) $row_id );
	}

	/**
	 * Queue replays for several rows.
	 *
	 * @param int[] $row_ids  Rows.
	 * @param int   $spacing  Seconds between two replays.
	 * @return int Number queued.
	 */
	public static function queue_replays( array $row_ids, $spacing = 2 ) {
		$n = 0;
		foreach ( array_values( array_unique( array_map( 'intval', $row_ids ) ) ) as $i => $id ) {
			as_schedule_single_action( time() + $i * max( 0, (int) $spacing ), self::ACTION_REPLAY, array( 'row_id' => $id ), self::GROUP );
			$n++;
		}
		return $n;
	}
}
