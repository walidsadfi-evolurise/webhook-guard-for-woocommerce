# Webhook Guard for WooCommerce

**Retries failed WooCommerce webhook deliveries, keeps a replayable log, alerts you when a webhook gets disabled and switches it back on.**

[![CI](https://github.com/walidsadfi-evolurise/webhook-guard-for-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/walidsadfi-evolurise/webhook-guard-for-woocommerce/actions/workflows/ci.yml)
![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-blue)
![WooCommerce 8.0+](https://img.shields.io/badge/WooCommerce-8.0%2B-96588a)
![License GPLv2](https://img.shields.io/badge/license-GPLv2-green)

## The problem

WooCommerce webhooks carry orders to your ERP, CRM, warehouse or automation platform. Out of the box:

1. a delivery is attempted **once** — a timeout or a 503 and the event is gone;
2. after more than five consecutive failures WooCommerce **disables the webhook** and never re-enables it;
3. nobody is told. Orders keep coming in, nothing reaches the other system.

The WooCommerce log shows what happened; it does not fix it. This plugin does.

## What it does

| | WooCommerce alone | with Webhook Guard |
|---|---|---|
| Receiver times out | event lost | retried at +1, +3, +9, +27, +81 min (configurable) |
| Receiver answers 503 / 429 | event lost | retried with backoff |
| Receiver answers 404 / 401 | event lost | logged as *dead*, alert, one-click replay (retrying a rejected payload is opt-in) |
| 6 failures in a row | webhook disabled, silently, forever | alert → re-enabled after 5, 10, 20… min → failure counter reset → everything recorded as dead meanwhile is replayed |
| "Did order #4821 reach the ERP?" | grep the log | filter the Deliveries screen, see every attempt, replay it |

Plus: Site Health tests, a red notice on WooCommerce screens while a webhook is disabled, email + Slack/Discord/n8n alerts (throttled), WP-CLI, HPOS compatible.

## Install

Requires WordPress 6.4+, PHP 7.4+, WooCommerce 8.0+.

```bash
wp plugin install https://github.com/walidsadfi-evolurise/webhook-guard-for-woocommerce/archive/refs/heads/main.zip --activate
```

or upload the folder to `wp-content/plugins/`. Then **WooCommerce → Webhook Guard → Settings**: check the retry schedule, add an alert email or a Slack URL. Existing webhooks are covered immediately.

## WP-CLI

```bash
wp wc webhook-guard stats
wp wc webhook-guard list --status=dead
wp wc webhook-guard replay 42
wp wc webhook-guard replay --webhook=7 --status=dead --background
wp wc webhook-guard reenable 7
```

## How it works

```
WC_Webhook::deliver()
   └─ do_action('woocommerce_webhook_delivery', …)      ← WooCommerce fires this after EVERY attempt
        └─ WCWG_Retry::on_delivery()
             ├─ success → row "succeeded"
             ├─ retryable failure & attempts left → row "failed", Action Scheduler job in +delay
             └─ otherwise → row "dead", alert

do_action('woocommerce_webhook_disabled_due_delivery_failures')
   └─ WCWG_Recovery::on_disabled() → alert, schedule re-enable (delay doubles, cap 6 h)
        └─ reenable(): status=active, failure_count=0, replay dead rows 3 s apart
```

Nothing WooCommerce builds is altered: payload, headers and signature are the originals. A replay rebuilds the payload from the resource's **current** state.

Request/response bodies are not stored unless you enable it (they contain personal data). Rows are purged after 30 days by default.

## Hooks

| Hook | Purpose |
|---|---|
| `wcwg_should_retry( $retry, $code, $attempt, $webhook_id, $arg )` | veto or force a retry |
| `wcwg_delivery_succeeded( $row_id, $row )` / `wcwg_delivery_dead( $row_id, $row )` | react to outcomes |
| `wcwg_webhook_disabled( $webhook_id, $state )` / `wcwg_webhook_reenabled( $webhook_id, $manual )` | recovery events |
| `wcwg_alert_payload( $payload, $event )` / `wcwg_alert_sent( $event, $payload )` | alert pipeline |

## Development

```bash
composer install
composer test        # unit tests (no WordPress needed)
composer lint        # WordPress coding standards
php tests/run-without-phpunit.php   # same unit tests, zero dependencies
```

Integration tests run in CI against a real WordPress + WooCommerce (`tests/integration`): a fake receiver answers 503, then recovers; a webhook is driven to "disabled" by seven failures, re-enabled, and its dead deliveries replayed.

## License

GPLv2 or later. Built by [Walid Sadfi](https://walidsadfi.com) — Forward Deployed Engineer, WooCommerce & AI agents.
