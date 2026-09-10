# SMS-only implementation audit

## Before editing

The active integration is `WhatsAppService` (Meta Cloud API), called alongside `SmsService` by `CustomerNotificationDispatcher`. Settings exposes Meta credentials, approved mappings, local preview templates and three test routes. Customer, order and delivery views contain direct links, icons or channel wording. `WhatsAppLog` writes a dedicated historical table. No gateway directory, gateway job, webhook or gateway-only Composer/npm dependency exists in the working tree. No project AGENTS.md was found.

| Trigger | Caller | Before | Planned after | Execution / history |
| --- | --- | --- | --- | --- |
| Order created | OrderService::create | Dispatcher, both enabled channels | One SMS attempt | After commit; SMS log |
| Ready / manual notify / bulk notify | OrderController; DeliveryController | Dispatcher, both enabled channels | One SMS per order | Synchronous; marks notified on acceptance |
| Partial payment | PaymentController::record | payment-received, both channels | One SMS | After financial commit; payment replay guard |
| Final payment | PaymentController::record | final-receipt, both channels | One SMS | After financial commit; payment replay guard |
| Upcoming due date | NotificationService::sweepDueOrders; orders:sweep and page polling | due-reminder, both channels | One SMS | Shared atomic repeat guard |
| Delivery date extension | OrderController::bulkExtend | due-extended, both channels | One SMS | After update; per unique order |
| Settings test | SettingController | Meta test endpoints and SMS test | SMS test only | Explicit, authenticated, throttled |
| Customer shortcut | Customer detail view | Direct external chat link | Validated manual SMS | Central SmsService, customer-linked log |

Cloth checkout, cloth payments, measurements, new customers, expenses, inventory and generic status changes do not call outbound messaging. Their internal notifications and business behavior stay intact. Marking Ready alone intentionally does not send; explicit ready-and-notify does.

## Implementation plan

1. Remove Meta sender/model/routes/settings/template editor and all active shortcuts or channel references.
2. Keep the existing SMS service, six SMS templates, provider payloads, normalization, encryption and after-commit behavior. Veevo is default; SendPK remains selectable. Preserve the existing no-retry/no-automatic-fallback policy to avoid duplicate delivery on uncertain responses.
3. Validate message length centrally and add the customer SMS action with existing owner authorization, CSRF and throttling.
4. Preserve historical tables, records and migrations; retired settings cannot be resolved or exported through the schema. No destructive database operation.
5. Update setup documentation and tests; run static, PHP, Blade, JavaScript, build and isolated database workflow checks. Report exact results and limitations.


## Final report — 9 September 2026

### 1. Components found

| File / component | Previous purpose |
| --- | --- |
| `app/Services/WhatsAppService.php` | Meta connection checks, approved template mapping validation, Cloud API template sends and logging |
| `app/Models/WhatsAppLog.php` | Runtime writes to `whats_app_logs` |
| `CustomerNotificationDispatcher::dispatch` | Independent attempts through both enabled text channels |
| `SettingController::{testWhatsapp,sendTest,testTemplate}` | Connection and template test endpoints |
| `Settings::{templates,activeTemplate,defaultTemplates}` and schema | Local rich-message previews, Meta credentials and channel switches |
| Settings Blade `panelWhatsApp`, `panelMeta`, Meta mapping/editor/test functions | Two messaging panels and obsolete configuration controls |
| Customer/order/delivery/notification views and shared layouts | Direct customer chat link, unused link generator, icons, filters, labels and runtime settings |
| `NotificationService::whatsappSent`, `OrderService::markNotified` | Internal notice category/icon on successful customer notification |
| Cloth-store settings and customer importer | Contact field and import-column alias |
| `.gitignore`, `.htaccess`, setup guides | References to already-removed local infrastructure |

No dedicated gateway process launcher, QR/link endpoint, webhook, job, listener, scheduled gateway command, local session directory or gateway npm manifest remained at the start of this task. Root Composer/npm dependencies serve Laravel, frontend rendering or shared libraries; none was exclusive to the removed sender.

### 2. Components removed

Deleted the Meta sender and log model. Removed the three controller actions and routes, two Settings panels, Meta mapping and preview editors, their JavaScript handlers, the direct customer link and unused order-link generator. Internal notices now use `smsSent`, the SMS category and a generic SMS icon. Shared layout exports and import aliases no longer expose the removed channel. Cloth settings now validate a shared allowlist, so retired or arbitrary keys cannot be saved or included in the settings view.

Removed active schema keys: `whatsapp_number`, `whatsapp_enabled`, `meta_access_token`, `meta_phone_number_id`, `meta_waba_id`, `meta_templates`, `message_templates` and the unused customer `email_enabled` switch. `sms_enabled` belongs to the SMS settings group. Historical stored values remain inert and cannot enable a removed sender.

### 3. Notification trigger map

The before/after table at the start of this report covers every actual outbound call chain found. All six existing events now dispatch exactly one SMS service attempt per invocation. Their template IDs remain `order-created`, `order-ready`, `payment-received`, `due-reminder`, `due-extended` and `final-receipt`.

