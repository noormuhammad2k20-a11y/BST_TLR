# Timestamp-based tailoring order workflow

Orders follow Received → Pending → Stitching → Ready for Verification → Ready → Delivered.
Verification and collection always require an explicit staff action. Full payment does not imply delivery.

## No scheduler or polling

There is no orders:sweep Artisan command, Windows scheduled task, queue job, cron entry, or status-polling timer.
The old Windows task and installer were removed. The application uses ordinary authenticated website requests.
Existing live counters/notification endpoints do not drive order status progression.

Before a normal page visit or user action reads orders, ReconcileOrderTimestamps applies the shop timezone and
OrderService reconciles elapsed stages from the existing created_at/status-history timestamps. Queries for
filters, reports, dashboard counts, and order details therefore see consistent persisted statuses.
A route-bound order is refreshed after reconciliation so details/actions cannot use its previous stage.

Each effective stage deadline is calculated cumulatively, not restarted when a page opens. For example, with
5/10/30-minute delays, Pending starts at creation +5 minutes, Stitching at +15 minutes, and Ready for Verification
at +45 minutes. Returning three days later records those same effective timestamps in the existing history.
Rows are locked and rechecked before writing, so concurrent requests do not duplicate transitions or staff alerts.
No extra schema, duplicate history system, or fake frontend status changes are required.

**Idle behavior:** elapsed status can be calculated without a scheduled worker, but database writes and notification
side effects happen on the next normal request. With no requests and no worker, no code executes. A page left open
updates on navigation, refresh, or an action; it does not silently poll. Verification notifications appear when the
elapsed transition is reconciled. This is the deliberate tradeoff for removing all scheduler processes.

## Timing

Settings → Workflow retains the existing units and delays. Defaults are 1 hour Received, 1 hour Pending, and
24 hours Stitching. Zero pauses a hop; disabling automatic status stops timestamp reconciliation of timed stages.
Settings changes use the current configured duration measured from the recorded stage start. Normal order edits
do not reset that start. Existing historical orders and manual stages remain intact.

## SMS and due dates

Staff manually verifies Ready for Verification → Ready. The existing pickup SMS dispatcher runs after commit.
Its atomic ready_sms_attempted_at claim prevents duplicate pickup messages from repeated saves or concurrent requests.
Missing provider configuration and send failures do not roll back Ready. An interrupted callback that has not yet
claimed the message is recovered on the next normal request. An attempted or ambiguous failed message is never
retried automatically; inspect the provider/SMS logs before using the existing manual-message feature.

Overdue starts after the promised delivery calendar date in the shop timezone. Today, future, and null dates are
not overdue. Delivered and closed legacy orders are excluded. Delivered remains final.

## Verification

With the isolated atelier_integrity_test database migrated, set INTEGRITY_MYSQL=1 and run:

```text
php vendor/bin/phpunit
```

The workflow tests cover idle catch-up with original deadlines, fresh route bindings, no polling dependency,
manual gates, duplicate histories/notifications, SMS failure isolation, and persisted dates/statuses.
The browser check is tests/Browser/order-status-workflow.cjs using the rendered integration-test fixture.
