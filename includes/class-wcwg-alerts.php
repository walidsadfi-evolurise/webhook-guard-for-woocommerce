<?php
/**
 * Alerts: email and a generic JSON webhook (Slack, Discord, n8n, Make…).
 *
 * @package WebhookGuard
 */

defined( 'ABSPATH' ) || exit;

/**
 * Throttled notifications.
 */
class WCWG_Alerts {

	/**
	 * Send an alert unless the same (event, webhook) pair fired within the throttle window.
	 *
	 * @param string $event      dead_delivery | webhook_disabled | webhook_reenabled | reenable_gave_up.
	 * @param int    $webhook_id Webhook ID.
	 * @param array  $context    Extra data for the message.
	 */
	public static function send( $event, $webhook_id, array $context = array() ) {
		$throttle = (int) WCWG_Settings::get( 'alert_throttle' );
		$key      = 'wcwg_alert_' . $event . '_' . (int) $webhook_id;
		if ( $throttle > 0 && get_transient( $key ) ) {
			return;
		}
		if ( $throttle > 0 ) {
			set_transient( $key, 1, $throttle );
		}

		$webhook = wc_get_webhook( (int) $webhook_id );
		$name    = $webhook ? $webhook->get_name() : sprintf( '#%d', $webhook_id );
		$site    = wp_parse_url( home_url(), PHP_URL_HOST );

		$messages = array(
			'dead_delivery'     => array(
				/* translators: 1: webhook name, 2: site host */
				'subject' => sprintf( __( '[%2$s] Webhook "%1$s": a delivery gave up after all retries', 'webhook-guard-for-woocommerce' ), $name, $site ),
				'body'    => __( 'A WooCommerce webhook delivery failed on every attempt. The receiver never got this event. You can replay it from WooCommerce → Webhook Guard.', 'webhook-guard-for-woocommerce' ),
			),
			'webhook_disabled'  => array(
				/* translators: 1: webhook name, 2: site host */
				'subject' => sprintf( __( '[%2$s] Webhook "%1$s" was DISABLED by WooCommerce', 'webhook-guard-for-woocommerce' ), $name, $site ),
				'body'    => __( 'WooCommerce disabled this webhook after too many consecutive failed deliveries. New events are no longer sent to the receiver until it is re-enabled.', 'webhook-guard-for-woocommerce' ),
			),
			'webhook_reenabled' => array(
				/* translators: 1: webhook name, 2: site host */
				'subject' => sprintf( __( '[%2$s] Webhook "%1$s" is active again', 'webhook-guard-for-woocommerce' ), $name, $site ),
				'body'    => __( 'Webhook Guard re-enabled the webhook. Dead deliveries recorded while it was down are listed under WooCommerce → Webhook Guard and can be replayed in bulk.', 'webhook-guard-for-woocommerce' ),
			),
			'reenable_gave_up'  => array(
				/* translators: 1: webhook name, 2: site host */
				'subject' => sprintf( __( '[%2$s] Webhook "%1$s" could not be re-enabled', 'webhook-guard-for-woocommerce' ), $name, $site ),
				'body'    => __( 'Every automatic re-enable attempt was followed by new failures. The receiver looks down for good; fix it, then re-enable the webhook manually.', 'webhook-guard-for-woocommerce' ),
			),
		);

		if ( ! isset( $messages[ $event ] ) ) {
			return;
		}

		$payload = array_merge(
			array(
				'event'        => $event,
				'site'         => home_url(),
				'webhook_id'   => (int) $webhook_id,
				'webhook'      => $name,
				'topic'        => $webhook ? $webhook->get_topic() : '',
				'delivery_url' => $webhook ? $webhook->get_delivery_url() : '',
				'admin_url'    => admin_url( 'admin.php?page=wcwg&webhook_id=' . (int) $webhook_id ),
				'time'         => gmdate( 'c' ),
			),
			$context
		);

		/**
		 * Filter the alert payload before it is sent.
		 *
		 * @param array  $payload Payload.
		 * @param string $event   Event name.
		 */
		$payload = apply_filters( 'wcwg_alert_payload', $payload, $event );

		self::email( $messages[ $event ]['subject'], $messages[ $event ]['body'], $payload );
		self::http( $messages[ $event ]['subject'], $payload );

		/**
		 * Fires after an alert was dispatched.
		 *
		 * @param string $event   Event name.
		 * @param array  $payload Payload.
		 */
		do_action( 'wcwg_alert_sent', $event, $payload );
	}

	/**
	 * Email channel.
	 *
	 * @param string $subject Subject.
	 * @param string $body    Lead paragraph.
	 * @param array  $payload Details.
	 */
	private static function email( $subject, $body, array $payload ) {
		$to = WCWG_Settings::get( 'alert_email' );
		if ( '' === $to ) {
			$to = get_option( 'admin_email' );
		}
		if ( ! $to ) {
			return;
		}
		$lines = array( $body, '' );
		foreach ( $payload as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$lines[] = sprintf( '%s: %s', $k, $v );
			}
		}
		wp_mail( array_map( 'trim', explode( ',', $to ) ), $subject, implode( "\n", $lines ) );
	}

	/**
	 * Generic webhook channel. The body carries both `text` (Slack/Discord/Mattermost render it)
	 * and the structured payload (n8n, Make, Zapier read it).
	 *
	 * @param string $subject One-line summary.
	 * @param array  $payload Details.
	 */
	private static function http( $subject, array $payload ) {
		$url = WCWG_Settings::get( 'alert_webhook_url' );
		if ( ! $url ) {
			return;
		}
		$body = array_merge(
			array(
				'text'    => $subject,
				'content' => $subject,
			),
			$payload
		);
		wp_safe_remote_post(
			$url,
			array(
				'timeout'  => 10,
				'blocking' => false,
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( $body ),
			)
		);
	}
}
