# Professional English SMS — completion report

## 1. Source audit

The six customer SMS defaults were defined in `app/Services/Settings.php`, in `defaultSmsTemplates()`. `smsTemplates()` merges saved overrides, `activeSmsTemplate()` respects the existing template switch, and `SmsService` renders the selected text through `NotificationVariables`. Settings supplies these same defaults to Reset and the preview editor. The working database contained an empty saved template array, so no client customization needed replacement on this installation.

Order creation, ready notifications, partial/final payment, due reminders and date extension already use these six IDs. The extension controller supplies real `oldDate`, `newDate` and `reason` values. Payment amounts already include the configured currency symbol; adding literal `Rs` to the templates would have duplicated it.

## 2. Files changed in this task

- `app/Services/Settings.php`: six English defaults; safe resolution of retired saved text; complete current templates in client responses.
- `app/Services/SmsTemplateContent.php` (new): SMS-only plain-text rendering, currency spacing, optional-clause handling and retired-language detection.
- `app/Services/NotificationVariables.php`: delegates SMS content rendering and leaves unavailable previous-date/reason context empty.
- `app/Http/Controllers/SettingController.php`: supplies normalized SMS preview variables and rejects the recognized retired wording when saving templates.
- `resources/views/settings/index.blade.php`: content preview/test-send consistency, rendered character count, multipart wording and dirty tracking. Layout, styles, provider controls and the editor's 500-character limit are unchanged.
- `database/migrations/2026_09_09_000001_upgrade_sms_templates_to_english.php` (new): safe saved-content upgrade.
- `tests/Integration/EnglishSmsTemplatesTest.php` (new): six-event output, edge cases, migration preservation, defaults restoration and test-send checks.
- `tests/Fixtures/legacy-sms-defaults.json` (new): historical input fixture for all six original defaults.
- `ENGLISH-SMS-REPORT.md` (new): this report.

Earlier SMS-only removal changes remain in the working tree. This task did not modify Veevo/SPEXT or SendPK HTTP adapters, provider selection, credentials, sending architecture, notification enablement or unrelated business modules.

## 3. New defaults

Money variables already carry currency. The content renderer normalizes the existing `Rs.` prefix to `Rs ` for SMS only, without changing invoice or other application money formatting.

### order-created

{shopName}: Dear {customerName}, your order {orderID} has been received. {garmentType}. Total: {totalAmount}, Advance: {advancePaid}, Balance: {remainingBalance}. Due: {dueDate}. Thank you.

### order-ready

{shopName}: Dear {customerName}, your {garmentType} is ready for collection. Order: {orderID}. Balance due: {remainingBalance}. For assistance, call {shopPhone}.

### payment-received

{shopName}: Dear {customerName}, we have received your payment of {paidAmount} for order {orderID}. Remaining balance: {remainingBalance}. Thank you.

### due-reminder

{shopName}: Reminder for {customerName}: Order {orderID} ({garmentType}) is scheduled for delivery on {dueDate}. Balance due: {remainingBalance}. Contact: {shopPhone}.

### due-extended

{shopName}: Dear {customerName}, the delivery date for order {orderID} has been updated from {oldDate} to {newDate}. Reason: {reason}. We apologize for the inconvenience.

### final-receipt

{shopName}: Dear {customerName}, order {orderID} is fully paid. Total: {totalAmount}. Balance: {remainingBalance}. Thank you for choosing {shopName}.

## 4. Database upgrade behavior

The forward migration was applied successfully to the working database and tested on the isolated MySQL database. It changes only recognized retired wording in known saved template IDs. Customized English text, names, event metadata, active flags, provider configuration and unrelated settings are preserved. If replacement is needed, the original JSON is retained under the inert archive key `sms_templates_before_english_20260909`; an existing archive is not overwritten. The current installation had no overrides to replace, so no archive was necessary.

The upgrade is idempotent. Its rollback intentionally does not reintroduce retired wording or overwrite later client edits. No reset, `migrate:fresh`, `db:wipe` or destructive schema operation was run. The resolver also prevents recognized retired text from becoming active if an older backup is restored; the original stored value is not silently deleted.

