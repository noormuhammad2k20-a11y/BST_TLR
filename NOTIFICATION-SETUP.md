# Official customer notification setup

## Safe upgrade

Back up the working database and application encryption key securely. Deploy code, run `php artisan migrate --force`, `php artisan optimize:clear`, then `npm run build`. Do not regenerate APP_KEY: it decrypts existing credentials. Do not reset/reseed a client database. Review all other pending migrations separately before deployment; this checkout includes pre-existing inventory changes.

This update adds `sms_logs.provider_message_id` and `whats_app_logs`. It selects SendPK when existing credentials have no saved provider selection; genuinely new installations default to Veevo. Explicit provider selection, SMS templates, local WhatsApp previews and historical records survive. Retired provider settings remain inert and are excluded from settings responses and portable exports.

Disable any previously installed local WhatsApp startup task/service on the host. The old process/scripts are no longer part of the application. Revoke obsolete provider tokens and linked sessions through the relevant account. Rotate any credential previously shared or committed. New secrets must be entered in Settings, never source code.

## Meta WhatsApp Cloud API

1. Set up a Meta business portfolio, app with WhatsApp, WABA, and registered business phone number. Complete the verification, billing and sender activation requested by Meta for your account.
2. Obtain a system-user access token with `whatsapp_business_messaging` and `whatsapp_business_management` permissions and access to the relevant WABA/phone. Store it securely; manage expiry and rotation in Meta.
3. In Settings → WhatsApp Business API enter **Meta Access Token**, **Phone Number ID**, and **WhatsApp Business Account ID (WABA)**. Saved dots mean unchanged. Save.
4. Create the following positional text-body utility templates in WhatsApp Manager. These are suggested names/text, not already-approved templates. Request approval and use the exact approved language code (`en_US` for the examples). An optional static footer is supported; headers, media, buttons and named parameters are not supported in this version.
5. Map each event to its approved name/language and enter the ordered variables below. Enable only complete mappings. Save and click **Test Connection & Templates**. Credentials and each mapping have separate verification results.
6. Enable WhatsApp under Notifications → Delivery Channels. Use **Send Test Message** only with a recipient authorized to receive the test. Tests use saved mappings and the most recent order; missing variable data prevents sending. Follow Meta recipient permission/opt-in requirements for live customer messaging.

| Event | Suggested name | Ordered Tailor variables |
|---|---|---|
| ORDER CREATED | bst_order_created | customerName, orderID, garmentType, dueDate, totalAmount, advancePaid, remainingBalance, shopName |
| ORDER READY | bst_order_ready | customerName, orderID, garmentType, remainingBalance, shopName |
| PAYMENT RECEIVED | bst_payment_received | customerName, orderID, paidAmount, remainingBalance, shopName |
| DUE DATE REMINDER | bst_due_reminder | customerName, orderID, dueDate, remainingBalance, shopName |
| DUE DATE EXTENDED | bst_due_extended | customerName, orderID, oldDate, newDate, reason, shopName |
| FINAL RECEIPT | bst_final_receipt | customerName, orderID, totalAmount, shopName |

### Template body examples (submit for approval)

- **bst_order_created:** Hello {{1}}, order {{2}} for {{3}} has been received. Due: {{4}}. Total: {{5}}, advance: {{6}}, balance: {{7}}. Thank you from {{8}}.
- **bst_order_ready:** Hello {{1}}, order {{2}} for {{3}} is ready for collection. Balance: {{4}}. Thank you from {{5}}.
- **bst_payment_received:** Hello {{1}}, payment for order {{2}} received: {{3}}. Remaining balance: {{4}}. Thank you from {{5}}.
- **bst_due_reminder:** Hello {{1}}, order {{2}} is due on {{3}}. Remaining balance: {{4}}. Thank you from {{5}}.
- **bst_due_extended:** Hello {{1}}, the due date for order {{2}} changed from {{3}} to {{4}}. Reason: {{5}}. Thank you from {{6}}.
- **bst_final_receipt:** Hello {{1}}, order {{2}} is fully paid. Total: {{3}}. Thank you from {{4}}.

Local WhatsApp preview text does not create or update Meta approvals. Final receipt retains the existing fully-paid payment trigger. Re-saving an order does not automatically resend a ready notice; explicit notify actions can resend.

The API version defaults to `v26.0` in `config/messaging.php`, optionally set through `META_GRAPH_VERSION`. All requests originate from Laravel over HTTPS. No inbound chat or delivery webhook is installed. An API message ID proves acceptance, not delivery/read status. Check Meta diagnostics for subsequent delivery problems.

## Veevo Tech / SPEXT

Choose **Veevo Tech / SPEXT — Recommended** in SMS Settings. Enter **API Key** from the Veevo/SPEXT account. **Sender ID / Masking** is optional; leave empty for the account default. Arrange sender approval and account credit with Veevo. Save, then enable SMS under Notifications → Delivery Channels.

**Validate configuration** checks that required settings are present; it does not remotely authenticate the key. No documented balance/no-send credential endpoint is implemented. Use the provider dashboard for credit and an explicitly authorized **Send Test SMS** for a real sending check. Sending uses JSON POST `https://api.veevotech.com/v3/sendsms`. Rates are account-specific and are not hard-coded.

## SendPK

Choose **SendPK**, enter **API Key** and **approved Sender ID / Name**, and save. Complete account verification/sender approval and ensure credit in SendPK. Existing saved credentials remain available when switching providers. Production requests use POST bodies to keep API keys out of URLs.

Send endpoint: `https://sendpk.com/api/sms.php`. Balance endpoint: `https://sendpk.com/api/balance.php`. Unicode is selected for non-ASCII text. Sender routing follows account approval; the obsolete application SMS-type selector is not sent. A plain balance response matching a numeric error code is ambiguous; verify that balance in the provider dashboard instead of treating it as a successful connection check.

## Operations and verification

WhatsApp and SMS toggles are independent. Provider errors do not roll back orders/payments and do not trigger another provider or automatic retries. Check unknown-outcome failures before manually resending. Logs store acceptance/failure, provider message IDs, event, order/customer references and sanitized metadata. A logging outage cannot invalidate a completed transaction.

Run `php artisan schedule:run` every minute to execute the existing fifteen-minute order sweep and due-reminder checks. Page and scheduler reminder checks share an atomic cache repeat guard. Reminder timing follows Notifications settings. Keep the configured shared cache available.

Run `php artisan test`, `npm run build`, `php artisan route:list`, and `php artisan view:cache`. MySQL integration tests require the fixed isolated database; run `php dev/tools/test-database.php`, then set `INTEGRITY_MYSQL=1` only for the test process. Automated tests mock all external sends. Never put real credentials in test fixtures.

Official references: [Meta API examples](https://www.postman.com/meta/whatsapp-business-platform/documentation/wlk6lh4/whatsapp-cloud-api), [Meta v26 release](https://github.com/facebook/facebook-nodejs-business-sdk/releases/tag/v26.0.0), [Veevo SMS](https://www.veevotech.com/api-docs/sms), [SendPK](https://sendpk.com/api.php).
