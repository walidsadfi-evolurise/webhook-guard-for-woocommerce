<?php
/**
 * Remove everything the plugin stored.
 *
 * @package WebhookGuard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$evowg_table = $wpdb->prefix . 'evowg_deliveries';
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $evowg_table ) ); // phpcs:ignore WordPress.DB

delete_option( 'evowg_settings' );
delete_option( 'evowg_db_version' );
delete_option( 'evowg_webhook_state' );

$evowg_transients = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_evowg_alert_%' OR option_name LIKE '_transient_timeout_evowg_alert_%'" ); // phpcs:ignore WordPress.DB
foreach ( (array) $evowg_transients as $evowg_name ) {
	delete_option( $evowg_name );
}

wp_clear_scheduled_hook( 'evowg_purge_log' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'evowg_retry_delivery', array(), 'wc-webhook-guard' );
	as_unschedule_all_actions( 'evowg_replay_delivery', array(), 'wc-webhook-guard' );
	as_unschedule_all_actions( 'evowg_reenable_webhook', array(), 'wc-webhook-guard' );
}
