<?php
/**
 * Settings tab.
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

$wcwg_s        = WCWG_Settings::all();
$wcwg_schedule = WCWG_Settings::backoff()->schedule();
$wcwg_human    = array();
foreach ( $wcwg_schedule as $wcwg_i => $wcwg_d ) {
	$wcwg_human[] = '+' . human_time_diff( 0, $wcwg_d );
}
?>
<form method="post" action="options.php" style="max-width:900px">
	<?php settings_fields( 'wcwg_settings_group' ); ?>

	<h2><?php esc_html_e( 'Retries', 'webhook-guard-for-woocommerce' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'WooCommerce tries once and gives up. Webhook Guard retries network errors, timeouts and 5xx/429 answers with exponential backoff.', 'webhook-guard-for-woocommerce' ); ?>
		<?php if ( $wcwg_human ) : ?>
			<br><strong><?php esc_html_e( 'Current schedule:', 'webhook-guard-for-woocommerce' ); ?></strong> <?php echo esc_html( implode( ', ', $wcwg_human ) ); ?>
		<?php endif; ?>
	</p>
	<table class="form-table">
		<tr><th><label for="wcwg_max_retries"><?php esc_html_e( 'Max retries', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="number" min="0" max="20" id="wcwg_max_retries" name="wcwg_settings[max_retries]" value="<?php echo (int) $wcwg_s['max_retries']; ?>"> <span class="description"><?php esc_html_e( '0 disables retries (log and alert only).', 'webhook-guard-for-woocommerce' ); ?></span></td></tr>
		<tr><th><label for="wcwg_base_delay"><?php esc_html_e( 'First retry after (seconds)', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="number" min="5" max="86400" id="wcwg_base_delay" name="wcwg_settings[base_delay]" value="<?php echo (int) $wcwg_s['base_delay']; ?>"></td></tr>
		<tr><th><label for="wcwg_multiplier"><?php esc_html_e( 'Backoff multiplier', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="number" step="0.5" min="1" max="10" id="wcwg_multiplier" name="wcwg_settings[multiplier]" value="<?php echo esc_attr( $wcwg_s['multiplier'] ); ?>"></td></tr>
		<tr><th><label for="wcwg_max_delay"><?php esc_html_e( 'Longest wait between two attempts (seconds)', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="number" min="60" max="604800" id="wcwg_max_delay" name="wcwg_settings[max_delay]" value="<?php echo (int) $wcwg_s['max_delay']; ?>"></td></tr>
		<tr><th><?php esc_html_e( 'Also retry 4xx answers', 'webhook-guard-for-woocommerce' ); ?></th>
			<td><label><input type="checkbox" name="wcwg_settings[retry_4xx]" value="1" <?php checked( $wcwg_s['retry_4xx'] ); ?>> <?php esc_html_e( 'Off by default: a 400/401/404 means the receiver rejected the payload, sending it again rarely helps.', 'webhook-guard-for-woocommerce' ); ?></label></td></tr>
	</table>

	<h2><?php esc_html_e( 'Disabled webhooks', 'webhook-guard-for-woocommerce' ); ?></h2>
	<p class="description"><?php esc_html_e( 'WooCommerce disables a webhook after more than 5 consecutive failures and never turns it back on. Webhook Guard re-enables it with a growing delay, resets the failure counter, then replays the deliveries recorded as dead while it was down.', 'webhook-guard-for-woocommerce' ); ?></p>
	<table class="form-table">
		<tr><th><?php esc_html_e( 'Automatic re-enable', 'webhook-guard-for-woocommerce' ); ?></th>
			<td><label><input type="checkbox" name="wcwg_settings[auto_reenable]" value="1" <?php checked( $wcwg_s['auto_reenable'] ); ?>> <?php esc_html_e( 'Re-enable disabled webhooks automatically', 'webhook-guard-for-woocommerce' ); ?></label></td></tr>
		<tr><th><label for="wcwg_reenable_delay"><?php esc_html_e( 'First re-enable after (seconds)', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="number" min="60" max="86400" id="wcwg_reenable_delay" name="wcwg_settings[reenable_delay]" value="<?php echo (int) $wcwg_s['reenable_delay']; ?>"> <span class="description"><?php esc_html_e( 'Doubles after every failed attempt, capped at 6 hours.', 'webhook-guard-for-woocommerce' ); ?></span></td></tr>
		<tr><th><label for="wcwg_reenable_max"><?php esc_html_e( 'Give up after N re-enables', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="number" min="1" max="100" id="wcwg_reenable_max" name="wcwg_settings[reenable_max]" value="<?php echo (int) $wcwg_s['reenable_max']; ?>"></td></tr>
		<tr><th><label for="wcwg_max_failures"><?php esc_html_e( 'Failures before WooCommerce disables a webhook', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="number" min="0" max="1000" id="wcwg_max_failures" name="wcwg_settings[max_failures]" value="<?php echo (int) $wcwg_s['max_failures']; ?>"> <span class="description"><?php esc_html_e( '0 keeps the WooCommerce default (5). Raise it if your receiver has short outages often.', 'webhook-guard-for-woocommerce' ); ?></span></td></tr>
	</table>

	<h2><?php esc_html_e( 'Alerts', 'webhook-guard-for-woocommerce' ); ?></h2>
	<table class="form-table">
		<tr><th><label for="wcwg_alert_email"><?php esc_html_e( 'Email', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="text" class="regular-text" id="wcwg_alert_email" name="wcwg_settings[alert_email]" value="<?php echo esc_attr( $wcwg_s['alert_email'] ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"> <span class="description"><?php esc_html_e( 'Comma-separated. Empty = site admin email.', 'webhook-guard-for-woocommerce' ); ?></span></td></tr>
		<tr><th><label for="wcwg_alert_webhook_url"><?php esc_html_e( 'Slack / Discord / automation URL', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="url" class="regular-text" id="wcwg_alert_webhook_url" name="wcwg_settings[alert_webhook_url]" value="<?php echo esc_attr( $wcwg_s['alert_webhook_url'] ); ?>" placeholder="https://hooks.slack.com/services/…"> <span class="description"><?php esc_html_e( 'A JSON POST with a "text" field plus the structured event (works with Slack, Discord, Mattermost, n8n, Make, Zapier).', 'webhook-guard-for-woocommerce' ); ?></span></td></tr>
		<tr><th><label for="wcwg_alert_throttle"><?php esc_html_e( 'Same alert at most every (seconds)', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="number" min="0" max="86400" id="wcwg_alert_throttle" name="wcwg_settings[alert_throttle]" value="<?php echo (int) $wcwg_s['alert_throttle']; ?>"></td></tr>
	</table>

	<h2><?php esc_html_e( 'Log', 'webhook-guard-for-woocommerce' ); ?></h2>
	<table class="form-table">
		<tr><th><?php esc_html_e( 'Store payloads', 'webhook-guard-for-woocommerce' ); ?></th>
			<td><label><input type="checkbox" name="wcwg_settings[store_body]" value="1" <?php checked( $wcwg_s['store_body'] ); ?>> <?php esc_html_e( 'Keep request and response bodies with each row. Off by default: order and customer payloads contain personal data.', 'webhook-guard-for-woocommerce' ); ?></label></td></tr>
		<tr><th><label for="wcwg_retention_days"><?php esc_html_e( 'Keep rows for (days)', 'webhook-guard-for-woocommerce' ); ?></label></th>
			<td><input type="number" min="1" max="365" id="wcwg_retention_days" name="wcwg_settings[retention_days]" value="<?php echo (int) $wcwg_s['retention_days']; ?>"></td></tr>
	</table>

	<?php submit_button(); ?>
</form>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wp_nonce_field( 'wcwg_action' ); ?>
	<input type="hidden" name="action" value="wcwg_action">
	<input type="hidden" name="do" value="purge">
	<button type="submit" class="button"><?php esc_html_e( 'Purge old rows now', 'webhook-guard-for-woocommerce' ); ?></button>
</form>
