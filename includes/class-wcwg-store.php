<?php
/**
 * Delivery log storage (custom table).
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * One row per delivery attempt, plus webhook-level events (disabled, re-enabled).
 */
class WCWG_Store {

	const STATUS_SUCCESS  = 'success';
	const STATUS_FAILED   = 'failed';   // Failed, a retry is scheduled.
	const STATUS_DEAD     = 'dead';     // Failed, retries exhausted or not retryable.
	const STATUS_REPLAYED = 'replayed'; // Manually replayed; the replay has its own row.

	/**
	 * Table name with prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'wcwg_deliveries';
	}

	/**
	 * Create or upgrade the table.
	 */
	public static function install() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			webhook_id BIGINT UNSIGNED NOT NULL,
			delivery_id VARCHAR(64) NOT NULL DEFAULT '',
			topic VARCHAR(100) NOT NULL DEFAULT '',
			resource_arg VARCHAR(191) NOT NULL DEFAULT '',
			attempt SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			parent_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status VARCHAR(20) NOT NULL DEFAULT 'success',
			response_code VARCHAR(40) NOT NULL DEFAULT '',
			error_message TEXT NULL,
			duration DECIMAL(10,5) NOT NULL DEFAULT 0,
			request_body LONGTEXT NULL,
			response_body LONGTEXT NULL,
			next_retry_at DATETIME NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY webhook_status (webhook_id, status),
			KEY status_created (status, created_at),
			KEY created_at (created_at)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'wcwg_db_version', WCWG_VERSION, false );
	}

	/**
	 * Insert a delivery row.
	 *
	 * @param array $data Column => value.
	 * @return int Row ID.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		$data = array_merge(
			array(
				'created_at' => current_time( 'mysql', true ),
			),
			$data
		);
		$wpdb->insert( self::table(), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->insert_id;
	}

	/**
	 * Update columns of a row.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Column => value.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		return false !== $wpdb->update( self::table(), $data, array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Fetch one row.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Query rows for the list table.
	 *
	 * @param array $args status, webhook_id, search, per_page, page, orderby, order.
	 * @return array{items: object[], total: int}
	 */
	public static function query( array $args ) {
		global $wpdb;
		$table = self::table();
		$args  = array_merge(
			array(
				'status'     => '',
				'webhook_id' => 0,
				'search'     => '',
				'per_page'   => 20,
				'page'       => 1,
				'orderby'    => 'id',
				'order'      => 'DESC',
			),
			$args
		);

		$where  = array( '1=1' );
		$params = array();
		if ( '' !== $args['status'] ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['webhook_id'] ) {
			$where[]  = 'webhook_id = %d';
			$params[] = (int) $args['webhook_id'];
		}
		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(topic LIKE %s OR resource_arg LIKE %s OR response_code LIKE %s OR error_message LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		$orderby = in_array( $args['orderby'], array( 'id', 'webhook_id', 'status', 'created_at', 'attempt', 'response_code' ), true ) ? $args['orderby'] : 'id';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit   = max( 1, (int) $args['per_page'] );
		$offset  = max( 0, ( (int) $args['page'] - 1 ) * $limit );

		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$total = $params
			? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params ) )
			: (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );

		$sql   = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$items = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $params, array( $limit, $offset ) ) ) );
		// phpcs:enable

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	/**
	 * Counts per status, for the dashboard and Site Health.
	 *
	 * @param int $since_hours Window.
	 * @return array status => count
	 */
	public static function counts( $since_hours = 24 ) {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - $since_hours * HOUR_IN_SECONDS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM {$table} WHERE created_at >= %s GROUP BY status", $since ) ); // phpcs:ignore WordPress.DB
		$out   = array(
			self::STATUS_SUCCESS  => 0,
			self::STATUS_FAILED   => 0,
			self::STATUS_DEAD     => 0,
			self::STATUS_REPLAYED => 0,
		);
		foreach ( (array) $rows as $r ) {
			$out[ $r->status ] = (int) $r->n;
		}
		return $out;
	}

	/**
	 * Dead deliveries for a webhook (used by "replay all dead" after a re-enable).
	 *
	 * @param int $webhook_id Webhook ID.
	 * @param int $limit      Max rows.
	 * @return object[]
	 */
	public static function dead_for_webhook( $webhook_id, $limit = 200 ) {
		global $wpdb;
		$table = self::table();
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE webhook_id = %d AND status = %s ORDER BY id ASC LIMIT %d", (int) $webhook_id, self::STATUS_DEAD, (int) $limit ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Delete rows older than N days.
	 *
	 * @param int $days Retention.
	 * @return int Rows deleted.
	 */
	public static function purge( $days ) {
		global $wpdb;
		$table  = self::table();
		$before = gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $before ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Drop the table (uninstall).
	 */
	public static function drop() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB
	}
}