## 5. Restore Defaults and Test send

Panel Restore Defaults returns the new six English definitions; the individual Reset action uses the same server-provided definitions. The existing reset behavior for the rest of the SMS panel was not changed.

Template editing now marks the panel dirty, so Test send cannot accidentally use unsaved edits. After saving, Test send renders the saved template through the same preview content rules. Backend and preview rendering were compared for all six defaults, with and without optional contact/reason/previous-date values. No live SMS was sent during verification.

## 6. Rendered examples

These are synthetic test examples using the requested sample customer and shop names. Production uses actual variables. The example receipt uses a zero balance; the partial-payment example uses a remaining balance of Rs 450.

### order-created (172 characters)

Best Tailor: Dear Sana Javed, your order ENGLISH-010 has been received. Alteration and Fitting. Total: Rs 900, Advance: Rs 450, Balance: Rs 450. Due: 15/09/2026. Thank you.

### order-ready (158 characters)

Best Tailor: Dear Sana Javed, your Alteration and Fitting is ready for collection. Order: ENGLISH-010. Balance due: Rs 450. For assistance, call 0303 4980786.

### payment-received (130 characters)

Best Tailor: Dear Sana Javed, we have received your payment of Rs 450 for order ENGLISH-010. Remaining balance: Rs 450. Thank you.

### due-reminder (165 characters)

Best Tailor: Reminder for Sana Javed: Order ENGLISH-010 (Alteration and Fitting) is scheduled for delivery on 15/09/2026. Balance due: Rs 450. Contact: 0303 4980786.

### due-extended (178 characters)

Best Tailor: Dear Sana Javed, the delivery date for order ENGLISH-010 has been updated from 15/09/2026 to 17/09/2026. Reason: Schedule change. We apologize for the inconvenience.

### final-receipt (128 characters)

Best Tailor: Dear Sana Javed, order ENGLISH-010 is fully paid. Total: Rs 900. Balance: Rs 0. Thank you for choosing Best Tailor.

## 7. Final legacy-text search

No active default or resolved customer SMS template contains the retired Roman Urdu wording. The remaining matching phrases are intentionally limited to:

- The retired-text detector, which rejects or upgrades those phrases.
- Historical test fixtures and migration regression inputs.
- Existing receipt/printing labels and unrelated operator-facing wording in the order UI. These are not SMS templates and were preserved as instructed.
- Older documentation, logs or verification fixtures where applicable; these are not active SMS defaults.

The current database still contains `sms_templates = []`, so it resolves entirely to the new English defaults. SMS remains disabled and the existing SendPK selection remains unchanged.

## 8. Verification

- Full isolated MySQL suite: **84 passed, 589 assertions**.
- All six rendered outputs checked for customer/order/amount/date accuracy, unresolved tokens, malformed currency, HTML, duplicate spaces and retired wording.
- Edge cases covered: long customer and garment names, absent shop phone, absent reason/previous date, zero balance and large grouped amounts.
- All six original defaults upgrade to English without enabling their disabled flags; customized English and original archive data survive.
- Panel Restore Defaults and saved-template test sending pass with mocked provider calls and SMS log assertions.
- PHP syntax checks pass for all changed PHP source and the migration.
- Blade compilation and configuration cache clearing pass.
- Rendered JavaScript: **68 script blocks pass syntax checks**. Preview/backend consistency: **12 of 12 comparisons pass** (six defaults plus optional-field variants).

## 9. Limits and remaining issues

No new implementation blocker remains. The existing 500-character editor limit, 1000-character test-request limit and 2000-character sender/backend limit are preserved. Preview counts show actual rendered characters; they do not claim to calculate GSM-7 segments. Long messages or non-GSM characters can require multiple chargeable segments.

Free-text customer names, garment descriptions and supplied reasons are not automatically translated. The supplied/default wording is professional English; the guard recognizes the retired phrases rather than claiming to be a general language detector. Live account delivery remains untested: providers and credentials were not changed, SMS remains disabled, and all send tests used mocks.
