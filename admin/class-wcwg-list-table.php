<?php
/**
 * Deliveries list table.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * List of delivery attempts.
 */
class WCWG_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'delivery',
				'plural'   => 'deliveries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'            => '<input type="checkbox" />',
			'id'            => __( 'ID', 'webhook-guard-for-woocommerce' ),
			'webhook_id'    => __( 'Webhook', 'webhook-guard-for-woocommerce' ),
			'topic'         => __( 'Topic', 'webhook-guard-for-woocommerce' ),
			'resource_arg'  => __( 'Resource', 'webhook-guard-for-woocommerce' ),
			'attempt'       => __( 'Attempt', 'webhook-guard-for-woocommerce' ),
			'status'        => __( 'Status', 'webhook-guard-for-woocommerce' ),
			'response_code' => __( 'Response', 'webhook-guard-for-woocommerce' ),
			'duration'      => __( 'Duration', 'webhook-guard-for-woocommerce' ),
			'next_retry_at' => __( 'Next retry', 'webhook-guard-for-woocommerce' ),
			'created_at'    => __( 'When', 'webhook-guard-for-woocommerce' ),
		);
	}

	/**
	 * Sortable.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'id'            => array( 'id', true ),
			'webhook_id'    => array( 'webhook_id', false ),
			'status'        => array( 'status', false ),
			'attempt'       => array( 'attempt', false ),
			'response_code' => array( 'response_code', false ),
			'created_at'    => array( 'created_at', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array( 'bulk_replay' => __( 'Replay', 'webhook-guard-for-woocommerce' ) );
	}

	/**
	 * Status filter views.
	 *
	 * @return array
	 */
	protected function get_views() {
		$current = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$labels  = array(
			''                          => __( 'All', 'webhook-guard-for-woocommerce' ),
			WCWG_Store::STATUS_DEAD     => __( 'Dead', 'webhook-guard-for-woocommerce' ),
			WCWG_Store::STATUS_FAILED   => __( 'Retrying', 'webhook-guard-for-woocommerce' ),
			WCWG_Store::STATUS_SUCCESS  => __( 'Succeeded', 'webhook-guard-for-woocommerce' ),
			WCWG_Store::STATUS_REPLAYED => __( 'Replayed', 'webhook-guard-for-woocommerce' ),
		);
		$views   = array();
		foreach ( $labels as $k => $label ) {
			$url     = add_query_arg(
				array(
					'page'   => 'wcwg',
					'status' => $k ? $k : false,
				),
				admin_url( 'admin.php' )
			);
			$views[] = sprintf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), $current === $k ? 'current' : '', esc_html( $label ) );
		}
		return $views;
	}

	/**
	 * Load items.
	 */
	public function prepare_items() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$per_page = 25;
		$res      = WCWG_Store::query(
			array(
				'status'     => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
				'webhook_id' => isset( $_GET['webhook_id'] ) ? (int) $_GET['webhook_id'] : 0,
				'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
				'per_page'   => $per_page,
				'page'       => $this->get_pagenum(),
				'orderby'    => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id',
				'order'      => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc',
			)
		);
		// phpcs:enable
		$this->items = $res['items'];
		$this->set_pagination_args(
			array(
				'total_items' => $res['total'],
				'per_page'    => $per_page,
			)
		);
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
	}

	/**
	 * Checkbox.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="row_ids[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Default cell.
	 *
	 * @param object $item   Row.
	 * @param string $column Column.
	 * @return string
	 */
	public function column_default( $item, $column ) {
		switch ( $column ) {
			case 'webhook_id':
				$w    = wc_get_webhook( (int) $item->webhook_id );
				$name = $w ? $w->get_name() : sprintf( '#%d', (int) $item->webhook_id );
				return sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						add_query_arg(
							array(
								'page'       => 'wcwg',
								'webhook_id' => (int) $item->webhook_id,
							),
							admin_url( 'admin.php' )
						)
					),
					esc_html( $name )
				);
			case 'status':
				$labels = array(
					WCWG_Store::STATUS_SUCCESS  => __( 'Succeeded', 'webhook-guard-for-woocommerce' ),
					WCWG_Store::STATUS_FAILED   => __( 'Retrying', 'webhook-guard-for-woocommerce' ),
					WCWG_Store::STATUS_DEAD     => __( 'Dead', 'webhook-guard-for-woocommerce' ),
					WCWG_Store::STATUS_REPLAYED => __( 'Replayed', 'webhook-guard-for-woocommerce' ),
				);
				$label  = isset( $labels[ $item->status ] ) ? $labels[ $item->status ] : $item->status;
				return sprintf( '<span class="wcwg-status wcwg-status-%s">%s</span>', esc_attr( $item->status ), esc_html( $label ) );
			case 'response_code':
				$out = '<code>' . esc_html( $item->response_code ) . '</code>';
				if ( $item->error_message ) {
					$out .= '<br><small>' . esc_html( mb_substr( $item->error_message, 0, 120 ) ) . '</small>';
				}
				return $out;
			case 'duration':
				return esc_html( number_format_i18n( (float) $item->duration, 2 ) . ' s' );
			case 'attempt':
				return (int) $item->attempt . ( $item->parent_id ? ' <small>' . sprintf( /* translators: %d: row id */ esc_html__( '(of #%d)', 'webhook-guard-for-woocommerce' ), (int) $item->parent_id ) . '</small>' : '' );
			case 'next_retry_at':
			case 'created_at':
				return $item->$column ? esc_html( get_date_from_gmt( $item->$column, 'Y-m-d H:i:s' ) ) : '—';
			default:
				return esc_html( (string) $item->$column );
		}
	}

	/**
	 * ID column with row actions.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_id( $item ) {
		$actions = array();
		if ( '' !== $item->resource_arg ) {
			$actions['replay'] = sprintf(
				'<form method="post" action="%s" style="display:inline">%s<input type="hidden" name="action" value="wcwg_action"><input type="hidden" name="do" value="replay"><input type="hidden" name="row_id" value="%d"><button type="submit" class="button-link">%s</button></form>',
				esc_url( admin_url( 'admin-post.php' ) ),
				wp_nonce_field( 'wcwg_action', '_wpnonce', true, false ),
				(int) $item->id,
				esc_html__( 'Replay', 'webhook-guard-for-woocommerce' )
			);
		}
		if ( $item->request_body ) {
			$actions['body'] = sprintf( '<a href="#" class="wcwg-toggle-body" data-id="%d">%s</a>', (int) $item->id, esc_html__( 'Payload', 'webhook-guard-for-woocommerce' ) );
		}
		$out = '<strong>#' . (int) $item->id . '</strong>' . $this->row_actions( $actions );
		if ( $item->request_body ) {
			$out .= '<pre class="wcwg-body" id="wcwg-body-' . (int) $item->id . '" style="display:none;max-height:240px;overflow:auto;background:#f6f7f7;padding:8px">' . esc_html( $item->request_body ) . "\n\n--- response ---\n" . esc_html( (string) $item->response_body ) . '</pre>';
		}
		return $out;
	}

	/**
	 * Empty state.
	 */
	public function no_items() {
		esc_html_e( 'No deliveries recorded yet. The log fills up as WooCommerce sends webhooks.', 'webhook-guard-for-woocommerce' );
	}
}
