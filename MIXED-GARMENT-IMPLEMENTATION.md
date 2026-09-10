# Mixed-garment order implementation

Implemented in the existing Laravel application and rolled out to the working database. No database reset, payment rewrite, inventory deduction or SMS-provider change was performed.

## 1. Existing architecture discovered

The five-step order wizard previously wrote a single JSON line. Order quantities, invoices and garment reporting frequently used the first line or the order-level catalogue reference. Measurements were reusable customer sheets with optional order ownership. Fabric was descriptive text. Payments and payroll already had separate ledgers.

## 2. Database changes

The additive migration adds `order_items`, `order_item_pieces`, a unique nullable measurement-to-piece reference, order billing/legacy snapshots and an edit version. Catalogue records gain canonical identity, normalized name and measurement configuration. Removed items and pieces use soft deletion. The migration is forward-only; its rollback refuses to discard history or silently remove the migration tracking entry.

## 3. Models and relationships

`Order::lineItems()` coexists with the old JSON attribute. `OrderItem` owns ordered pieces; each piece owns an independent measurement snapshot. Archived relationships remain available for history and backups. Order quantity sums all active items. The legacy fields remain available, while new writes maintain compatible JSON snapshots.

## 4. Step 2

Garment cards can be added and removed independently. Each card has its own catalogue choice, quantity, unit price, fabric and style notes. Repeated catalogue choices remain separate rows. The catalogue price supplies the initial rate. Quantity changes retain existing piece IDs, append blank pieces and confirm removal of populated trailing pieces.

## 5. Step 3

Tabs identify both the garment and the piece. Each piece can use new values, a compatible saved sheet, or a copy of another compatible piece. Saved sheets are checked against customer ownership and profile, then copied into independent records. Units and entered values remain independent. Changing a unit does not perform a conversion. Completed and incomplete pieces have separate indicators.

## 6. Measurement profiles

`MeasurementProfiles` supplies fields, labels, essential requirements and compatibility for Shalwar Kameez, Sherwani, Trouser, Waistcoat, Kurta Pajama, generic stitching, alteration and accessories. Shop-required fields apply only within the selected profile. Trouser thigh, knee and rise use the existing details JSON. Accessories need no measurements; alteration needs at least one relevant measurement. Generic services display an owner-visible configuration notice. Catalogue controls can select a profile and measurement requirement.

## 7. Step 4

Every line has its own price override. The server calculates quantity × unit price using the existing fixed-point arithmetic and applies order charges once. Submitted aggregate totals cannot override nested-item calculations. Billing snapshots keep printed totals stable when settings change. Measurement-only edits preserve the original billing snapshot and any historical rounding adjustment. Paid amounts are read-only in the item editor; repricing below recorded payments is rejected.

## 8. Step 5

Confirmation shows every garment, quantity, fabric, notes, piece completion, unit price, line amount, total, balance and delivery details. Navigation retains the item tree. A failed save leaves the editor state available for correction.

## 9. Create and update

Orders, item rows, pieces, measurements and the initial payment are written transactionally. Updates lock the order and check an edit version. Ownership checks reject foreign item, piece and saved-sheet IDs. New nested orders allow up to 50 rows, 20 pieces per row and 200 total pieces; larger historical quantities can be retained without increases. Completed/delivered orders and orders with credited work lock structural edits, while order notes and the existing payment-correction workflow remain available. Legacy flat HTTP requests enforce measurements and create a single relational item; legacy single-item updates are normalized safely. Flat edits cannot overwrite mixed orders.

## 10. Historical migration and backfill

Commands:

```text
php artisan catalogue:reconcile
php artisan catalogue:reconcile --apply
php artisan orders:backfill-items
php artisan orders:backfill-items --apply
```

The default is a dry-run. The backfill is transactional and idempotent. It retains original JSON, derives single-line historical amounts from recorded totals when unit prices are absent, records adjustments without applying current tax assumptions, and preserves unmatched sheets. Existing reusable or historical sheets are cloned, never reassigned. A single order-owned sheet is not copied into otherwise missing pieces.

Working-database rollout results:

| Verified result | Count |
|---|---:|
| Original orders unchanged | 31 |
| Original payments unchanged | 29 |
| Original payroll records unchanged | 16 |
| Original measurement records unchanged | 67 |
| Relational items | 31 |
| Relational pieces | 42 |
| Independent measurement snapshots | 23 |
| Historical pieces left blank | 19 |

All 31 orders reported “already migrated” on the follow-up dry-run. Maintenance mode was removed after successful verification. Detailed local evidence is in `dev/artifacts/mixed-rollout-results.json`; the pre-rollout snapshot is `dev/artifacts/mixed-before-rollout.json`.

## 11. Invoices and printing

