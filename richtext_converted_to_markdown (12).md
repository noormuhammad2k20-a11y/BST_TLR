TASK: EXTEND ONLY THE EXISTING TAILOR/STAFF WAGE RATE SYSTEM.

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

IMPORTANT:

The current application is already working.

DO NOT redesign anything.

DO NOT refactor unrelated functionality.

DO NOT change the existing application theme.

DO NOT change customer pricing.

DO NOT change receipts.

DO NOT change Cloth Store.

DO NOT change customer ledger/payments.

DO NOT change order workflow/status/SMS/delivery.

ONLY add the missing tailor wage-rate functionality described below.

\==================================================

FIRST: STUDY THE CURRENT IMPLEMENTATION

\==================================================

Deeply inspect these existing files before changing anything:

resources/views/staff/index.blade.php

app/Http/Controllers/StaffController.php

app/Models/Staff.php

app/Models/StaffWorkLog.php

app/Models/StaffPayment.php

app/Services/StaffPayroll.php

app/Services/StaffPayPeriod.php

app/Services/OrderService.php

app/Services/OrderItemsService.php

app/Http/Controllers/OrderController.php

app/Http/Requests/StoreOrderRequest.php

app/Http/Requests/UpdateOrderRequest.php

app/Models/Order.php

app/Models/OrderItem.php

app/Models/ProductService.php

database/migrations/2026\_08\_19\_000001\_create\_staff\_tables.php

database/migrations/2026\_09\_11\_000002\_staff\_payment\_periods.php

database/migrations/2026\_09\_09\_000002\_add\_relational\_order\_items.php

routes/web.php

and all existing Staff / Order / Payroll tests.

DO NOT implement anything until you understand the current flow.

\==================================================

CURRENT WORKING BEHAVIOR — PRESERVE IT

\==================================================

The current system already correctly has:

\- Tailor/Staff directory

\- Master Tailor / Tailor roles

\- Monthly / Per Suit / Both salary types

\- Monthly Salary

\- Default Per-Suit Stitching Rate

\- Active/Inactive tailors

\- Tailor assignment to Orders

\- This Week pieces

\- This Month pieces

\- All-Time pieces

\- Assigned Orders

\- Stitching History

\- Salary History

\- Manual "Record Stitching"

\- Automatic work credit when an assigned order is completed/Ready

\- StaffWorkLog historical rate snapshots

\- salary payments

\- payment reversal/history

\- customer money completely separate from staff wages

\- duplicate work-credit protection

KEEP ALL OF THIS.

\==================================================

CURRENT LIMITATION

\==================================================

Currently OrderService::recordStaffWork() effectively does:

quantity × staff.per\_suit\_rate

for the completed order.

That means every garment gets the same tailor wage rate.

That is NOT enough for the real shop.

Example:

Irfan default rate:

Rs.500 per suit

But sometimes:

Wash & Wear = Rs.500

Cotton = Rs.550

Boski = Rs.700

Sherwani = Rs.1,500

Special / difficult piece = custom Rs.2,000

The Tailor wage MUST support this without changing what the CUSTOMER is charged.

CUSTOMER PRICE and TAILOR WAGE are two completely separate amounts.

\==================================================

REQUIRED RATE PRIORITY

\==================================================

Implement this exact priority:

1\. ORDER ITEM CUSTOM TAILOR RATE

↓ if empty

2\. THIS TAILOR'S SPECIAL RATE FOR THAT SERVICE/GARMENT

↓ if none exists

3\. THIS TAILOR'S DEFAULT PER-SUIT RATE

Conceptually:

Custom Order Rate

\> Tailor Special Garment Rate

\> Tailor Default Per-Suit Rate

\==================================================

1\. KEEP DEFAULT PER-SUIT RATE

\==================================================

Do NOT remove:

staff.per\_suit\_rate

It remains the fallback/default rate.

Example:

Irfan Malik

Default Per-Suit Rate = Rs.500

If there is no special rate and no order override:

1 suit × Rs.500 = Rs.500 earned.

\==================================================

2\. ADD OPTIONAL SPECIAL RATES PER TAILOR

\==================================================

Each tailor should optionally have different wage rates for different active tailoring services.

Use the existing tailoring service identity / ProductService IDs.

DO NOT use loose text matching if a product\_service\_id exists.

Create the smallest safe new structure, conceptually something like:

staff\_service\_rates

\- id

\- staff\_id

\- product\_service\_id

\- rate

\- timestamps

Unique:

staff\_id + product\_service\_id

Use an appropriate safe migration.

DO NOT edit historical migrations.

DO NOT delete any existing data.

Example:

Irfan Malik

Default Rate:

Rs.500

Special Rates:

Wash & Wear Rs.500

Cotton Rs.550

Boski Rs.700

If another active tailoring service exists later, it should also be supportable.

