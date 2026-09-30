Implementation report — family customers and verification workflow

1. **Files changed**

- [app/Http/Controllers/CustomerController.php](<D:/Xamp/htdocs/test-fnal_telor/app/Http/Controllers/CustomerController.php>)
- [app/Http/Controllers/OrderController.php](<D:/Xamp/htdocs/test-fnal_telor/app/Http/Controllers/OrderController.php>)
- [app/Models/Customer.php](<D:/Xamp/htdocs/test-fnal_telor/app/Models/Customer.php>)
- [app/Services/CollectionBoard.php](<D:/Xamp/htdocs/test-fnal_telor/app/Services/CollectionBoard.php>)
- [app/Services/CollectionNotifications.php](<D:/Xamp/htdocs/test-fnal_telor/app/Services/CollectionNotifications.php>)
- [app/Services/CustomerLifecycle.php](<D:/Xamp/htdocs/test-fnal_telor/app/Services/CustomerLifecycle.php>)
- [app/Services/DeliveryTiming.php](<D:/Xamp/htdocs/test-fnal_telor/app/Services/DeliveryTiming.php>)
- [app/Services/NotificationVariables.php](<D:/Xamp/htdocs/test-fnal_telor/app/Services/NotificationVariables.php>)
- [app/Services/OrderAutoProgress.php](<D:/Xamp/htdocs/test-fnal_telor/app/Services/OrderAutoProgress.php>)
- [app/Services/OrderService.php](<D:/Xamp/htdocs/test-fnal_telor/app/Services/OrderService.php>)
- [app/Services/Settings.php](<D:/Xamp/htdocs/test-fnal_telor/app/Services/Settings.php>)
- [app/Services/SmsService.php](<D:/Xamp/htdocs/test-fnal_telor/app/Services/SmsService.php>)
- [database/migrations/2026_09_30_000001_link_customer_family_members.php](<D:/Xamp/htdocs/test-fnal_telor/database/migrations/2026_09_30_000001_link_customer_family_members.php>)
- [docs/family-ready-workflow.md](<D:/Xamp/htdocs/test-fnal_telor/docs/family-ready-workflow.md>)
- [resources/views/components/family-member-form.blade.php](<D:/Xamp/htdocs/test-fnal_telor/resources/views/components/family-member-form.blade.php>)
- [resources/views/customers/index.blade.php](<D:/Xamp/htdocs/test-fnal_telor/resources/views/customers/index.blade.php>)
- [resources/views/orders/index.blade.php](<D:/Xamp/htdocs/test-fnal_telor/resources/views/orders/index.blade.php>)
- [resources/views/orders/item-editor.blade.php](<D:/Xamp/htdocs/test-fnal_telor/resources/views/orders/item-editor.blade.php>)
- [resources/views/settings/index.blade.php](<D:/Xamp/htdocs/test-fnal_telor/resources/views/settings/index.blade.php>)
- [tests/Browser/family-ready-workflow.cjs](<D:/Xamp/htdocs/test-fnal_telor/tests/Browser/family-ready-workflow.cjs>)
- [tests/Browser/saved-measurement-selection.cjs](<D:/Xamp/htdocs/test-fnal_telor/tests/Browser/saved-measurement-selection.cjs>)
- [tests/Feature/CollectionWorkflowTest.php](<D:/Xamp/htdocs/test-fnal_telor/tests/Feature/CollectionWorkflowTest.php>)
- [tests/Feature/CustomerLifecycleTest.php](<D:/Xamp/htdocs/test-fnal_telor/tests/Feature/CustomerLifecycleTest.php>)
- [tests/Feature/OrderAutoProgressTest.php](<D:/Xamp/htdocs/test-fnal_telor/tests/Feature/OrderAutoProgressTest.php>)
- [tests/Integration/MixedGarmentOrdersTest.php](<D:/Xamp/htdocs/test-fnal_telor/tests/Integration/MixedGarmentOrdersTest.php>)

2. **Forward migration**

`2026_09_30_000001_link_customer_family_members.php` adds nullable `parent_customer_id` (self-reference, restricted deletion), nullable `relationship`, and nullable `phone`. Existing customers remain unlinked. Existing phone uniqueness is retained. Applied successfully to the opened application's database and the isolated MySQL test database. No old migration was edited. Rollback refuses to discard family links or invent replacement phone values.

3. **Setting**

