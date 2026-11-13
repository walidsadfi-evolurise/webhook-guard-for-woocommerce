<?php
/**
 * Pure retry policy: no WordPress dependency, unit-tested on its own.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a delivery is a failure and when the next attempt should run.
 */
class WCWG_Backoff {

	/**
	 * Maximum number of retries after the original delivery.
	 *
	 * @var int
	 */
	private $max_retries;

	/**
	 * Delay before the first retry, in seconds.
	 *
	 * @var int
	 */
	private $base_delay;

	/**
	 * Multiplier applied to the delay on every retry.
	 *
	 * @var float
	 */
	private $multiplier;

	/**
	 * Upper bound for a single delay, in seconds.
	 *
	 * @var int
	 */
	private $max_delay;

	/**
	 * Constructor.
	 *
	 * @param int   $max_retries Retries after the original delivery (0 disables retries).
	 * @param int   $base_delay  Seconds before the first retry.
	 * @param float $multiplier  Backoff multiplier.
	 * @param int   $max_delay   Cap for a single delay in seconds.
	 */
	public function __construct( $max_retries = 5, $base_delay = 60, $multiplier = 3.0, $max_delay = 21600 ) {
		$this->max_retries = max( 0, (int) $max_retries );
		$this->base_delay  = max( 1, (int) $base_delay );
		$this->multiplier  = max( 1.0, (float) $multiplier );
		$this->max_delay   = max( $this->base_delay, (int) $max_delay );
	}

	/**
	 * Classify an HTTP response code the way WooCommerce does: 2xx, 301 and 302 are successes.
	 *
	 * A WP_Error (network failure) is passed as a string code and is always a failure.
	 *
	 * @param int|string $response_code HTTP status code or WP_Error code.
	 * @return bool True when the delivery failed.
	 */
	public static function is_failure( $response_code ) {
		if ( ! is_numeric( $response_code ) ) {
			return true;
		}
		$code = (int) $response_code;
		return $code < 200 || $code >= 303;
	}

	/**
	 * Failures that a retry can reasonably fix. 4xx (except 408, 425, 429) are the receiver
	 * rejecting the payload itself; retrying the same payload will not help.
	 *
	 * @param int|string $response_code HTTP status code or WP_Error code.
	 * @return bool
	 */
	public static function is_retryable( $response_code ) {
		if ( ! self::is_failure( $response_code ) ) {
			return false;
		}
		if ( ! is_numeric( $response_code ) ) {
			return true; // Timeout, DNS, connection refused.
		}
		$code = (int) $response_code;
		if ( in_array( $code, array( 408, 425, 429 ), true ) ) {
			return true;
		}
		return $code < 400 || $code >= 500;
	}

	/**
	 * Whether another attempt is allowed.
	 *
	 * @param int $attempt Attempt number that just failed (1 = original delivery).
	 * @return bool
	 */
	public function can_retry( $attempt ) {
		return (int) $attempt <= $this->max_retries;
	}

	/**
	 * Delay in seconds before the next attempt.
	 *
	 * @param int $attempt Attempt number that just failed (1 = original delivery).
	 * @return int
	 */
	public function delay_for( $attempt ) {
		$attempt = max( 1, (int) $attempt );
		$delay   = $this->base_delay * pow( $this->multiplier, $attempt - 1 );
		return (int) min( $this->max_delay, round( $delay ) );
	}

	/**
	 * Full schedule, useful for the settings screen ("retries at +1 min, +3 min, +9 min…").
	 *
	 * @return int[] Delays in seconds, one per retry.
	 */
	public function schedule() {
		$out = array();
		for ( $i = 1; $i <= $this->max_retries; $i++ ) {
			$out[] = $this->delay_for( $i );
		}
		return $out;
	}

	/**
	 * Getter.
	 *
	 * @return int
	 */
	public function get_max_retries() {
		return $this->max_retries;
	}
}