DO NOT hardcode only these three names into the database architecture.

Use the active existing tailoring/service catalogue.

\==================================================

STAFF FORM UI

\==================================================

Page:

/staff

Keep the existing Add/Edit Tailor modal design EXACTLY as it is.

Same:

\- modal

\- spacing

\- typography

\- colors

\- inputs

\- buttons

\- responsive layout

\- theme

Only extend it.

For Salary Type:

Per Suit

or

Both

show:

DEFAULT PER-SUIT STITCHING RATE

and underneath add a compact optional section:

SPECIAL STITCHING RATES

Example:

Wash & Wear \[ Rs. 500 \]

Cotton \[ Rs. 550 \]

Boski \[ Rs. 700 \]

Blank special rate means:

USE DEFAULT RATE.

Do NOT make this a completely new page.

Do NOT redesign the Staff modal.

For Monthly-only staff:

special per-suit rates do not need to affect salary calculations.

\==================================================

VERY IMPORTANT:

CUSTOMER STITCHING RATE != TAILOR RATE

\==================================================

The current Products / Stitching Rates system contains CUSTOMER prices.

Example:

Customer pays:

Wash & Wear stitching = Rs.2,500

That does NOT mean tailor earns Rs.2,500.

Tailor may earn:

Rs.500.

Never overwrite:

order\_items.unit\_price

customer invoice price

order total

customer balance

receipt amounts

with tailor wage rates.

These two financial systems must remain completely separate.

\==================================================

3\. ADD OPTIONAL ORDER-SPECIFIC TAILOR RATE

\==================================================

Sometimes even the normal special rate is not enough.

Example:

Boski standard tailor rate:

Rs.700

But one difficult/custom piece:

Rs.1,000

Support an optional:

Tailor Rate Override

on the ORDER GARMENT ROW.

Do this minimally inside the existing order Create/Edit UI.

Do NOT redesign the order wizard.

For every garment/order item, allow:

Tailor Rate Override

(optional)

Blank means:

automatic rate resolution.

Entered amount means:

use this rate for this specific order item only.

This field is the TAILOR'S WAGE RATE.

It must NOT modify:

\- customer unit price

\- subtotal

\- invoice

\- order total

\- receipt

\- customer ledger

Prefer a nullable column on order\_items such as:

tailor\_rate\_override

or another clear safe name.

