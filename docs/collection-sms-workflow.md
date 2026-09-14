# Collection SMS workflow

The September 12 collection workflow supersedes the earlier Ready/SMS behavior described in delivery-workflow-report.md.

Delivery is an operator notification board. Its normal list contains Ready for Verification and Ready orders. Due-date filters also expose unfinished orders for planning, with sending disabled. All counts, quantities, dates, settings and contacts come from the database.

## Sending and status

One provider request combines a customer's selected orders, linked individually in collection_sms_orders. The server rechecks status and contact information, acquires a durable phone lock, and commits a unique sending attempt before contacting the provider. Only an accepted provider response marks an order Ready through the existing OrderService, keeping status history, delivery synchronization and staff accounting intact. Failed responses are logged and leave the order unnotified; they can be retried. Orders page Ready/bulk actions use the same service.

Accepted means the provider accepted the message, not verified handset delivery. A timeout has an unknown outcome and blocks another send. SMS History lets an authorized operator check the provider, wait at least two minutes, type CHECKED and record the confirmed outcome. Confirming acceptance also requires a provider reference. Durable accepted logs can recover an interrupted status update without sending again.

## Reminders and settings

First collection notices and reminders have separate per-order reasons. A reminder becomes eligible after the configured interval from the last successful collection message; default seven complete days. Every successful reminder restarts the interval. A two-minute phone cooldown also prevents immediate notices for a different order. Pending/unknown messages block that phone. Delivered or archived orders never receive collection messages.

Settings > Delivery & Reminders stores alert enablement, 0–365 days advance notice, overdue alerts, reminder alerts, 1–365 day reminder interval, manual reminder SMS enablement and dashboard notifications. Changes affect subsequent board refreshes and sends. These settings do not enable automatic SMS. Dashboard/bell alerts use the existing scheduler and business hours; the page refreshes every 30 seconds.

Legacy Ready orders remain Ready. If no prior notification exists they appear as needing a first notice. Existing data, payments and financial totals were not reset. The migration adds reasons/attempt IDs, order links and phone locks; foreign keys restrict deletion of message history. Customer anonymization also scrubs shared SMS contact copies while retaining links and statuses.

## Validation

Feature tests cover success/failure, grouped sends, seven-day boundaries, repeated reminders, disabled SMS/reminders, pending and uncertain attempts, operator reconciliation, legacy records, dynamic counts, collection, settings, payment preservation, transaction safety and alerts. Browser DOM tests render the real local Delivery and Settings pages and exercise selection, filtering and settings controls. Feature/Unit regression, Blade compilation and Vite build were run.

Live SMS is currently disabled and no provider credentials are configured. No real SMS was sent during implementation. Configure the existing SMS Settings before operational sending. Tests simulate provider responses only in their isolated test databases.

Validation details: 52 Feature/Unit tests passed (314 assertions), followed by the added alert test within the 17-test collection suite (91 assertions). Delivery and Settings also passed headless Edge checks using the real locally rendered data and bundled application assets, with outbound requests intercepted. Six current DOM browser scripts passed. Two older browser scripts did not pass against their pre-rendered dev/artifacts/mixed-orders-browser.html fixture: order-status-workflow expects the superseded four-status list; mixed-orders times out locating its old modal select. These legacy fixture checks are not counted as passed, and a full live-provider end-to-end send remains unverified until SMS is configured.
