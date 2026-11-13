<?php
/**
 * Retry policy tests.
 *
 * @package WebhookGuard
 */

use PHPUnit\Framework\TestCase;

/**
 * WCWG_Backoff.
 */
class BackoffTest extends TestCase {

	/**
	 * 2xx, 301 and 302 are successes, like WooCommerce; everything else fails.
	 */
	public function test_failure_classification_matches_woocommerce() {
		foreach ( array( 200, 201, 204, 301, 302 ) as $ok ) {
			$this->assertFalse( WCWG_Backoff::is_failure( $ok ), "$ok should be a success" );
		}
		foreach ( array( 0, 100, 303, 304, 400, 404, 429, 500, 502, 503 ) as $ko ) {
			$this->assertTrue( WCWG_Backoff::is_failure( $ko ), "$ko should be a failure" );
		}
		$this->assertTrue( WCWG_Backoff::is_failure( 'http_request_failed' ) );
	}

	/**
	 * Network errors, 5xx and the three "try later" 4xx are retryable; other 4xx are not.
	 */
	public function test_retryable() {
		$this->assertTrue( WCWG_Backoff::is_retryable( 'http_request_failed' ) );
		$this->assertTrue( WCWG_Backoff::is_retryable( 503 ) );
		$this->assertTrue( WCWG_Backoff::is_retryable( 500 ) );
		$this->assertTrue( WCWG_Backoff::is_retryable( 429 ) );
		$this->assertTrue( WCWG_Backoff::is_retryable( 408 ) );
		$this->assertFalse( WCWG_Backoff::is_retryable( 400 ) );
		$this->assertFalse( WCWG_Backoff::is_retryable( 401 ) );
		$this->assertFalse( WCWG_Backoff::is_retryable( 404 ) );
		$this->assertFalse( WCWG_Backoff::is_retryable( 200 ) );
	}

	/**
	 * Default schedule: 1, 3, 9, 27, 81 minutes.
	 */
	public function test_default_schedule() {
		$b = new WCWG_Backoff();
		$this->assertSame( array( 60, 180, 540, 1620, 4860 ), $b->schedule() );
	}

	/**
	 * Delays never exceed the cap.
	 */
	public function test_cap() {
		$b = new WCWG_Backoff( 10, 60, 3, 600 );
		$this->assertSame( 600, $b->delay_for( 5 ) );
		$this->assertSame( 600, max( $b->schedule() ) );
	}

	/**
	 * can_retry counts the original delivery as attempt 1.
	 */
	public function test_can_retry_bounds() {
		$b = new WCWG_Backoff( 2 );
		$this->assertTrue( $b->can_retry( 1 ) );
		$this->assertTrue( $b->can_retry( 2 ) );
		$this->assertFalse( $b->can_retry( 3 ) );

		$none = new WCWG_Backoff( 0 );
		$this->assertFalse( $none->can_retry( 1 ) );
		$this->assertSame( array(), $none->schedule() );
	}

	/**
	 * Garbage in, sane policy out.
	 */
	public function test_constructor_clamps_inputs() {
		$b = new WCWG_Backoff( -5, 0, 0.1, 1 );
		$this->assertSame( 0, $b->get_max_retries() );
		$this->assertSame( 1, $b->delay_for( 1 ) );
		$this->assertSame( 1, $b->delay_for( 9 ) );
	}
}
