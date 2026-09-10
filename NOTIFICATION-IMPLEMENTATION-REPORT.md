> Archived implementation history. Superseded by [SMS-only audit and final report](SMS-REMOVAL-AUDIT.md). Do not use this document for current setup.

# Official notification integration — implementation report

Verified 8 September 2026 in the existing BST_TLR checkout. Existing uncommitted inventory, purchase-order, authorization and tailor-workspace changes were preserved. No deployment or working-database migration/reset was performed. No real messages were sent.

## Result

Laravel now sends WhatsApp through Meta Cloud API v26.0 with approved event mappings. Veevo/SPEXT is the fresh-install SMS default; SendPK remains supported and existing selections/credentials survive upgrades. Shared rendering and Pakistani phone normalization serve both channels. Each channel attempts independently after business commits and isolates provider, rendering and logging failures.

The six existing events remain: order created, order ready, payment received, due reminder, due extended and final receipt. Bulk extensions no longer send duplicate SMS; bulk delivery attempts SMS even if WhatsApp fails. Repeated ready saves do not resend, explicit notifications still can, recorded-payment replays do not send another receipt, and scheduler/page reminders share a repeat guard.

Settings retain existing panels and styling. Meta mappings are separate from local preview text. Selected-provider fields, encrypted saved credentials, masked responses, saved-configuration tests, authorization, CSRF and diagnostic throttles are enforced. API acceptance is distinguished from confirmed delivery. Unsupported Meta components produce validation errors.

## Notification file inventory

Added:

- `app/Services/CustomerNotificationDispatcher.php`
- `app/Services/DeliveryResult.php`
- `app/Services/NotificationPhone.php`
- `app/Services/NotificationVariables.php`
- `app/Models/WhatsAppLog.php`
- `config/messaging.php`
- `database/migrations/2026_09_06_000002_add_official_notification_logs.php`
- `tests/Integration/OfficialNotificationTest.php`
- `tests/Feature/NotificationMigrationTest.php`
- `tests/Unit/NotificationPhoneTest.php`
- `NOTIFICATION-SETUP.md`
- `NOTIFICATION-IMPLEMENTATION-REPORT.md`

Updated:

- `app/Services/WhatsAppService.php`, `app/Services/SmsService.php`
- `app/Services/Settings.php`, `app/Models/Setting.php`, `app/Models/SmsLog.php`
- `app/Services/BackupService.php`
- `app/Services/NotificationService.php`, `app/Services/OrderService.php`
- `app/Console/Commands/SweepOrderStatuses.php`
- `app/Http/Controllers/SettingController.php`
- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/DeliveryController.php`
- `app/Http/Controllers/PaymentController.php`
- `resources/views/settings/index.blade.php`
- `resources/views/orders/index.blade.php`
- `resources/views/delivery/index.blade.php`
- `resources/views/dashboard.blade.php`
- `routes/web.php` (notification routes only; existing unrelated edits preserved)
- `tests/TestCase.php` (block unexpected HTTP requests)
- `tests/Integration/BusinessIntegrityTest.php`, `tests/Integration/WorkflowCoverageTest.php` (notification expectations; existing edits preserved)
- `README.md`, `CLIENT-SETUP-GUIDE.md`, `.gitignore`, `.htaccess`

Removed: `app/Console/Commands/WhatsappDemo.php`; three obsolete `WHATSAPP-*-SETUP`/options guides (`WHATSAPP-AUTO-SETUP.md`, `WHATSAPP-FREE-AUTO-OPTIONS.md`, `WHATSAPP-SETUP-GUIDE.md`); all 13 tracked files under `whatsapp-gateway/`, including its package manifests, server/security modules, tests, README and startup/install scripts. Retired private-path ignore/access protections deliberately remain for upgraded hosts. Negative tests also retain obsolete names. Active application code, configuration, routes and views contain no old integration.

## Migration

The new forward migration adds indexed nullable `sms_logs.provider_message_id` and creates `whats_app_logs` with event, customer/order references, message ID, acceptance/failure, timestamps and sanitized diagnostic metadata. SMS structured metadata uses its existing `api_response` field. Historical SMS records remain intact.

If no provider row exists, saved SendPK credentials select SendPK; otherwise a fresh installation selects Veevo. Explicit selections are never overwritten. Local templates, credentials and inert obsolete settings rows remain stored. Rollback removes only the new log schema, keeping the selected provider and credentials.

Migration verification used SQLite memory databases (fresh and populated) and the designated MySQL database `atelier_integrity_test`. The working database was not migrated. Review the separately pre-existing inventory migration before any deployment.

## Actual verification results

| Command/check | Result |
|---|---|
| `php dev/tools/test-database.php` | Passed; designated isolated MySQL database prepared and migrated |
| `php dev/tools/test-database.php --reset-data` | Isolated test fixtures reset before final suite; no working data touched |
| `INTEGRITY_MYSQL=1 php artisan test` | 78 passed, 450 assertions, 21.79 seconds; no skipped tests |
| Final `INTEGRITY_MYSQL=1 php artisan test --filter=OfficialNotificationTest` | 22 passed, 153 assertions, 6.20 seconds after the final SmsLog helper adjustment |
| `npm run build` | Passed; Vite 7.3.6, 60 modules, 18.41 seconds |
| `php artisan view:cache` | Passed |
| `php artisan route:list --path=settings` | 19 settings routes; obsolete gateway routes absent |
| `php artisan route:list --path=settings/whatsapp -v` | Three endpoints; web/auth/active/business/admin middleware and throttle 6/min |
| `php -l` across app/config/routes/database/tests | 209 PHP files passed; final SmsLog adjustment also passed |
| `git diff --check` | Passed; line-ending notices only |
| Browser checks | No JavaScript errors; Meta mappings, saved test controls, SMS switching/save passed |

Tests cover provider requests/parsing, malformed responses/timeouts, Unicode, missing credentials, phone rejection, acceptance logs, four channel combinations, six events, business failure isolation, explicit resends, payment replay, reminder repetition, credential masking/encryption, provider switching, authorization, CSRF, throttles and migration preservation. External HTTP is mocked and unexpected requests are blocked. The initial default run skipped gated MySQL tests; the final full run enabled them and skipped none.

Desktop and 390px browser checks covered settings, orders, payment/billing and delivery against the isolated database. Existing theme, typography, spacing and shared layout files were preserved. The pre-existing fixed sidebar causes horizontal overflow at 390px; mobile usability is therefore limited by that existing shell. No responsive redesign was made. Screenshots and test output remain in ignored `dev/artifacts/notification-*` files. Temporary visual QA account/scripts were removed and the isolated preview server stopped.

## Remaining provider-side actions

Follow [NOTIFICATION-SETUP.md](NOTIFICATION-SETUP.md) for exact fields, six template bodies and ordered parameters, upgrade steps, scheduler and credential rotation.

Before live use: apply reviewed migrations with a backup; configure the existing minute scheduler; supply Meta token/Phone Number ID/WABA and approved templates; complete provider account/sender activation and credits; enable desired channel switches. Validate saved settings. A live test requires an explicitly authorized recipient. Provider-side approvals and actual delivery were not tested. No webhooks, inbound chat or automatic retries/failover are included.
