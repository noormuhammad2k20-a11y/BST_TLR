# Delivery workflow implementation report

Verified locally on 10 September 2026, Asia/Karachi. The user's explicit clarification selected the attached document's silent scheduler and read-only polling architecture, superseding the earlier no-scheduler instruction.

1. **Old timing discovered:** six stages with global elapsed-stage delays, most recently reconciled on normal authenticated requests. Old due reminders used day-based/repeat settings. The new workflow has four active statuses and one delivery attention calculation.
2. **Files changed:** full list below. The timestamp reconciliation middleware was deleted; dedicated timing/attention services and a silent-task installer were added.
3. **Settings removed:** auto_status_enabled, auto_status_received_delay, auto_status_unit, auto_status_pending_hours, auto_status_progress_delay, auto_status_verify_delay, auto_status_ready_delay, auto_delivery_update, at_risk_hours, delivery_slots and alert_days_before. One delivery_alert_hours control defaults to 4. Stock repeat settings remain for stock notifications.
4. **Delivery calculation:** existing delivery_date DATETIME combines an exact date/time. Attention starts at max(promised time minus lead, delivery-day opening). Legacy midnight dates use the end of the old time range; unknown ranges use 23:59. No duplicate delivery timestamp system was introduced.
5. **Opening:** existing weekly business_hours are reused. Before opening/after closing there are no automatic transitions or alerts. Closed-day promises wait until the next opening. Missed work catches up when checks next run during opening hours.
6. **Today’s Deliveries:** includes Received, In Progress and Ready due today; overdue first, then promised datetime. The existing card becomes the filter, also linked from the daily summary. Exact time appears in the table and edit wizard.
7. **Due Soon:** an unfinished order reaches its attention time but has not passed its promise. Browser countdown uses server clock offset and never changes workflow status.
8. **Overdue:** strictly later than promised datetime and still Received/In Progress. Ready is Ready for Pickup; its lateness at completion is separately available. Delivered is closed.
9. **Received → In Progress:** scheduler only, with normal Orders/Dashboard page catch-up. Candidates are queried by status/date, processed in chunks and locked/rechecked. Effective transition history and staff activity are recorded. Age since creation does not advance future orders.
10. **Ready:** physical staff confirmation from Received or In Progress; no time-based Ready. Existing styled actions and four-step timeline retained.
11. **Single SMS:** one automatic attempt after Ready commits. Sent/Failed remains independent of order status. Failure never rolls back completion. Already Ready action does not resend.
12. **Bulk Ready/SMS:** each eligible selected order is locked and marked Ready; each has its own SMS result. Response reports marked Ready, sent, failed and skipped counts. One provider failure does not stop later orders. Duplicate selected IDs are removed.
13. **Duplicate protection:** atomic attempted_at claim plus attempt UUID and state. Retry requires the previous failed UUID and atomically replaces it, preventing simultaneous retry clicks. Cache clearing cannot reopen the initial claim. Real provider delivery after an ambiguous timeout cannot be proven exactly once; explicit retry asks staff to check logs first.
14. **Delivered:** explicit handover from Ready, including the Delivery page. No automatic delivery transition.
15. **Payments:** payment completion and order collection are separate. Tests cover manual delivery with payment balance and ensure settling Ready does not deliver it.
16. **AJAX:** GET refresh every 60 seconds, countdown text every 30 seconds, immediate action responses. Live order and notification endpoints do not run scheduler logic or send SMS. Browser checks cover rendered In Progress, Ready and Delivered updates and Today sorting without navigation.
17. **Scheduler:** routes/console.php defines orders:check-deliveries every minute with overlap protection. Installed Windows task Atelier-Delivery-Attention-test-fnal_telor invokes D:\xamp\php\php-win.exe directly, quiet/noninteractive Artisan flags, no shell wrapper, Hidden=true, IgnoreNew, five-minute timeout. Verified LastTaskResult=0 and a next run one minute later. **Windows denied S4U registration; current task requires the account to stay signed in.** An administrator must run the supplied installer with default S4U for unattended operation. The website never displays scheduler output.
18. **Migration/database:** forward migration applied successfully to local test_fnal_telor and isolated atelier_integrity_test. Adds SMS state/attempt UUID and unique notification event_key. Existing status/delivery index is reused. Historical Ready remains Ready; unverified legacy stages become In Progress, Pending becomes Received, Completed becomes Delivered. Closed legacy rows remain historical, unavailable as new workflow choices. No database wipe/fresh migration was used.
19. **Local cleanup:** confirmed APP_ENV=local, loopback MySQL, database test_fnal_telor. Backed up then removed 33 old tailoring orders and their order-owned children within a transaction. Archive: storage/app/private/order-cleanup/20260910-222753-be425c.json (393,908 bytes). Original customers and customer-owned measurement sheets were retained. Product/service count 16, staff 10, users 1, expenses 15, settings 72, cloth inventory products 10 and unrelated payments 0 were checked unchanged across cleanup. Existing activity history unrelated to those order subjects remains.
20. **Five demo orders:** created through OrderService with fictional non-dialable LOCAL-DELIVERY-TEST contacts, outbound HTTP mocked. IDs and promises listed below. Creation happened at 22:27, after the configured 20:00 closing, so the real-clock check correctly made zero automatic transitions. Morning/afternoon lead boundaries were exercised separately with a frozen clock in isolated tests; local fixtures necessarily reflect the actual late-evening time.
21. **Executed checks:** php artisan test with isolated MySQL: **108 PASS, 768 assertions, 60.73 seconds**. Covers morning opening, daily/per-order dedup after cache clear, 13:00 attention for 17:00 promise, future order age, exact overdue boundary, read-only live requests, early Ready, duplicate Ready/bulk requests, partial SMS failure/retry, manual Delivered, migration preservation, existing business/payment/measurement flows. After the final seeder changes, the 15-test mixed-order suite passed again (96 assertions). Headless Edge browser: PASS four-status table/modal, manual Ready eligibility, exact-time input, read-only live updates and Today sorting including Ready, no JS errors. npm run build: PASS. artisan route:list, view:cache, schedule:list and manual orders:check-deliveries: PASS. Visual screenshot inspected after completing page animations. No real SMS was sent by these tests.
22. **WhatsApp:** no active WhatsApp reference in app/routes or current SMS settings. Historical migrations/log storage are retained. Windows task AtelierWhatsAppGateway still points at a missing nested whatsapp-gateway/run-hidden.vbs. **Windows denied disabling that existing task.** It is not used by the new delivery workflow; administrator cleanup remains needed.
23. **Remaining issues:** unattended Windows production registration and removal of the obsolete WhatsApp task require administrator rights. These were OS access-denied responses, not automatic approval-review rejections. SMS stays disabled locally; successful provider delivery was tested with mocked responses, not a live recipient. The five local samples are time-sensitive and will naturally become overdue as days pass. No deployed remote production server was changed.

