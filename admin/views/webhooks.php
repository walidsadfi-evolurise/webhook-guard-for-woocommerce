<?php
/**
 * Webhooks tab: status per webhook, re-enable and replay-dead buttons.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

$wcwg_webhooks = wc_load_webhooks();
?>
<table class="widefat striped" style="margin-top:16px">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Webhook', 'webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Topic', 'webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Delivery URL', 'webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Status', 'webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Consecutive failures', 'webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Dead deliveries', 'webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Auto re-enable', 'webhook-guard-for-woocommerce' ); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
	<?php if ( ! $wcwg_webhooks ) : ?>
		<tr><td colspan="8"><?php esc_html_e( 'No webhooks defined. Create one under WooCommerce → Settings → Advanced → Webhooks.', 'webhook-guard-for-woocommerce' ); ?></td></tr>
	<?php endif; ?>
	<?php
	foreach ( $wcwg_webhooks as $wcwg_w ) :
		$wcwg_state = WCWG_Recovery::state( $wcwg_w->get_id() );
		$wcwg_dead  = count( WCWG_Store::dead_for_webhook( $wcwg_w->get_id(), 500 ) );
		$wcwg_next  = as_next_scheduled_action( WCWG_Recovery::ACTION_REENABLE, array( 'webhook_id' => $wcwg_w->get_id() ), WCWG_Retry::GROUP );
		?>
		<tr>
			<td><a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wcwg', 'webhook_id' => $wcwg_w->get_id() ), admin_url( 'admin.php' ) ) ); ?>"><?php echo esc_html( $wcwg_w->get_name() ); ?></a><br><small>#<?php echo (int) $wcwg_w->get_id(); ?> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=webhooks&edit-webhook=' . $wcwg_w->get_id() ) ); ?>"><?php esc_html_e( 'edit', 'webhook-guard-for-woocommerce' ); ?></a></small></td>
			<td><code><?php echo esc_html( $wcwg_w->get_topic() ); ?></code></td>
			<td><small><?php echo esc_html( $wcwg_w->get_delivery_url() ); ?></small></td>
			<td>
				<?php if ( 'active' === $wcwg_w->get_status() ) : ?>
					<span class="wcwg-status wcwg-status-success"><?php esc_html_e( 'Active', 'webhook-guard-for-woocommerce' ); ?></span>
				<?php elseif ( 'disabled' === $wcwg_w->get_status() ) : ?>
					<span class="wcwg-status wcwg-status-dead"><?php esc_html_e( 'Disabled', 'webhook-guard-for-woocommerce' ); ?></span>
					<?php if ( $wcwg_state['disabled_at'] ) : ?>
						<br><small><?php echo esc_html( sprintf( /* translators: %s: human time diff */ __( 'since %s ago', 'webhook-guard-for-woocommerce' ), human_time_diff( $wcwg_state['disabled_at'] ) ) ); ?></small>
					<?php endif; ?>
				<?php else : ?>
					<span class="wcwg-status wcwg-status-replayed"><?php echo esc_html( $wcwg_w->get_status() ); ?></span>
				<?php endif; ?>
			</td>
			<td><?php echo (int) $wcwg_w->get_failure_count(); ?></td>
			<td><?php echo (int) $wcwg_dead; ?></td>
			<td>
				<?php
				if ( is_numeric( $wcwg_next ) ) {
					echo esc_html( sprintf( /* translators: %s: human time diff */ __( 'next attempt in %s', 'webhook-guard-for-woocommerce' ), human_time_diff( time(), (int) $wcwg_next ) ) );
				} else {
					echo esc_html( sprintf( /* translators: %d: attempts */ __( '%d attempts so far', 'webhook-guard-for-woocommerce' ), (int) $wcwg_state['reenable_attempts'] ) );
				}
				?>
			</td>
			<td>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<?php wp_nonce_field( 'wcwg_action' ); ?>
					<input type="hidden" name="action" value="wcwg_action">
					<input type="hidden" name="webhook_id" value="<?php echo (int) $wcwg_w->get_id(); ?>">
					<?php if ( 'active' !== $wcwg_w->get_status() ) : ?>
						<button type="submit" name="do" value="reenable" class="button button-primary"><?php esc_html_e( 'Re-enable & replay dead', 'webhook-guard-for-woocommerce' ); ?></button>
					<?php elseif ( $wcwg_dead ) : ?>
						<button type="submit" name="do" value="replay_dead" class="button"><?php esc_html_e( 'Replay dead deliveries', 'webhook-guard-for-woocommerce' ); ?></button>
					<?php endif; ?>
				</form>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