Added `verification_before_days`, default **2**, under the existing collection settings group, exposed as “Verification Before Delivery (days).” The existing `delivery_alert_before_days` remains unchanged because it controls dashboard/upcoming alerts, a different function.

4. **Customer model**

Added `primaryCustomer()`, `familyMembers()`, `isFamilyMember()`, `effectiveContact()`, `effectivePhone()`, and the six relationship options. Links are established at creation and cannot be re-parented into nested families. Blank member phones have NULL phone keys, preserving uniqueness for real phone numbers.

5. **Family behavior**

Customers now have a compact Add Family Member action and member summary. The shared form asks only name, relationship, and optional personal phone. Members remain real customers with separate order, measurement, payment, and ledger ownership. Search includes inherited contact names/numbers. The existing edit form permits a member's phone to be blank or updated later.

6. **Notification contact**

SMS uses the member's own phone when present, otherwise the primary customer's phone. Collection grouping, phone locks, cooldowns, reminders, order templates, and manual customer SMS use the centralized resolver. The stored member phone is never populated with the primary phone. `contactName` and `relationship` are available as template variables; `customerName` remains the order owner. Fallback order messages explicitly name the owner and order number, while normal-customer template output stays unchanged.

7. **Verification timing**

Automatic verification uses the promised delivery datetime minus the configured days in the shop timezone. Earlier workshop milestones retain their percentage logic, capped at the verification boundary. A promise already inside the window becomes eligible immediately, without history dated before booking. Ready/closed statuses are excluded, and repeated runs do not duplicate history. The existing minute scheduler and page triggers are reused. Automatic progress never sends SMS.

8. **Orders interface**

Table and card views offer READY & SEND SMS directly at verification, and FINISHED — READY & SEND SMS while stitching. There is no confirmation modal. Buttons disable during the request; success updates the local order and existing counters/toasts. Due in N Days, Due Tomorrow, Due Today, overdue urgency, SMS failure, and family contact context are displayed. Order For selects the actual customer ID. Quick add selects the new member without losing date, garment, pricing, advance, or order notes; changing people clears the previous person's measurement selection.

9. **Measurements and Special Instructions**

Saved-measurement eager loading now includes `notes`; edited-order measurement payloads also retain notes. Existing saved-set filtering and backend ownership validation remain in use. Tests verify separate chest measurements, member-only saved sets, note loading, and rejection of another customer's saved measurement. No parallel measurement or notes storage was introduced.

10. **SMS outcome and safety**

The existing markReady → prepareForCollection → CollectionNotifications pipeline remains responsible for sending, logging, linking orders to SMS, and finalizing Ready. Accepted SMS promotes the order; failures retain verification and show the provider error. Repeated Ready actions cannot become reminders, even after the reminder interval. Existing reminder actions remain available. Pending/unknown order attempts remain blocked even if the member's contact changes. Provider outcomes were tested with HTTP fakes; no live SMS was sent for verification.

11. **Checks performed**

- Standard PHP suite: **100 passed, 1,095 assertions**; 116 MySQL-only tests skipped in this invocation.
- Selected isolated MySQL order/ledger integration checks: **26 passed, 234 assertions**, including the new family ownership/measurements/notes/receipt/balance test.
- Three DOM-based frontend checks passed: family/ready workflow, customer lifecycle, and saved measurements/notes.
- All six inline scripts in the rendered Orders page parsed successfully.
- Changed PHP files passed syntax checks; Blade views compiled; git diff whitespace check passed.
- Migration exercised on SQLite test fixtures and MySQL, then applied locally.

The broader legacy MySQL run was not fully green: 41 passed and 13 failed. Five underlying failures were reproduced using the original HEAD application classes: two receipt wording assertions, a garment/profile validation expectation, a ready/SMS call inside an open transaction, and an obsolete notification/reminder expectation. The remaining eight failures followed from that notification test leaving a committed duplicate-phone fixture. These unrelated behaviors were not changed to make old tests pass. Diagnostic logs are retained under `storage/app/family-*`.

12. **Compatibility and lifecycle decisions**

Orders, receipts, and financial records retain their original customer ownership; family balances are not merged. Receipt layout and existing routes are unchanged. Archive members before archiving their primary; restore the primary before restoring members. A primary with any linked members, including archived members, cannot be permanently removed. Historical member records follow the existing anonymization policy. The new migration must precede using this code on another installation. The pre-existing modification to `storage/framework/delivery-background-status.json` was left alone.