DO NOT misuse \`unit\_price\`.

\==================================================

4\. MULTIPLE GARMENTS IN ONE ORDER MUST WORK

\==================================================

This is critical.

The application already supports multiple garment rows.

Example one order contains:

2 × Wash & Wear

1 × Boski

Irfan's rates:

Wash & Wear = Rs.500

Boski = Rs.700

Tailor earning must be:

2 × 500 = Rs.1,000

1 × 700 = Rs.700

TOTAL TAILOR EARNING:

Rs.1,700

DO NOT calculate:

3 × one single rate.

Each actual garment/service line must resolve its own tailor rate.

\==================================================

5\. AUTOMATIC COMPLETION CREDIT

\==================================================

KEEP the current automatic work-credit behavior.

When the order reaches the existing qualifying completion state, continue automatically creating the staff work credit.

DO NOT change:

\- Ready workflow

\- SMS requirement

\- Delivered flow

\- Completed flow

Only change the wage calculation.

For every stitchable line:

resolved rate =

custom override

OR staff special service rate

OR staff default per\_suit\_rate

Then:

line amount = quantity × resolved rate

Total StaffWorkLog amount =

sum of all stitchable line amounts.

Continue excluding physical/non-service items exactly as the existing implementation does.

\==================================================

6\. PRESERVE HISTORICAL RATE SNAPSHOTS

\==================================================

This is VERY IMPORTANT.

Once stitching is completed:

the exact rate used must be permanently stored.

If tomorrow:

Default rate changes

or

Special rate changes

OLD WORK MUST NOT CHANGE.

Current StaffWorkLog already snapshots rate and amount.

Preserve that principle.

Because a mixed order may now contain multiple rates, safely extend the work log to preserve the breakdown.

Preferred minimal approach:

add a nullable JSON field such as:

rate\_breakdown

to staff\_work\_logs.

Example:

\[

{

product\_service\_id: 1,

garment: "Wash & Wear",

quantity: 2,

rate: 500,

amount: 1000,

source: "special"

},

{

product\_service\_id: 3,

garment: "Boski",

quantity: 1,

rate: 700,

amount: 700,

source: "special"

}

\]

Possible \`source\` values:

custom

special

default

The StaffWorkLog's total \`amount\` should still equal the sum.

Do NOT recalculate old logs from current rates.

\==================================================

7\. KEEP DOUBLE-COUNTING PROTECTION

\==================================================

Current system intentionally allows only one automatic work record per order.

DO NOT break this protection.

Moving an order:

Ready

→ Delivered

→ Completed

must NOT credit the tailor again.

Keep the existing order-level idempotency / unique protection.

If using rate\_breakdown JSON, keep the current one-summary-log-per-order architecture.

Do NOT create duplicate wage records for the same order.

\==================================================

8\. STITCHING HISTORY

\==================================================

Keep the existing Tailor Profile:

Stitching History

same design.

Current columns like:

Date

Order

Garment

Qty

Rate

Amount

must remain.

For a work log containing ONE rate:

continue showing the exact rate normally.

For a mixed-rate order:

do NOT display a fake rate.

Show something small such as:

Mixed

in the Rate column.

Allow the existing row/details area or a small same-theme detail line to show:

2 × Wash & Wear @ Rs.500

1 × Boski @ Rs.700

Total Rs.1,700

Do NOT redesign the table.

\==================================================

9\. MANUAL "RECORD STITCHING"

\==================================================

DO NOT break the existing manual Record Stitching function.

It is already useful for:

alterations

repairs

counter jobs

work outside an order

Keep:

Garment

Pieces

Rate per piece

Completed On

Notes

and existing amount preview.

Manual rate should continue to be explicitly entered/defaulted.

\==================================================

10\. STAFF ADVANCE — SEPARATE FROM SALARY PAYMENT

\==================================================

Real shop behavior:

A tailor may take money before completing enough stitching.

This must NOT be recorded as a normal salary payment.

Keep existing:

Pay Salary

and add a separate action:

Give Advance

Use the SAME existing modal/theme style.

At minimum this behavior must work correctly for:

Salary Type = Per Suit

Create a safe separate staff advance record/table rather than corrupting customer payments or earned salary records.

Conceptually:

staff\_advances

\- id

\- staff\_id

\- amount

\- method

\- given\_on

\- notes

\- recorded\_by

\- reversed\_at if appropriate

\- timestamps

Methods can reuse the existing staff payment methods:

Cash

Bank Transfer

Easypaisa

JazzCash

Cheque

DO NOT put staff advances in customer \`payments\`.

\==================================================

11\. PER-SUIT RUNNING BALANCE

\==================================================

Per-Suit tailors are NOT paid on a strict schedule.

They may come:

after 2 days

after 3 days

after 7 days

whenever they want

Therefore for:

Salary Type = Per Suit

the important calculation is a RUNNING ACCOUNT BALANCE.

Use:

TOTAL COMPLETED STITCHING EARNED

\-

TOTAL NORMAL STAFF PAYMENTS

\-

TOTAL STAFF ADVANCES

\=

NET TAILOR BALANCE

Examples:

Earned = Rs.5,000

Paid = Rs.2,000

Advance = Rs.1,000

Net Payable:

Rs.2,000

If:

Earned = Rs.1,000

Advance = Rs.2,000

Net balance:

\-Rs.1,000

Display that clearly as:

Advance Outstanding: Rs.1,000

NOT as the shop owing the tailor money.

When the tailor later completes Rs.1,500 more work:

old net = -1,000

new earned = +1,500

new shop payable =

Rs.500

No manual advance adjustment should be needed.

\==================================================

12\. PER-SUIT PAYMENT PERIOD

\==================================================

Do NOT destroy the existing Monthly / Weekly / Daily code because historical records and Monthly/Both staff may rely on it.

However:

for Salary Type = Per Suit

payment timing should NOT control earning entitlement.

A Per-Suit worker earns based on completed work.

They can receive normal payment whenever they ask.

The current strict Payment Period requirement should not force Per-Suit workers into an artificial monthly/weekly/daily settlement model.

For Per-Suit workers:

\- Pay Tailor / Pay Salary should use the current positive running payable balance.

\- Payment Period UI may be hidden/not required for new Per-Suit payments.

\- Existing historical period values must remain untouched.

For:

Monthly

Both

preserve the existing period/payroll behavior unless a tiny compatibility adjustment is absolutely required.

DO NOT rewrite Monthly/Both payroll.

\==================================================

13\. PAYMENTS VS ADVANCES

\==================================================

Rules:

NORMAL PAYMENT:

money paid against already-earned positive balance.

Do not allow normal payment greater than current positive payable.

ADVANCE:

money intentionally given even if earned balance is zero.

Advance is allowed to make net balance negative.

This distinction must remain explicit.

Do NOT silently treat overpayment as an advance.

User must choose:

Pay Tailor

or

Give Advance

\==================================================

14\. STAFF PROFILE SUMMARY

\==================================================

Keep the same Staff Profile design.

For Per-Suit workers add/use compact figures such as:

Completed Pieces

Total Earned

Payments

Advances

Current Payable

If net balance is negative show:

Advance Outstanding

Example:

Completed: 18 pcs

Earned: Rs.9,000

Paid: Rs.5,000

Advance: Rs.2,000

Current Payable: Rs.2,000

Do NOT redesign the profile.

Use the existing cards/rows/badges.

\==================================================

15\. DO NOT CHANGE PRODUCTS / STITCHING RATES PAGE

\==================================================

The current:

/products-services

page is already being used for shop/customer stitching services and prices.

DO NOT redesign or modify that page for this task.

You may READ the existing active ProductService/service IDs so staff special rates can refer to them.

But do not change customer service pricing.

\==================================================

16\. DO NOT CHANGE THESE WORKING MODULES

\==================================================

Absolutely do NOT change unrelated behavior in:

Customers

Measurements

Products / Stitching Rates UI

Customer Ledger

Customer Payments

Delivery

SMS

Notifications

Reports

Dashboard

Settings

Expenses

Cloth Store

Cloth Store inventory

Receipts

Thermal printing

Sidebar

Authentication

Theme

Responsive layout

\==================================================

17\. DESIGN LOCK

\==================================================

NO DESIGN CHANGES.

Follow the exact current design/theme.

Reuse:

existing Tailwind classes

existing modal component

Atelier.api

Atelier.confirm

Atelier.setBusy

existing toast

existing badges

existing tables

existing cards

existing typography

existing spacing

existing colors

DO NOT:

\- add a new UI library

\- change colors

\- introduce gradients

\- redesign forms

\- redesign Staff page

\- change sidebar

\- change header

\- change global CSS

Any new field or button must look like it was already part of this application.

\==================================================

18\. DATABASE SAFETY

\==================================================

Use NEW migrations only.

DO NOT edit old migrations.

DO NOT delete historical:

staff

staff\_work\_logs

staff\_payments

orders

order\_items

DO NOT rewrite old rates.

DO NOT recalculate historical wages.

DO NOT truncate anything.

Existing production data must remain valid.

\==================================================

19\. TEST THESE EXACT CASES

\==================================================

Case A — Default Rate:

Tailor default:

Rs.500

1 normal piece

Expected:

Rs.500 earned.

Case B — Special Rate:

Default:

Rs.500

Boski special:

Rs.700

2 Boski:

Expected:

Rs.1,400.

Case C — Custom Override:

Default:

Rs.500

Boski special:

Rs.700

Order override:

Rs.1,000

1 piece:

Expected:

Rs.1,000.

Case D — Mixed Order:

2 Wash & Wear @ Rs.500

1 Boski @ Rs.700

Expected:

3 pieces

Rs.1,700 earned

with correct rate breakdown.

Case E — Rate Changes Later:

Complete work at Rs.500.

Then change tailor's default to Rs.700.

Old work must remain:

Rs.500.

New work:

Rs.700.

Case F — No Double Count:

Move same order through:

Ready

Delivered

Completed

Expected:

only one work credit.

Case G — Advance Before Work:

Earned:

Rs.0

Give Advance:

Rs.2,000

Expected:

Advance Outstanding = Rs.2,000.

Case H — Work After Advance:

Advance Outstanding:

Rs.2,000

New stitching earned:

Rs.3,000

Expected:

Current Payable = Rs.1,000.

Case I — Partial Payment:

Current Payable:

Rs.4,000

Pay:

Rs.1,500

Expected:

Remaining Payable = Rs.2,500.

Case J — Existing Monthly / Both:

Run regression tests.

Existing calculations must remain unchanged.

\==================================================

20\. BEFORE EDITING

\==================================================

First report:

1\. Current Staff wage architecture.

2\. Exact place where one \`per\_suit\_rate\` is currently applied.

3\. Existing double-count protection.

4\. Existing payment/period logic.

5\. Exact database additions required.

6\. Exact files you plan to modify.

7\. Confirmation Products/Services customer prices will remain untouched.

8\. Confirmation customer financials will remain untouched.

9\. Confirmation receipts will remain untouched.

10\. Confirmation theme/design will remain untouched.

THEN implement.

\==================================================

21\. AFTER IMPLEMENTATION

\==================================================

Report:

1\. Exact files changed.

2\. Exact migrations created.

3\. Exact new tables/columns.

4\. How default rate works.

5\. How special garment rate works.

6\. How custom order override works.

7\. How mixed orders calculate tailor wages.

8\. How historical snapshots are protected.

9\. How advances work.

10\. How Per-Suit running balance is calculated.

11\. Confirmation Monthly/Both still work.

12\. Confirmation customer order price was never changed.

13\. Confirmation receipts were untouched.

14\. Confirmation Cloth Store was untouched.

15\. Tests run and results.

FINAL RULE:

DO NOT CHANGE ANYTHING EXCEPT THE MINIMUM STAFF/Tailor WAGE-RATE + ADVANCE/RUNNING-BALANCE FUNCTIONALITY REQUIRED ABOVE.