## Five created orders

| Order | Scenario | Promised datetime (Asia/Karachi) |
|---|---|---|
| ORD-1044 (44) | Morning urgent | 2026-09-10 10:30 |
| ORD-1045 (45) | Later today; capped at today's end because setup was after 22:00 | 2026-09-10 23:59 |
| ORD-1046 (46) | Late evening | 2026-09-10 23:30 |
| ORD-1047 (47) | Tomorrow | 2026-09-11 17:00 |
| ORD-1048 (48) | Overdue at setup | 2026-09-10 21:42 |

## Changed files

- `app/Console/Commands/CheckDeliveryAttention.php`
- `app/Console/Commands/ResetLocalDeliveryOrders.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/DeliveryController.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Http/Controllers/OrderController.php`
- `app/Http/Middleware/ReconcileOrderTimestamps.php`
- `app/Http/Requests/StoreOrderRequest.php`
- `app/Http/Requests/UpdateOrderRequest.php`
- `app/Models/Order.php`
- `app/Services/CustomerNotificationDispatcher.php`
- `app/Services/DeliveryAttentionService.php`
- `app/Services/DeliveryTiming.php`
- `app/Services/NotificationService.php`
- `app/Services/OrderService.php`
- `app/Services/ReportAnalytics.php`
- `app/Services/Settings.php`
- `app/Services/StatsService.php`
- `database/migrations/2026_09_11_000001_delivery_attention_workflow.php`
- `database/seeders/DatabaseSeeder.php`
- `database/seeders/WorkflowStatusSeeder.php`
- `docs/order-workflow.md`
- `resources/views/dashboard.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/orders/index.blade.php`
- `resources/views/orders/item-editor.blade.php`
- `resources/views/settings/index.blade.php`
- `routes/console.php`
- `routes/web.php`
- `scripts/install-delivery-task.ps1`
- `tests/Browser/order-status-workflow.cjs`
- `tests/Feature/DeliveryAttentionMigrationTest.php`
- `tests/Feature/OrderWorkflowMigrationTest.php`
- `tests/Integration/MixedGarmentOrdersTest.php`
- `tests/Integration/OfficialNotificationTest.php`
- `tests/Integration/OrderStatusWorkflowTest.php`

Operational details: [order-workflow.md](order-workflow.md).
