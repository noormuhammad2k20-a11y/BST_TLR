# Text SMS setup

Customer messages use the centralized `SmsService` through `CustomerNotificationDispatcher`. Six existing event templates remain editable under Settings → SMS Settings. The customer detail SMS action uses the same service. Browser alerts remain internal application notifications.

No SMS credentials belong in `.env`: save `sms_enabled`, `sms_provider` (`veevo` or `sendpk`), `veevo_api_key`, optional `veevo_sender_id`, `sendpk_api_key`, required `sendpk_sender_id`, and `sms_templates` through Settings. API keys are encrypted with the existing Laravel `APP_KEY` and masked in responses. Preserve that key when moving the application. HTTP timeouts are in `config/messaging.php`.

No database schema cleanup is needed for this change. Historical tables and records remain unchanged. No data reset is required.

## Veevo Tech / SPEXT

Choose **Veevo Tech / SPEXT — Recommended** in SMS Settings. Enter **API Key** from the Veevo/SPEXT account. **Sender ID / Masking** is optional; leave empty for the account default. Arrange sender approval and account credit with Veevo. Save, then enable SMS under Notifications → Delivery Channels.

**Validate configuration** checks that required settings are present; it does not remotely authenticate the key. No documented balance/no-send credential endpoint is implemented. Use the provider dashboard for credit and an explicitly authorized **Send Test SMS** for a real sending check. Sending uses JSON POST `https://api.veevotech.com/v3/sendsms`. Rates are account-specific and are not hard-coded.

## SendPK

Choose **SendPK**, enter **API Key** and **approved Sender ID / Name**, and save. Complete account verification/sender approval and ensure credit in SendPK. Existing saved credentials remain available when switching providers. Production requests use POST bodies to keep API keys out of URLs.

Send endpoint: `https://sendpk.com/api/sms.php`. Balance endpoint: `https://sendpk.com/api/balance.php`. Unicode is selected for non-ASCII text. Sender routing follows account approval; the obsolete application SMS-type selector is not sent. A plain balance response matching a numeric error code is ambiguous; verify that balance in the provider dashboard instead of treating it as a successful connection check.

## Operations and verification

SMS is the only outbound customer message channel. Provider errors do not roll back orders/payments and do not trigger another provider or automatic retries. Check unknown-outcome failures before manually resending. Logs store acceptance/failure, provider message IDs, event, order/customer references and sanitized metadata. A logging outage cannot invalidate a completed transaction.

Run `php artisan schedule:run` every minute to execute the existing fifteen-minute order sweep and due-reminder checks. Page and scheduler reminder checks share an atomic cache repeat guard. Reminder timing follows Notifications settings. Keep the configured shared cache available.

Run `php artisan test`, `npm run build`, `php artisan route:list`, and `php artisan view:cache`. MySQL integration tests require the fixed isolated database; run `php dev/tools/test-database.php`, then set `INTEGRITY_MYSQL=1` only for the test process. Automated tests mock all external sends. Never put real credentials in test fixtures.


Veevo is the default and SendPK is a manually selected alternative. Automatic fallback and automatic retry are not implemented. A timeout may mean a provider accepted a message; verify its status before choosing to resend. No worker is required for customer SMS: sends are synchronous after transactions commit. Use the existing scheduler for unattended due reminders.

Phone numbers remain unchanged in customer records. Dispatch accepts common Pakistan mobile formats and sends `+923XXXXXXXXX` to Veevo and `923XXXXXXXXX` to SendPK, using the existing provider adapters. Malformed numbers, empty messages and messages exceeding 2000 characters fail without an HTTP send. The Settings test field has a 1000-character request limit. Long/Unicode SMS can span multiple chargeable segments.

`SmsLog`/`sms_logs` identifies the SMS channel and records provider, normalized recipient, event, order/customer IDs, acceptance/failure, provider message ID, timestamp and sanitized error metadata. Acceptance is not proof of handset delivery. Missing/disabled templates make no provider request. Explicit manual notify can resend; saving a Ready order does not. Payment operation keys and the shared reminder guard protect their existing workflows from repeats.
