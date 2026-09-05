# Cloth Store — Production Audit

Run first:

```bash
php artisan migrate
php artisan optimize:clear
```

---

## Critical — these broke real transactions

### 1. Quick-add customer wrote to a column that does not exist
`cs_customers` was only ever `name / phone / due_balance / total_purchases /
last_purchase_date`. But `storeQuick()` wrote `city`, and the Customers table
rendered `$c->city`. On a database built from the migrations, **creating a
customer at the till fails with `Unknown column 'city'`** — the cashier can
never add a walk-in.

Added a migration for `city`, `email`, `address`, `notes`, plus an index on
`phone` (the column both customer search and the POS lookup filter on).

### 2. Checkout wrote `reference_number` to tables that define `reference`
`cs_customer_ledgers` and `cs_customer_payments` both define **`reference`**.
Only `cs_supplier_payments` has `reference_number`. Checkout was writing
`reference_number` to all three inserts. `PaymentController` already used the
correct name, which is what made the inconsistency visible. Fixed.

### 3. Return approval used an invalid enum value
`cs_stock_transactions.type` is `ENUM('in','out','adjustment','transfer')`.
The return approval wrote `'Stock In'` and `'Stock Out'` — not valid members,
so **approving a return threw and rolled back the whole thing.**

### 4. Refunds never moved any money
The same method wrote a ledger row using `transaction_type` and `amount` —
neither column exists on `cs_customer_ledgers` — and **never touched
`due_balance` at all**. So even if the insert had worked, a refund changed
nothing the customer owed. Rewritten to credit the ledger properly and adjust
both `due_balance` and `total_purchases`.

### 5. Two routes pointed at methods that do not exist
- `purchase-orders` resource registers `show()`; the controller only had
  `getPO()` — so **the "Receive Stock" drawer never opened.**
- `returns` resource registers `show()` and `update()`; neither existed.

---

## Security — the server was trusting the browser with money

`CheckoutController@store` accepted `unit_price`, `subtotal` and
`total_amount` from the request and wrote them straight to the order. A
crafted POST could buy a Rs 6,500 product for Rs 1, or book a Rs 0 total
against real stock that then left the building.

The server now computes everything itself:

- unit price comes from the **product row**, never the request
- subtotal is summed from those prices
- discount is **capped at the subtotal** (it could previously push the total negative)
- amount paid is capped at the total (overpayment is change, not credit)
- payment method is whitelisted
- inactive products are refused

Returns had the same shape of hole: `refund_amount` came from the client, and
nothing checked the line actually belonged to that order. Refunds are now
derived from the price charged, lines are verified against the invoice, and
you cannot return more than was sold (net of earlier returns).

---

## Correctness

| Fix | Effect |
|---|---|
| Dashboard excluded cancelled orders | Cancelling a sale left its value in takings, profit, metres and transaction count |
| Order status on partial return | A partial return marked the whole invoice `Returned`, so reports counted the full amount as refunded |
| Exchange stock guard | An exchange could drive stock negative |
| Payment overpayment guard | Collecting more than owed silently made `due_balance` negative |
| `lockForUpdate` on customer during payment | Two tills collecting at once lost one payment from the running balance |
| `total_purchases` / `last_purchase_date` | Never updated on a sale, so "recent customers" and lifetime value were always stale |
| Customer/order/payment linkage | Checkout payments now carry `cs_order_id` and `payment_type = 'Sale Payment'` |

### Invoice and return numbers
Invoices were `uniqid()` — `INV-6A81BAF1A2397`, which nobody can read out over
a counter. Return numbers were `RTN-{date}-rand(1000,9999)` on a **unique**
column; by the birthday paradox that collides at roughly 40% once you hit ~100
returns in a day, and the insert then fails. Both are now derived from the
primary key: `INV-000042`, `RTN-000042`.

---

## Missing functionality

**Customers had no CRUD at all** — only list, ledger and the POS quick-add.
No create, edit or delete existed in the controller, the routes, or the page.
Added all three with the Tailor system's modal + `Atelier.api` pattern, phone
uniqueness (duplicates split one person's ledger across two accounts), and
delete guards: a customer with sales history or an outstanding balance cannot
be deleted.

Also added to the `Customer` model: `orders()` and `returns()` relations, float
casts on the money columns, and an `initials` accessor.

---

## Test the full journey

1. **Product** → create *Lawn Blue*, unit Meter, 120.50 m, Rs 850 → appears in Products **and** in Checkout
2. **Customer** → create from `/cloth-store/customers`, then again from the POS "Add Customer" (this was failing outright)
3. **Sale** → add 5.50 m → total Rs 4,675 → complete → stock 115.00 → invoice reads `INV-0000xx`
4. **Verify** → Orders shows it · Stock History has an `out` row for 5.50 · Payments shows a *Sale Payment* linked to the order · customer ledger debit + credit
5. **Payment** → collect a partial due, then try to collect more than owed → refused with the outstanding figure
6. **Purchase Order** → create, then **Receive Stock** (the drawer was broken) → stock rises, PO shows remaining
7. **Return** → return 2.5 m of an 8.5 m line → approve → stock +2.5, refund credited, order shows *Partially Returned*
8. **Dashboard** → figures move; cancel an order and confirm it **leaves** the sales total

---

## Known remaining

- Categories CRUD is correct (unique names, delete guard) but the page still
  renders from `@json` with no pagination. Low risk on a ~20-row table.
- Several `Atelier.fetch` call sites still end in `window.location.reload()`.
  They work; they just reload.
- Reports, Loyalty, Users and Settings pages keep their original markup.
- `OrderController@recordPayment` is still a stub that only returns a message.
