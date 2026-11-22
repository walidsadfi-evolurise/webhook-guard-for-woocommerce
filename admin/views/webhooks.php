<?php
/**
 * Webhooks tab: status per webhook, re-enable and replay-dead buttons.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

$evowg_webhooks = EVOWG_Recovery::webhooks();
?>
<table class="widefat striped" style="margin-top:16px">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Webhook', 'evolurise-webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Topic', 'evolurise-webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Delivery URL', 'evolurise-webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Status', 'evolurise-webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Consecutive failures', 'evolurise-webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Dead deliveries', 'evolurise-webhook-guard-for-woocommerce' ); ?></th>
			<th><?php esc_html_e( 'Auto re-enable', 'evolurise-webhook-guard-for-woocommerce' ); ?></th>
			<th></th>
		</tr>
	</thead>
	<tbody>
	<?php if ( ! $evowg_webhooks ) : ?>
		<tr><td colspan="8"><?php esc_html_e( 'No webhooks defined. Create one under WooCommerce → Settings → Advanced → Webhooks.', 'evolurise-webhook-guard-for-woocommerce' ); ?></td></tr>
	<?php endif; ?>
	<?php
	foreach ( $evowg_webhooks as $evowg_w ) :
		$evowg_state = EVOWG_Recovery::state( $evowg_w->get_id() );
		$evowg_dead  = count( EVOWG_Store::dead_for_webhook( $evowg_w->get_id(), 500 ) );
		$evowg_next  = as_next_scheduled_action( EVOWG_Recovery::ACTION_REENABLE, array( 'webhook_id' => $evowg_w->get_id() ), EVOWG_Retry::GROUP );
		$evowg_link  = add_query_arg(
			array(
				'page'       => 'evowg',
				'webhook_id' => $evowg_w->get_id(),
			),
			admin_url( 'admin.php' )
		);
		?>
		<tr>
			<td><a href="<?php echo esc_url( $evowg_link ); ?>"><?php echo esc_html( $evowg_w->get_name() ); ?></a><br><small>#<?php echo (int) $evowg_w->get_id(); ?> · <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=advanced&section=webhooks&edit-webhook=' . $evowg_w->get_id() ) ); ?>"><?php esc_html_e( 'edit', 'evolurise-webhook-guard-for-woocommerce' ); ?></a></small></td>
			<td><code><?php echo esc_html( $evowg_w->get_topic() ); ?></code></td>
			<td><small><?php echo esc_html( $evowg_w->get_delivery_url() ); ?></small></td>
			<td>
				<?php if ( 'active' === $evowg_w->get_status() ) : ?>
					<span class="evowg-status evowg-status-success"><?php esc_html_e( 'Active', 'evolurise-webhook-guard-for-woocommerce' ); ?></span>
				<?php elseif ( 'disabled' === $evowg_w->get_status() ) : ?>
					<span class="evowg-status evowg-status-dead"><?php esc_html_e( 'Disabled', 'evolurise-webhook-guard-for-woocommerce' ); ?></span>
					<?php if ( $evowg_state['disabled_at'] ) : ?>
						<br><small><?php echo esc_html( sprintf( /* translators: %s: human time diff */ __( 'since %s ago', 'evolurise-webhook-guard-for-woocommerce' ), human_time_diff( $evowg_state['disabled_at'] ) ) ); ?></small>
					<?php endif; ?>
				<?php else : ?>
					<span class="evowg-status evowg-status-replayed"><?php echo esc_html( $evowg_w->get_status() ); ?></span>
				<?php endif; ?>
			</td>
			<td><?php echo (int) $evowg_w->get_failure_count(); ?></td>
			<td><?php echo (int) $evowg_dead; ?></td>
			<td>
				<?php
				if ( is_numeric( $evowg_next ) ) {
					echo esc_html( sprintf( /* translators: %s: human time diff */ __( 'next attempt in %s', 'evolurise-webhook-guard-for-woocommerce' ), human_time_diff( time(), (int) $evowg_next ) ) );
				} else {
					echo esc_html( sprintf( /* translators: %d: attempts */ __( '%d attempts so far', 'evolurise-webhook-guard-for-woocommerce' ), (int) $evowg_state['reenable_attempts'] ) );
				}
				?>
			</td>
			<td>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
					<?php wp_nonce_field( 'evowg_action' ); ?>
					<input type="hidden" name="action" value="evowg_action">
					<input type="hidden" name="webhook_id" value="<?php echo (int) $evowg_w->get_id(); ?>">
					<?php if ( 'active' !== $evowg_w->get_status() ) : ?>
						<button type="submit" name="do" value="reenable" class="button button-primary"><?php esc_html_e( 'Re-enable & replay dead', 'evolurise-webhook-guard-for-woocommerce' ); ?></button>
					<?php elseif ( $evowg_dead ) : ?>
						<button type="submit" name="do" value="replay_dead" class="button"><?php esc_html_e( 'Replay dead deliveries', 'evolurise-webhook-guard-for-woocommerce' ); ?></button>
					<?php endif; ?>
				</form>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