Explicit order and delivery actions can resend intentionally. Re-saving Ready does not send. Bulk delivery rows are deduplicated by order ID. Payment replay protection and the atomic shared reminder guard remain. Browser/internal notifications for stock, payments and other business events remain separate from customer SMS. No new outbound trigger was invented for cloth checkout, measurements or generic status changes. No separate cloth-plus-stitching outbound integration was present.

### 4. SMS architecture

- Central customer event dispatcher: `App\Services\CustomerNotificationDispatcher`.
- Sender and provider adapters: `App\Services\SmsService`.
- Default provider: Veevo Tech / SPEXT. Alternative: SendPK, selected manually. Existing selection and saved credentials are preserved.
- Existing provider URLs, POST payloads and response interpretation remain; no endpoint was invented.
- No automatic provider fallback or retry. Uncertain network outcomes require checking the provider before resending.
- `NotificationPhone` normalizes valid Pakistan mobile numbers at send time; customer records retain their original phone strings.
- `Settings::smsTemplates` and `NotificationVariables` centralize content. Due reminders now use the actual delivery date instead of promising tomorrow.
- Empty, malformed-recipient and over-2000-character sends fail before HTTP dispatch. Disabled templates give a clear dispatcher error. Settings test requests retain their 1000-character limit.
- Financial transactions commit before automatic outbound dispatch. Rollbacks discard deferred callbacks. Provider/logging failures do not reverse business transactions.
- `SmsLog` / `sms_logs` identifies the SMS channel and records provider, recipient, event, order/customer, status, message ID, error and timestamps. No historical record was relabeled. Arbitrary SendPK response bodies are no longer retained in metadata; selected Veevo metadata is sanitized.
- API acceptance is reported accurately; it is not proof of delivery to the handset.

### 5. Settings final state

Only SMS delivery configuration remains: SMS enablement, Veevo/SendPK selection, encrypted/masked API keys, sender details, saved SMS templates, test SMS, configuration checks and the existing SendPK balance check. Browser alert preferences remain. No QR, linked-phone state, Meta token field, approved mapping editor, gateway status/counter or installation instruction remains in active settings.

### 6. Database changes

No migration file was added or altered. The working database had the existing `2026_09_06_000002_add_official_notification_logs` migration pending; it was reviewed and applied. It adds `sms_logs.provider_message_id`, initializes a missing SMS provider selection and creates the legacy historical log table. Its existing schema is retained for migration compatibility; there is no runtime writer for that historical table.

No reset, `migrate:fresh`, `db:wipe`, database drop or production-data deletion was run. Existing settings, customer data and notification history remain. Test migrations used in-memory SQLite or the fixed isolated MySQL database `atelier_integrity_test`. No test database reset was used.

### 7. Dependencies

No package removal was necessary, so Composer/npm manifests and lockfiles are unchanged. Composer’s optimized autoloader was regenerated to remove stale entries for the deleted sender and an already-missing legacy command. HTTP clients, Vite, Laravel and shared icon libraries remain in use. The common icon package includes unused brand glyph definitions; those are not a sender or integration.

### 8. Routes

Removed POST endpoints:

- `/settings/whatsapp/test` (`settings.whatsapp.test`)
- `/settings/whatsapp/test-template` (`settings.whatsapp.test-template`)
- `/settings/whatsapp/send-test` (`settings.whatsapp.send-test`)

Added POST `/customers/{customer}/sms` (`customers.sms`), protected by web CSRF, authentication, active-user and owner authorization plus six requests/minute throttling. It sends to the saved customer phone and records the customer ID. Existing SMS test/balance routes retain their protections. Unrelated route names remain unchanged.

### 9. Configuration and security review

Removed `messaging.meta_version` and the obsolete local-directory ignore/access entries. Neither `.env` nor `.env.example` contained a matching retired messaging variable requiring deletion. SMS secrets remain in encrypted settings, not environment documentation; the existing `APP_KEY` is still required. See [current SMS setup](NOTIFICATION-SETUP.md).

The tracked credential-literal check produced no unexplained hardcoded credential candidates. `.env` and the old private gateway config were not tracked; the path-specific Git history check returned no commits for them. This was a targeted check, not a comprehensive historical secret-scanner certification. No secret values were printed and Git history was not rewritten.

### 10. Files deleted

- `app/Services/WhatsAppService.php`
- `app/Models/WhatsAppLog.php`

No gateway-only scripts existed to delete. Temporary verification output is under ignored `dev/artifacts/`.

### 11. Files modified / added