Invoice payloads expose all line items and their quantities, fabric, rates and amounts. Order details include expandable garment-labelled piece measurements. Customer receipts and Printing Center/PDF-print previews list all garments. Workshop sheets show the garment, piece number and that piece’s unit. Receipt measurement lookup uses active relational pieces rather than unrelated recent customer sheets. Historical charge adjustments are explicit billing lines.

## 12. Payments and payroll

Payments remain attached to the order and the advance is recorded once. Existing payment history and correction behavior remain intact. Actual paid amounts protect repricing. New tailor credits count service quantities and exclude retail products. Existing credited work is not recomputed; repeated updates do not duplicate the credit. Fabric remains informational.

## 13. Reports and dashboard

Order counts, revenue and balances remain at order level. Garment reporting uses item quantities and distinct order IDs. Order revenue is allocated proportionally to items, with the rounding remainder assigned deterministically to the final line. Category series count item quantities. Search includes item names and fabric. Customer, delivery and staff summaries use the shared multi-item display behavior. JSON and CSV exports include archived relational history; JSON restore handles the order/measurement/piece relationship cycle and upgrades legacy orders.

## 14. SMS

`{garmentSummary}` is available in template variables, preview/rendering and sends. `{garmentType}` remains a single name for simple orders and a bounded summary for mixed orders; `{quantity}` is the total item quantity. Long SMS summaries use an “and N more pieces” suffix. Shipped English templates use the new variable; exact old shipped text upgrades during template resolution, while custom English text and activation settings are retained. Existing notification triggers remain unchanged. Tests prevent stray provider requests; no live customer SMS was sent during verification.

## 15. Duplicate catalogue entries

SKU-only seeding caused duplicate names. Canonical identity now uses normalized names, with a unique canonical key. Reconciliation counts referenced orders, including relational references, and breaks ties by lowest ID. IDs 1 and 3 remain canonical; ID 7 aliases ID 1, and ID 12 aliases ID 3. Historical references remain intact. New order selection offers 14 canonical active entries instead of 16 duplicate-containing entries. Seeding resolves canonical identity and preserves existing prices. Seeded category values are accepted by catalogue validation.

## 16. Files changed

New implementation files:

- `database/migrations/2026_09_09_000002_add_relational_order_items.php`
- `app/Models/OrderItem.php`, `OrderItemPiece.php`
- `app/Services/OrderItemsService.php`, `OrderItemsBackfill.php`, `MeasurementProfiles.php`, `CatalogueIdentity.php`
- `app/Console/Commands/BackfillOrderItems.php`, `ReconcileCatalogue.php`
- `resources/views/orders/item-editor.blade.php`
- `tests/Integration/MixedGarmentOrdersTest.php`, `tests/Browser/mixed-orders.cjs`

Existing files updated: Order/Measurement/ProductService models; Store/UpdateOrder requests; Order, Measurement, Payment, Printing and ProductService controllers; OrderService, PricingService, BackupService, ReportAnalytics, StatsService, NotificationVariables and Settings; DatabaseSeeder; order, catalogue and printing-center views. Earlier English-SMS and SMS-only changes were preserved.

## 17. Tests added

Fifteen integration tests cover mixed pricing, independent measurements, accessories, HTTP create/edit, saved-sheet ownership, piece archiving, forged IDs, stale edits, paid/completed locks, required fields, legacy backfill, duplicate rows, stable billing, missing historical pieces, backup round trips, catalogue idempotency, payroll quantities, SMS summaries, limits and flat-request compatibility. The browser script exercises the rendered wizard with mocked network requests, avoiding working-database or customer-notification writes.

## 18. Executed verification

- Full Laravel suite: **99 tests, 680 assertions passed**.
- Expanded mixed-order suite: 15 tests, 91 assertions passed.
- Final HTTP/editor/invoice/receipt/printing check: 1 test, 15 assertions passed after the receipt billing-line update.
- Headless Edge: create, mixed rows, piece copy, independent units, shrink/grow, back navigation, confirmation and edit IDs passed with no browser errors.
- Rendered inline JavaScript: 78 scripts passed syntax checks, including the final order and Printing Center renders.
- PHP lint, Blade compilation, order route listing and whitespace checks passed.
- Vite production build passed.
- Working-database financial/history comparison and idempotency verification passed.

## 19. Remaining operational edge cases

Historical order IDs **1, 2, 3, 4, 5, 6, 12, 13, 29, 30, 32, 33, 35, 36, 38 and 39** contain the 19 pieces without unambiguous sheets. They were intentionally left blank, and unchanged historical measurements remain editable without retroactive completeness requirements. Add real measurements through the order editor when available; completed orders retain their structural locks.

Other named stitching services initially use the generic profile and can be configured in the catalogue. A catalogue quantity represents one measurable piece/bundle; a three-piece suit does not automatically become three priced rows. Unit conversion is manual. Physical printer output and live SMS-provider delivery were not tested; browser rendering and mocked provider tests were used. No external hosting deployment was performed; rollout was to this existing local working application.
