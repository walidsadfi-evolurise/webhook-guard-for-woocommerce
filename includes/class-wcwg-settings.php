<?php
/**
 * Settings access with defaults and sanitisation.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * One option row, typed accessors.
 */
class WCWG_Settings {

	const OPTION = 'wcwg_settings';

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'max_retries'       => 5,
			'base_delay'        => 60,
			'multiplier'        => 3,
			'max_delay'         => 21600,
			'retry_4xx'         => 0,
			'auto_reenable'     => 1,
			'reenable_delay'    => 300,
			'reenable_max'      => 12,
			'max_failures'      => 0, // 0 = keep WooCommerce's default (5).
			'alert_email'       => '',
			'alert_webhook_url' => '',
			'alert_throttle'    => 3600,
			'store_body'        => 0,
			'retention_days'    => 30,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * One setting.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitise a raw settings array (used by the Settings API callback).
	 *
	 * @param array $raw Raw input.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw = is_array( $raw ) ? $raw : array();
		$d   = self::defaults();

		$int = function ( $key, $min, $max ) use ( $raw, $d ) {
			$v = isset( $raw[ $key ] ) ? (int) $raw[ $key ] : $d[ $key ];
			return max( $min, min( $max, $v ) );
		};

		$out = array(
			'max_retries'       => $int( 'max_retries', 0, 20 ),
			'base_delay'        => $int( 'base_delay', 5, 86400 ),
			'multiplier'        => (float) max( 1, min( 10, isset( $raw['multiplier'] ) ? (float) $raw['multiplier'] : $d['multiplier'] ) ),
			'max_delay'         => $int( 'max_delay', 60, 604800 ),
			'retry_4xx'         => empty( $raw['retry_4xx'] ) ? 0 : 1,
			'auto_reenable'     => empty( $raw['auto_reenable'] ) ? 0 : 1,
			'reenable_delay'    => $int( 'reenable_delay', 60, 86400 ),
			'reenable_max'      => $int( 'reenable_max', 1, 100 ),
			'max_failures'      => $int( 'max_failures', 0, 1000 ),
			'alert_email'       => isset( $raw['alert_email'] ) ? implode( ', ', array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', $raw['alert_email'] ) ) ) ) ) : '',
			'alert_webhook_url' => isset( $raw['alert_webhook_url'] ) ? esc_url_raw( trim( $raw['alert_webhook_url'] ) ) : '',
			'alert_throttle'    => $int( 'alert_throttle', 0, 86400 ),
			'store_body'        => empty( $raw['store_body'] ) ? 0 : 1,
			'retention_days'    => $int( 'retention_days', 1, 365 ),
		);

		return $out;
	}

	/**
	 * Backoff policy built from settings.
	 *
	 * @return WCWG_Backoff
	 */
	public static function backoff() {
		$s = self::all();
		return new WCWG_Backoff( $s['max_retries'], $s['base_delay'], $s['multiplier'], $s['max_delay'] );
	}
}
