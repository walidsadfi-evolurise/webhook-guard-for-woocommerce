<?php
/**
 * WP-CLI commands.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inspect and replay WooCommerce webhook deliveries.
 *
 * ## EXAMPLES
 *
 *     wp wc webhook-guard list --status=dead
 *     wp wc webhook-guard replay 42
 *     wp wc webhook-guard replay --webhook=7 --status=dead
 *     wp wc webhook-guard reenable 7
 *     wp wc webhook-guard stats
 */
class WCWG_CLI {

	/**
	 * List recorded deliveries.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : success, failed, dead or replayed.
	 *
	 * [--webhook=<id>]
	 * : Only this webhook.
	 *
	 * [--limit=<n>]
	 * : Rows. Default 50.
	 *
	 * [--format=<format>]
	 * : table, json, csv, yaml, ids. Default table.
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Named.
	 */
	public function list( $args, $assoc_args ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames
		$res  = WCWG_Store::query(
			array(
				'status'     => isset( $assoc_args['status'] ) ? $assoc_args['status'] : '',
				'webhook_id' => isset( $assoc_args['webhook'] ) ? (int) $assoc_args['webhook'] : 0,
				'per_page'   => isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50,
			)
		);
		$rows = array();
		foreach ( $res['items'] as $r ) {
			$rows[] = array(
				'id'            => (int) $r->id,
				'webhook_id'    => (int) $r->webhook_id,
				'topic'         => $r->topic,
				'resource'      => $r->resource_arg,
				'attempt'       => (int) $r->attempt,
				'status'        => $r->status,
				'response_code' => $r->response_code,
				'next_retry_at' => $r->next_retry_at,
				'created_at'    => $r->created_at,
			);
		}
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		WP_CLI\Utils\format_items( $format, $rows, array( 'id', 'webhook_id', 'topic', 'resource', 'attempt', 'status', 'response_code', 'next_retry_at', 'created_at' ) );
	}

	/**
	 * Replay one delivery by row ID, or every delivery matching --webhook / --status.
	 *
	 * ## OPTIONS
	 *
	 * [<id>]
	 * : Row ID.
	 *
	 * [--webhook=<id>]
	 * : Replay rows of this webhook (combined with --status, default dead).
	 *
	 * [--status=<status>]
	 * : Default dead.
	 *
	 * [--background]
	 * : Queue through Action Scheduler instead of running now.
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Named.
	 */
	public function replay( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {
			$r = WCWG_Retry::replay( (int) $args[0] );
			if ( is_wp_error( $r ) ) {
				WP_CLI::error( $r->get_error_message() );
			}
			WP_CLI::success( sprintf( 'Delivery %d replayed.', (int) $args[0] ) );
			return;
		}
		if ( empty( $assoc_args['webhook'] ) ) {
			WP_CLI::error( 'Give a row ID or --webhook=<id>.' );
		}
		$res = WCWG_Store::query(
			array(
				'webhook_id' => (int) $assoc_args['webhook'],
				'status'     => isset( $assoc_args['status'] ) ? $assoc_args['status'] : WCWG_Store::STATUS_DEAD,
				'per_page'   => 1000,
			)
		);
		$ids = wp_list_pluck( $res['items'], 'id' );
		if ( ! $ids ) {
			WP_CLI::warning( 'Nothing to replay.' );
			return;
		}
		if ( isset( $assoc_args['background'] ) ) {
			$n = WCWG_Retry::queue_replays( $ids, 2 );
			WP_CLI::success( sprintf( '%d replays queued.', $n ) );
			return;
		}
		$ok = 0;
		foreach ( $ids as $id ) {
			$r = WCWG_Retry::replay( (int) $id );
			if ( is_wp_error( $r ) ) {
				WP_CLI::warning( sprintf( '#%d: %s', $id, $r->get_error_message() ) );
			} else {
				$ok++;
			}
		}
		WP_CLI::success( sprintf( '%d of %d replayed.', $ok, count( $ids ) ) );
	}

	/**
	 * Re-enable a webhook, reset its failure counter and replay its dead deliveries.
	 *
	 * ## OPTIONS
	 *
	 * <webhook_id>
	 * : Webhook ID.
	 *
	 * @param array $args Positional.
	 */
	public function reenable( $args ) {
		$r = WCWG_Recovery::reenable( (int) $args[0], true );
		if ( is_wp_error( $r ) ) {
			WP_CLI::error( $r->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Webhook %d is active again; %d dead deliveries queued for replay.', (int) $args[0], (int) $r ) );
	}

	/**
	 * Counts for the last 24 hours and the list of disabled webhooks.
	 *
	 * @param array $args       Positional.
	 * @param array $assoc_args Named.
	 */
	public function stats( $args, $assoc_args ) {
		$counts = WCWG_Store::counts( 24 );
		foreach ( $counts as $k => $v ) {
			WP_CLI::line( sprintf( '%-10s %d', $k, $v ) );
		}
		$disabled = wc_load_webhooks( 'disabled' );
		WP_CLI::line( sprintf( 'disabled webhooks: %d', count( $disabled ) ) );
		foreach ( $disabled as $w ) {
			WP_CLI::line( sprintf( '  #%d %s → %s', $w->get_id(), $w->get_name(), $w->get_delivery_url() ) );
		}
	}
}
