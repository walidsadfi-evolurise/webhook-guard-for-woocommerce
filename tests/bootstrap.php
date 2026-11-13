<?php
/**
 * Test bootstrap: the pure classes only need ABSPATH defined.
 *
 * @package WebhookGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
	define( 'HOUR_IN_SECONDS', 3600 );
	define( 'DAY_IN_SECONDS', 86400 );
}

require_once __DIR__ . '/../includes/class-wcwg-backoff.php';
require_once __DIR__ . '/../includes/class-wcwg-settings.php';

// Minimal stand-ins for the two WordPress functions WCWG_Settings uses.
if ( ! function_exists( 'get_option' ) ) {
	/**
	 * In-memory options.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return isset( $GLOBALS['wcwg_test_options'][ $key ] ) ? $GLOBALS['wcwg_test_options'][ $key ] : $default;
	}
}
if ( ! function_exists( 'sanitize_email' ) ) {
	/**
	 * Good-enough email filter for tests.
	 *
	 * @param string $email Email.
	 * @return string
	 */
	function sanitize_email( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : '';
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Keep http(s) URLs only.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return preg_match( '#^https?://#i', $url ) ? $url : '';
	}
}
