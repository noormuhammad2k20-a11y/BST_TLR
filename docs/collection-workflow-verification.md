# Collection notification workflow — implementation verification

Existing navy/light page components, filters, cards, table, bulk controls and due-date extension feature are retained.

## Status and actions

- Ready for Verification: verify garments and send Ready SMS; no collection action or future Delivered timeline step.
- Ready: successful notification, with collection confirmation and eligible reminders.
- Delivered: historical details and SMS history only; excluded from pending notifications, reminders and date alerts.
- Delivered is the canonical collection status. Existing Completed records are displayed with the same historical meaning without rewriting their stored history.
- OrderService validates locked current status for status and collection requests. An internal confirmation flag cannot promote Ready without a persisted successful SMS.
- Bulk status options are restricted to transitions supported by every selected order.

## Notifications and information

The existing SMS provider, durable per-phone claims, SMS-order relationships, failure handling, retry/reconciliation and database settings remain in use. Successful first SMS promotes Ready; failure does not. Successful reminders restart the configured interval (default seven days). The new reminder-system switch disables reminder eligibility independently from reminder alert/SMS preferences.

SMS history shows customer, order, phone, type, sent/attempted timestamps, result, attempt count, content and errors. Existing collection-first/collection-reminder values are presented as ready_notification/reminder; failures are labelled failed_attempt. Attempt Count is the cumulative customer collection-SMS attempt ordinal, derived from retained log rows.

Due Today and Overdue use shop-local calendar dates; Upcoming uses configured advance-alert days. Waiting 7+ Days is a separate seven-day collection-age filter. Cards and alerts use database data. Payment information uses the existing finance ledger.

## Existing records

Migration 2026_09_12_000003 repairs uncollected Ready records with neither a successful SMS nor legacy notified_at evidence. It preserves payments, garments, completion timestamps and existing history, adds an audit entry, and keeps delivered records untouched. Applied locally; no reset or seed was run.

## Verification

- 27 passing focused Laravel tests, 173 assertions: CollectionWorkflowTest, DeliveryAttentionMigrationTest, OrderAutoProgressTest, TailorReadyCreditTest.
- Premature collection rejects via delivery collect, delivery status, order status, and order update service paths.
- SMS success/failure, retry, duplicates, six/seven-day boundaries, next interval, collected exclusion, settings persistence and date filters covered using an isolated SQLite database and mocked HTTP provider. No test SMS went to customers.
- Rendered database Blade pages checked with JSDOM and headless Edge, including verification/Ready/Delivered modal actions, bulk controls, settings, and JavaScript error checks.
- npm run build passed.

Live SMS is disabled and the provider is not configured. Actual provider delivery remains unverified until configured. Existing invalid customer phones correctly block sending and show the reason. No demonstration records were added by this implementation.
