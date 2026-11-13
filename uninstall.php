<?php
/**
 * Remove everything the plugin stored.
 *
 * @package WebhookGuard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wcwg_table = $wpdb->prefix . 'wcwg_deliveries';
$wpdb->query( "DROP TABLE IF EXISTS {$wcwg_table}" ); // phpcs:ignore WordPress.DB

delete_option( 'wcwg_settings' );
delete_option( 'wcwg_db_version' );
delete_option( 'wcwg_webhook_state' );

$wcwg_transients = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_wcwg_alert_%' OR option_name LIKE '_transient_timeout_wcwg_alert_%'" ); // phpcs:ignore WordPress.DB
foreach ( (array) $wcwg_transients as $wcwg_name ) {
	delete_option( $wcwg_name );
}

wp_clear_scheduled_hook( 'wcwg_purge_log' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wcwg_retry_delivery', array(), 'wc-webhook-guard' );
	as_unschedule_all_actions( 'wcwg_replay_delivery', array(), 'wc-webhook-guard' );
	as_unschedule_all_actions( 'wcwg_reenable_webhook', array(), 'wc-webhook-guard' );
}
