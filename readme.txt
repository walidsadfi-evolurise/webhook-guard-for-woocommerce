=== Webhook Guard for WooCommerce ===
Contributors: evolurise
Tags: woocommerce, webhooks, retry, integration, monitoring
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Retries failed WooCommerce webhook deliveries, keeps a replayable log, alerts you when a webhook gets disabled and switches it back on.

== Description ==

WooCommerce webhooks are how your store talks to your ERP, CRM, warehouse, invoicing tool or automation platform. Out of the box they are fragile:

* a delivery is attempted **once**; a timeout or a 503 from the receiver and the event is gone;
* after more than five consecutive failures WooCommerce **disables the webhook** and never re-enables it;
* nobody is told. Orders keep coming in, nothing reaches the other system, and you find out days later.

Webhook Guard fixes the three problems without touching how you create webhooks:

**Retries with exponential backoff.** Network errors, timeouts, 5xx and 429 answers are retried through Action Scheduler (1 min, 3 min, 9 min, 27 min, 81 min by default, all configurable). 4xx answers are not retried by default, because the receiver rejected the payload itself; you can turn that on.

**A delivery log you can act on.** Every attempt is a row: webhook, topic, resource, attempt number, response code, error, duration. Filter by status (succeeded, retrying, dead, replayed), search, and **replay** any delivery with one click; the payload is rebuilt from the resource's current state. Bulk replay runs in the background, spaced out so a receiver that just came back is not flooded.

**Disabled-webhook recovery.** When WooCommerce disables a webhook, Webhook Guard records it, alerts you, re-enables it after a growing delay (5 min, 10 min, 20 min… capped at 6 h), resets WooCommerce's failure counter and replays the deliveries recorded as dead while the webhook was down. If the receiver stays down after the configured number of attempts, you get a final alert and the webhook stays off.

**Alerts** by email and by a generic JSON webhook (Slack, Discord, Mattermost, n8n, Make, Zapier), throttled per webhook so an outage does not send you 200 messages.

**Site Health** tests: "all webhooks active" (critical when one is disabled) and "no delivery lost in the last 24 hours".

**WP-CLI**: `wp wc webhook-guard list --status=dead`, `replay <id>`, `replay --webhook=7 --background`, `reenable 7`, `stats`.

= Privacy =

Request and response bodies are **not stored** unless you enable it in the settings: order and customer payloads contain personal data. Rows are purged after 30 days by default.

= Hooks for developers =

* `wcwg_should_retry( bool $retry, string $code, int $attempt, int $webhook_id, mixed $arg )` — veto or force a retry.
* `wcwg_delivery_succeeded( int $row_id, array $row )`, `wcwg_delivery_dead( int $row_id, array $row )`.
* `wcwg_webhook_disabled( int $webhook_id, array $state )`, `wcwg_webhook_reenabled( int $webhook_id, bool $manual )`.
* `wcwg_alert_payload( array $payload, string $event )`, `wcwg_alert_sent( string $event, array $payload )`.

Compatible with High-Performance Order Storage. Needs WooCommerce 8.0 or newer (Action Scheduler is bundled with WooCommerce).

== Installation ==

1. Install and activate like any plugin. WooCommerce must be active.
2. Go to **WooCommerce → Webhook Guard → Settings**, check the retry schedule and add an alert email or Slack URL.
3. That's it. Existing webhooks are covered immediately; nothing changes in WooCommerce → Settings → Advanced → Webhooks.

== Frequently Asked Questions ==

= Does it change what WooCommerce sends? =

No. The payload, headers and signature are built by WooCommerce exactly as before. Webhook Guard only decides to deliver again, later.

= What is a "dead" delivery? =

A delivery that failed on every attempt, or that failed with an error that retrying would not fix (a 4xx by default), or that was due while its webhook was disabled. Dead deliveries are kept in the log and can be replayed at any time.

= Will a replay send the same payload? =

A replay rebuilds the payload from the resource as it is **now** (for example the order's current status). That is what you want for synchronisation; it also means a replay of an event about a deleted resource may send an empty payload. Enable "store payloads" if you need to see what was sent originally.

= Can I keep WooCommerce from disabling a webhook so fast? =

Yes: the "failures before WooCommerce disables a webhook" setting raises the threshold (filter `woocommerce_max_webhook_delivery_failures`).

= Does it work with custom "action.*" topics? =

Yes, as long as the first argument passed to the hook is a scalar (an ID). Deliveries whose argument is an object are logged but cannot be rebuilt for a retry.

== Screenshots ==

1. Deliveries log with status filters, replay and payload viewer.
2. Webhooks tab: status, consecutive failures, dead deliveries, one-click re-enable and replay.
3. Settings: retry schedule, recovery, alerts, retention.

== Changelog ==

= 1.0.0 =
* Initial release: retries with backoff, delivery log, replay, disabled-webhook recovery, alerts, Site Health, WP-CLI.
