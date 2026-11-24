<?php
/**
 * Settings tab.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

$evowg_s        = EVOWG_Settings::all();
$evowg_schedule = EVOWG_Settings::backoff()->schedule();
$evowg_human    = array();
foreach ( $evowg_schedule as $evowg_i => $evowg_d ) {
	$evowg_human[] = '+' . human_time_diff( 0, $evowg_d );
}
?>
<form method="post" action="options.php" style="max-width:900px">
	<?php settings_fields( 'evowg_settings_group' ); ?>

	<h2><?php esc_html_e( 'Retries', 'evolurise-webhook-delivery-recovery' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'WooCommerce tries once and gives up. Webhook Delivery Recovery retries network errors, timeouts and 5xx/429 answers with exponential backoff.', 'evolurise-webhook-delivery-recovery' ); ?>
		<?php if ( $evowg_human ) : ?>
			<br><strong><?php esc_html_e( 'Current schedule:', 'evolurise-webhook-delivery-recovery' ); ?></strong> <?php echo esc_html( implode( ', ', $evowg_human ) ); ?>
		<?php endif; ?>
	</p>
	<table class="form-table">
		<tr><th><label for="evowg_max_retries"><?php esc_html_e( 'Max retries', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="number" min="0" max="20" id="evowg_max_retries" name="evowg_settings[max_retries]" value="<?php echo (int) $evowg_s['max_retries']; ?>"> <span class="description"><?php esc_html_e( '0 disables retries (log and alert only).', 'evolurise-webhook-delivery-recovery' ); ?></span></td></tr>
		<tr><th><label for="evowg_base_delay"><?php esc_html_e( 'First retry after (seconds)', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="number" min="5" max="86400" id="evowg_base_delay" name="evowg_settings[base_delay]" value="<?php echo (int) $evowg_s['base_delay']; ?>"></td></tr>
		<tr><th><label for="evowg_multiplier"><?php esc_html_e( 'Backoff multiplier', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="number" step="0.5" min="1" max="10" id="evowg_multiplier" name="evowg_settings[multiplier]" value="<?php echo esc_attr( $evowg_s['multiplier'] ); ?>"></td></tr>
		<tr><th><label for="evowg_max_delay"><?php esc_html_e( 'Longest wait between two attempts (seconds)', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="number" min="60" max="604800" id="evowg_max_delay" name="evowg_settings[max_delay]" value="<?php echo (int) $evowg_s['max_delay']; ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Also retry 4xx answers', 'evolurise-webhook-delivery-recovery' ); ?></th>
			<td><label><input type="checkbox" name="evowg_settings[retry_4xx]" value="1" <?php checked( $evowg_s['retry_4xx'] ); ?>> <?php esc_html_e( 'Off by default: a 400/401/404 means the receiver rejected the payload, sending it again rarely helps.', 'evolurise-webhook-delivery-recovery' ); ?></label></td></tr>
	</table>

	<h2><?php esc_html_e( 'Disabled webhooks', 'evolurise-webhook-delivery-recovery' ); ?></h2>
	<p class="description"><?php esc_html_e( 'WooCommerce disables a webhook after more than 5 consecutive failures and never turns it back on. Webhook Delivery Recovery re-enables it with a growing delay, resets the failure counter, then replays the deliveries recorded as dead while it was down.', 'evolurise-webhook-delivery-recovery' ); ?></p>
	<table class="form-table">
		<tr><th><?php esc_html_e( 'Automatic re-enable', 'evolurise-webhook-delivery-recovery' ); ?></th>
			<td><label><input type="checkbox" name="evowg_settings[auto_reenable]" value="1" <?php checked( $evowg_s['auto_reenable'] ); ?>> <?php esc_html_e( 'Re-enable disabled webhooks automatically', 'evolurise-webhook-delivery-recovery' ); ?></label></td></tr>
		<tr><th><label for="evowg_reenable_delay"><?php esc_html_e( 'First re-enable after (seconds)', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="number" min="60" max="86400" id="evowg_reenable_delay" name="evowg_settings[reenable_delay]" value="<?php echo (int) $evowg_s['reenable_delay']; ?>"> <span class="description"><?php esc_html_e( 'Doubles after every failed attempt, capped at 6 hours.', 'evolurise-webhook-delivery-recovery' ); ?></span></td></tr>
		<tr><th><label for="evowg_reenable_max"><?php esc_html_e( 'Give up after N re-enables', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="number" min="1" max="100" id="evowg_reenable_max" name="evowg_settings[reenable_max]" value="<?php echo (int) $evowg_s['reenable_max']; ?>"></td></tr>
		<tr><th><label for="evowg_max_failures"><?php esc_html_e( 'Failures before WooCommerce disables a webhook', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="number" min="0" max="1000" id="evowg_max_failures" name="evowg_settings[max_failures]" value="<?php echo (int) $evowg_s['max_failures']; ?>"> <span class="description"><?php esc_html_e( '0 keeps the WooCommerce default (5). Raise it if your receiver has short outages often.', 'evolurise-webhook-delivery-recovery' ); ?></span></td></tr>
	</table>

	<h2><?php esc_html_e( 'Alerts', 'evolurise-webhook-delivery-recovery' ); ?></h2>
	<table class="form-table">
		<tr><th><label for="evowg_alert_email"><?php esc_html_e( 'Email', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="text" class="regular-text" id="evowg_alert_email" name="evowg_settings[alert_email]" value="<?php echo esc_attr( $evowg_s['alert_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"> <span class="description"><?php esc_html_e( 'Comma-separated. Empty = site admin email.', 'evolurise-webhook-delivery-recovery' ); ?></span></td></tr>
		<tr><th><label for="evowg_alert_webhook_url"><?php esc_html_e( 'Slack / Discord / automation URL', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="url" class="regular-text" id="evowg_alert_webhook_url" name="evowg_settings[alert_webhook_url]" value="<?php echo esc_attr( $evowg_s['alert_webhook_url'] ); ?>" placeholder="https://hooks.slack.com/services/…"> <span class="description"><?php esc_html_e( 'A JSON POST with a "text" field plus the structured event (works with Slack, Discord, Mattermost, n8n, Make, Zapier).', 'evolurise-webhook-delivery-recovery' ); ?></span></td></tr>
		<tr><th><label for="evowg_alert_throttle"><?php esc_html_e( 'Same alert at most every (seconds)', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="number" min="0" max="86400" id="evowg_alert_throttle" name="evowg_settings[alert_throttle]" value="<?php echo (int) $evowg_s['alert_throttle']; ?>"></td></tr>
	</table>

	<h2><?php esc_html_e( 'Log', 'evolurise-webhook-delivery-recovery' ); ?></h2>
	<table class="form-table">
		<tr><th><?php esc_html_e( 'Store payloads', 'evolurise-webhook-delivery-recovery' ); ?></th>
			<td><label><input type="checkbox" name="evowg_settings[store_body]" value="1" <?php checked( $evowg_s['store_body'] ); ?>> <?php esc_html_e( 'Keep request and response bodies with each row. Off by default: order and customer payloads contain personal data.', 'evolurise-webhook-delivery-recovery' ); ?></label></td></tr>
		<tr><th><label for="evowg_retention_days"><?php esc_html_e( 'Keep rows for (days)', 'evolurise-webhook-delivery-recovery' ); ?></label></th>
			<td><input type="number" min="1" max="365" id="evowg_retention_days" name="evowg_settings[retention_days]" value="<?php echo (int) $evowg_s['retention_days']; ?>"></td></tr>
	</table>

	<?php submit_button(); ?>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'evowg_action' ); ?>
	<input type="hidden" name="action" value="evowg_action">
	<input type="hidden" name="do" value="purge">
	<button type="submit" class="button"><?php esc_html_e( 'Purge old rows now', 'evolurise-webhook-delivery-recovery' ); ?></button>
</form>