- `.gitignore`
- `.htaccess`
- `CLIENT-SETUP-GUIDE.md`
- `NOTIFICATION-IMPLEMENTATION-REPORT.md`
- `NOTIFICATION-SETUP.md`
- `README.md`
- `app/Http/Controllers/ClothStore/SettingController.php`
- `app/Http/Controllers/CustomerController.php`
- `app/Http/Controllers/DeliveryController.php`
- `app/Http/Controllers/PaymentController.php`
- `app/Http/Controllers/SettingController.php`
- `app/Models/Notification.php`
- `app/Services/CustomerImporter.php`
- `app/Services/CustomerNotificationDispatcher.php`
- `app/Services/DeliveryResult.php`
- `app/Services/NotificationService.php`
- `app/Services/NotificationVariables.php`
- `app/Services/OrderService.php`
- `app/Services/Settings.php`
- `app/Services/SmsService.php`
- `config/messaging.php`
- `resources/views/cloth-store/layouts/app.blade.php`
- `resources/views/cloth-store/settings/index.blade.php`
- `resources/views/customers/index.blade.php`
- `resources/views/delivery/index.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/notifications/index.blade.php`
- `resources/views/orders/index.blade.php`
- `resources/views/settings/index.blade.php`
- `routes/web.php`
- `tests/Integration/BusinessIntegrityTest.php`
- `tests/Integration/OfficialNotificationTest.php`
- `tests/Integration/WorkflowCoverageTest.php`
- Added `SMS-REMOVAL-AUDIT.md` (this report).


### 12. Verification results

| Check | Result |
| --- | --- |
| Default `php artisan test --compact` | PASS: 7 tests, 42 assertions; 66 MySQL-only tests skipped before added coverage |
| Final isolated MySQL full suite | PASS: 78 tests, 511 assertions, no skips |
| Customer messaging | PASS: six event payloads/recipient/logs, both providers, disabled/invalid/oversized inputs, provider rejection/network failure, manual customer SMS, authorization |
| Duplicate/transaction protection | PASS: bulk notifications/extensions, deliberate manual resend, Ready-save behavior, payment replay, shared reminders and deferred-send rollback |
| Business workflows | PASS: existing authentication/customer/checkout/inventory/payment/refund/ledger/tailoring/staff/report/backup tests |
| Main screens | PASS: 13 authenticated pages render, including settings, measurements, orders, delivery, notifications and cloth checkout/settings |
| Rendered JavaScript | PASS: all 68 inline scripts from those 13 pages pass Node syntax checking |
| PHP syntax | PASS: 132 application/config/route/test files; changed controller also rechecked |
| Blade | PASS: `view:cache`, cleared and rebuilt after fixes |
| Vite | PASS: `npm run build`, 60 modules transformed |
| Routes / schedule | PASS: route list and `schedule:list`; existing `orders:sweep` every 15 minutes |
| Database | PASS: reviewed pending migration applied successfully; no destructive reset |
| Cache/config | PASS: `config:clear`, `cache:clear` |
| Composer validation / autoload | PASS: `composer.json` valid; optimized autoloader and package discovery regenerated |
| npm audit | PASS: zero vulnerabilities through official npm registry; configured mirror initially failed |
| Composer security audit | FAIL: four pre-existing high-severity advisories in `league/commonmark`; see below |
| Diff whitespace | PASS after cleanup |
| Real provider sends | NOT RUN: all automated HTTP sends were mocked; no customer contacted |

No live browser console/device delivery claim is made: UI verification consists of authenticated rendering plus compiled JavaScript checks. Physical printers, real SMS account routing and an end-to-end production operator walkthrough remain deployment checks.

### 13. Remaining references

There is zero active application sender, route, view control, configuration or dependency specifically implementing the removed channel. The full path/line inventory is in [reference inventory](dev/artifacts/sms-reference-inventory.csv). It stores matched terms and classifications, never source-line values or credentials.

Allowed remaining application-owned references are historical migration schema (`whatsapp` supplier fields and `whats_app_logs`), regression tests asserting retired configuration/routes cannot be used, this audit, and the clearly marked archived implementation report. Existing database notification history may still contain its original category/icon. Historical logs, generated caches and generic third-party library/icon definitions are separately classified in the inventory. They do not make outbound customer requests.

### 14. Remaining operational issues

Real Veevo or SendPK credentials, approved sender/credit and a deliberately chosen test recipient are needed for live verification. Read-only readiness check: the working installation retains SendPK selection, SMS is disabled, and required provider configuration is incomplete. The SMS log message-ID column is present. SMS was not enabled or sent merely to test this removal. Veevo has no implemented no-send authentication/balance endpoint; configuration readiness is not credential verification. SendPK fallback is manual, not automatic. A scheduler must be running for unattended reminders; customer sends themselves do not require a queue worker. Host services or startup tasks outside this repository were not modified.

Composer reports these existing `league/commonmark` advisories; package versions were left unchanged because they are unrelated to the removed integration:

- `GHSA-8rr7-cvq3-gmfh`: denial of service in the Attributes extension.
- `GHSA-jjv6-8j6v-6j52`: denial of service in SmartPunct/Attributes extensions.
- `GHSA-f8fg-pg57-v4j8`: event-handler filtering bypass in AttributesExtension.
- `GHSA-j8pm-gj4c-rq4x`: denial of service from crafted Markdown input.

The application change and tests are complete. Dependency security remediation and live-provider/deployment verification remain separately identified work.
