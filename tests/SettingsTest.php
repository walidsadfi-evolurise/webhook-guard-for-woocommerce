<?php
/**
 * Settings sanitisation tests.
 *
 * @package WebhookGuard
 */

use PHPUnit\Framework\TestCase;

/**
 * WCWG_Settings.
 */
class SettingsTest extends TestCase {

	/**
	 * Reset the fake option store.
	 */
	protected function setUp(): void {
		$GLOBALS['wcwg_test_options'] = array();
	}

	/**
	 * Unknown option => defaults.
	 */
	public function test_defaults_when_nothing_saved() {
		$this->assertSame( 5, WCWG_Settings::get( 'max_retries' ) );
		$this->assertSame( 1, WCWG_Settings::get( 'auto_reenable' ) );
		$this->assertSame( 0, WCWG_Settings::get( 'store_body' ) );
	}

	/**
	 * Partial saved arrays are merged with defaults (upgrade safety).
	 */
	public function test_partial_option_merges_with_defaults() {
		$GLOBALS['wcwg_test_options'][ WCWG_Settings::OPTION ] = array( 'max_retries' => 2 );
		$this->assertSame( 2, WCWG_Settings::get( 'max_retries' ) );
		$this->assertSame( 60, WCWG_Settings::get( 'base_delay' ) );
	}

	/**
	 * Sanitise clamps numbers, normalises checkboxes, filters emails and URLs.
	 */
	public function test_sanitize() {
		$out = WCWG_Settings::sanitize(
			array(
				'max_retries'       => '99',
				'base_delay'        => '1',
				'multiplier'        => '0',
				'max_delay'         => 'abc',
				'retry_4xx'         => 'on',
				'auto_reenable'     => '',
				'alert_email'       => 'ops@example.com, not-an-email, dev@example.org',
				'alert_webhook_url' => 'javascript:alert(1)',
				'retention_days'    => '0',
			)
		);
		$this->assertSame( 20, $out['max_retries'] );
		$this->assertSame( 5, $out['base_delay'] );
		$this->assertSame( 1.0, $out['multiplier'] );
		$this->assertSame( 60, $out['max_delay'] );
		$this->assertSame( 1, $out['retry_4xx'] );
		$this->assertSame( 0, $out['auto_reenable'] );
		$this->assertSame( 'ops@example.com, dev@example.org', $out['alert_email'] );
		$this->assertSame( '', $out['alert_webhook_url'] );
		$this->assertSame( 1, $out['retention_days'] );
	}

	/**
	 * Non-array input does not fatal.
	 */
	public function test_sanitize_garbage() {
		$out = WCWG_Settings::sanitize( 'nope' );
		$this->assertSame( WCWG_Settings::defaults()['max_retries'], $out['max_retries'] );
	}

	/**
	 * The backoff built from settings reflects them.
	 */
	public function test_backoff_from_settings() {
		$GLOBALS['wcwg_test_options'][ WCWG_Settings::OPTION ] = array(
			'max_retries' => 3,
			'base_delay'  => 10,
			'multiplier'  => 2,
			'max_delay'   => 1000,
		);
		$this->assertSame( array( 10, 20, 40 ), WCWG_Settings::backoff()->schedule() );
	}
}
