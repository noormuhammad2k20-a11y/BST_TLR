# Cloth Store — Phase 1: Data Integrity & Access

## What the audit actually found

The Cloth Store is **not** a static prototype. It already has real controllers,
Eloquent models, migrations, DB transactions, AJAX CRUD via the shared `Atelier`
runtime, and server-side pagination/filtering in 9 of its controllers. There
were **no hard-coded demo arrays** anywhere — every page already reads the
database.

The real problems were narrower and more serious than "it's static":

| # | Defect | Impact |
|---|--------|--------|
| 1 | 7 quantity columns still `INTEGER` in MySQL | Every fractional metre silently rounded |
| 2 | `Product` + `StockTransaction` cast quantities to `'integer'` | 120.50 m read back as 120 m |
| 3 | `ProductController` validated `stock_quantity` as `integer` | Opening stock of 120.50 rejected outright |
| 4 | `<input type="number">` with no `step` | Browser refused decimals before the request was even sent |
| 5 | POS, orders and returns registered **twice** — second copy inside `role:admin` | Cashiers got 403 on checkout; POS unusable for non-admins |
| 6 | Sale deducted stock but wrote no `cs_stock_transactions` row | No audit trail; Stock History could not explain missing metres |
| 7 | Goods receipt had no over-receipt guard | Could receive 200 m against a 100 m PO |

Defects 1–4 compound: four separate layers each independently destroyed the
decimal, which is why TEST 1 and TEST 3 could never have passed.

---

## Files changed

**New**
- `database/migrations/2026_08_16_120000_convert_cs_quantity_columns_to_decimal.php`

**Modified**
- `app/Models/ClothStore/Product.php` — casts → `float`; `getAvailableStockAttribute` `int` → `float`; alert-level banding no longer `floor()`s the critical threshold to zero
- `app/Models/ClothStore/StockTransaction.php` — `quantity`, `previous_qty`, `new_qty` → `float`
- `app/Models/ClothStore/ProductLocation.php` — added `quantity` → `float`
- `app/Http/Controllers/ClothStore/ProductController.php` — extracted shared `validated()` helper (mirrors Tailor's `CustomerController`); `integer` → `numeric|decimal:0,2`; SKU uniqueness now uses `Rule::unique()->ignore()`
- `app/Http/Controllers/ClothStore/StockController.php` — `integer|min:1` → `numeric|min:0.01|decimal:0,2`; `(int)` → `round((float) …, 2)`
- `app/Http/Controllers/ClothStore/CheckoutController.php` — writes `StockTransaction` per line; decrements `ProductLocation`; rounds all money/metre maths
- `app/Http/Controllers/ClothStore/PurchaseOrderController.php` — over-receipt guard; `lockForUpdate()` on PO item and product; writes `StockTransaction` on goods receipt
- `routes/web.php` — POS/orders/returns moved out of the admin-only group; duplicate registrations removed
- `resources/views/cloth-store/products/index.blade.php` — `step="0.01"` on price/cost/stock/threshold; stock label shows unit and preserves decimals; numeric coercion before comparison
- `resources/views/cloth-store/checkout/index.blade.php` — discount input `step="1"` → `step="0.01"`

### One design note
Quantities are cast to `float`, **not** `decimal:2`. Laravel's `decimal` cast
returns a *string*, which JSON-encodes as `"9.00"`. The product grid compares
`stock <= threshold` in JavaScript, and `"9.00" <= "10.00"` is a *lexicographic*
comparison that evaluates to `false` — low-stock alerts would have stopped
firing for any single-digit stock level. The `decimal(12,2)` columns remain the
source of truth for precision.

---

## Run these

```bash
php artisan migrate
php artisan optimize:clear
```

Then confirm the schema actually changed:

```bash
php artisan tinker --execute="dd(collect(DB::select('DESCRIBE cs_stock_transactions'))->pluck('Type','Field'));"
```

Expect `quantity`, `previous_qty`, `new_qty` to read `decimal(12,2)`.

---

## Test checklist

### TEST 1 — decimal opening stock
Products → Add Product → Lawn Blue, unit **Meter**, stock **120.50**, price **850**.
- [ ] Save succeeds (previously rejected by the `integer` rule)
- [ ] Card reads `120.50 meter in stock` — **not** 120 or 121
- [ ] `SELECT stock_quantity FROM cs_products WHERE name='Lawn Blue'` → `120.50`

### TEST 3 — POS sale of 5.50 m
Smart Checkout → search Lawn Blue → qty **5.50**.
- [ ] Line total shows **4,675.00** (5.50 × 850)
- [ ] Sale completes with no full page reload
- [ ] Stock becomes **115.00** m
- [ ] A `cs_stock_transactions` row exists: `type=out, quantity=5.50, previous_qty=120.50, new_qty=115.00, reason=Sale`
- [ ] Customer ledger shows the sale debit

### TEST 5 — partial goods receipt
PO for 100 m, receive **65.5** m.
- [ ] Inventory rises by exactly 65.5
- [ ] PO shows 34.5 outstanding, status **Partially Received**
- [ ] `cs_stock_transactions` row: `type=in, quantity=65.50, reason=Purchase Received`
- [ ] Attempting to receive 40 m more is **rejected** (only 34.5 outstanding)

### TEST 6 — return 2.5 m
- [ ] Stock rises by exactly 2.5
- [ ] Audit row records `2.50`, not `2` or `3`

### Access regression
- [ ] Log in as a **non-admin** user → Smart Checkout loads and a sale completes (was a 403)
- [ ] `php artisan route:list --path=cloth-store` shows each route name **once**

---

---
---

# Phase 2 — Server-driven lists & database-backed POS

## What was wrong

Two pages were still doing the work in the browser:

- **Products** shipped the entire table via `@json($products)` and had no
  search, filters or pagination at all.
- **Smart Checkout** rendered every active product and every customer, then
  "searched" by setting `display:none` on cards. It could only ever find a
  product that happened to be in the initial dump, and there was no barcode
  column to search against.

And a subtler one: every list page's filter bar was a plain `<form>` GET
submit. The SPA router only intercepts **link** clicks, so filtering and
searching triggered full page reloads even though pagination links did not.

## What changed

**New**
- `database/migrations/2026_08_16_130000_add_barcode_to_cs_products_table.php`
- `resources/views/cloth-store/products/partials/card.blade.php`
- `resources/views/cloth-store/checkout/partials/product-cards.blade.php`

**Modified**
- `cloth-store/layouts/app.blade.php` — new shared filter-form engine. Any
  `<form data-filter-form>` now debounces typing (400ms), applies selects
  instantly, strips empty params, resets to page 1 on a filter change, and
  routes through the SPA router instead of reloading. Caret and focus are
  restored after the swap, so the search box doesn't drop focus mid-word.
- `ProductController` — real server-side search (name/SKU/barcode), filters
  (category, stock level, unit, status, price range), whitelisted sorting,
  `paginate(12)->withQueryString()`. Delete now refuses to remove a product
  that appears on past sales.
- `products/index.blade.php` — server-rendered grid, filter bar, pagination.
  No longer ships the product table to the browser.
- `CheckoutController` — `searchProducts`, `scan` and `searchCustomers`
  endpoints; initial load capped at 24 products / 50 customers.
- `checkout/index.blade.php` — debounced DB-backed product and customer
  search, real barcode scanning, request cancellation, rounding guard on
  submit.
- `routes/web.php` — three POS lookup routes.

### Notable fixes found while wiring this up
- **Deleting a product destroyed sales history.** `cs_order_items` is
  `ON DELETE CASCADE`, so removing a product silently deleted its line items
  from past invoices — and every revenue and profit figure derived from them.
  Now blocked, with a suggestion to set the product Inactive instead.
- **Empty customer search crashed the till.** The change handler read
  `options[selectedIndex]` with no guard; an empty result set made that
  `options[-1]`, throwing and killing every later listener on the page.
- **Float drift vs the new `decimal:0,2` rule.** `5.5 * 850` is exact, but
  other combinations produce `…000001`, which the server would have rejected
  for a perfectly valid sale. The payload is now rounded to 2dp client-side.

---

## Run these

```bash
php artisan migrate
php artisan optimize:clear
```

## Phase 2 test checklist

### Products page
- [ ] Type in Search — results update **without a page reload**, and the caret
      stays in the box
- [ ] Search by SKU, then by barcode — both match
- [ ] Category / Stock / Unit / Status / Sort each apply instantly
- [ ] Changing a filter while on page 2 returns you to page 1
- [ ] Pagination works and keeps the active filters in the URL
- [ ] Add a product via the modal — the grid refreshes in place
- [ ] Try deleting a product that has been sold — refused with a clear message
- [ ] View source: the product table is **not** embedded in the page

### Smart Checkout
- [ ] Search matches a product that is **not** in the first 24 loaded
- [ ] Category and unit filters re-query rather than hiding cards
- [ ] Type a barcode and press Enter — that exact product is added to the cart
- [ ] Search a customer beyond the first 50 — they appear and can be selected
- [ ] Search a customer with no matches — the page does **not** break, and
      completing a sale is refused with "Select a customer"
- [ ] Complete a 5.50 m sale end to end (re-run TEST 3 above)

---

---
---

# Phase 3 — Broken calls, broken routes, and the rest of the lists

## The two findings that mattered most

**`Atelier.fetch` was never defined.** Six pages call it, 16 times — and the
Cloth Store runtime only ever exported `Atelier.api.*`. Every one of those
calls threw `TypeError: Atelier.fetch is not a function`, which silently
broke:

- recording and reversing a customer payment (**TEST 4 could not pass**)
- creating and receiving a purchase order (**TEST 5 could not pass**)
- saving, deleting and approving an expense
- viewing a supplier profile and recording a supplier payment
- saving Cloth Store settings

No backend fix would have made those tests pass. Added `Atelier.fetch` as a
thin CSRF/JSON wrapper that returns the raw `Response`, matching how the
existing call sites are written (`.then(res => res.json())`).

**Three route names were missing the `cloth-store.` prefix**, throwing
`RouteNotFoundException` — a hard 500 on the page:

- `route('suppliers.index')` and `route('suppliers.ledger')` in the supplier ledger
- `route('loyalty.adjust')` in the loyalty page

## Also fixed

- **Order status changes** were a form POST returning `back()` — a full reload,
  with no transaction, no audit rows, and a comment admitting the un-cancel
  path was "naive: assume they have stock". It now runs in a transaction,
  writes `cs_stock_transactions` in both directions, refuses to reinstate an
  order when the stock has since been sold, and responds as JSON.
- **Five more client-side filters removed** (suppliers, stock, discounts,
  returns, expenses). Each hid rendered rows, so none could find a record
  outside the current page. All five now query the database.
- **Suppliers and Stock had no pagination**, loading every row. Both now
  paginate; supplier KPIs moved to aggregate queries so paginating the list
  doesn't shrink the reported payables.
- **Customers had no search at all.** Added name/phone search and an
  "only with dues" filter.
- **Product stock edits** now write an `adjustment` row, and creating a product
  with opening stock writes an `in` row — so Stock History no longer begins
  with an unexplained balance.
- Every remaining filter form converted to `data-filter-form`; the orders page
  was also missing `@section('spaPage')` entirely.
- The filter engine now treats checkboxes and radios as instant, not debounced.

## Phase 3 test checklist

- [ ] Open the **supplier ledger** and the **loyalty** page — neither 500s
- [ ] Record a **customer payment** (TEST 4: 8,500 invoice, pay 5,000 → due
      3,500, then 2,000 → due 1,500)
- [ ] Create and **receive a purchase order** (TEST 5)
- [ ] Save an **expense**, then delete one
- [ ] Save **Cloth Store settings**
- [ ] Change an **order status** to Cancelled — stock returns, audit row written,
      no page reload
- [ ] Try reinstating that order after selling the stock — refused with a clear
      message rather than driving stock negative
- [ ] Search on **suppliers, stock, customers, discounts, returns, expenses** —
      each finds records beyond the first page, with no reload
- [ ] Product **Stock History** shows Opening Stock, Sale, and Manual Correction
      rows with correct decimals

## Still open after Phase 3

- `Atelier.fetch` call sites mostly end in `window.location.reload()`. They now
  work, but they reload.
- Categories page still renders from `@json` with no pagination.
- Stock **alerts** page still filters rows client-side.
- Dashboard statistics still need review.
- `OrderController@recordPayment` is a stub that only returns a message.

---
---

# Phase 4 — UI overhaul

## The root cause

`cloth-store/layouts/app.blade.php` is a byte-for-byte copy of the Tailor
layout — the whole design system (theme tokens, `.badge-*`, `.drawer`,
`.modal`, the `.page` entrance animation, a complete dark theme) was already
available to every Cloth Store page. **The pages simply weren't using it.**

They hand-rolled their own styling instead: `text-2xl font-black` headings
where Tailor uses `text-xl font-bold`, indigo primary buttons where Tailor uses
`slate-900`, `px-6 py-4` table cells where Tailor uses `px-5 py-3`, and one-off
KPI tiles with circular icons instead of Tailor's square chips. Several pages
also omitted the `.page` class, so they appeared with no entrance animation
while Tailor pages faded in.

Dark mode was broken for the same reason. The theme works by overriding the
standard Tailwind classes (`html.theme-dark .bg-white { … }`), so using Tailor's
class vocabulary is what makes a page theme-aware — the ad-hoc colours simply
never followed the theme.

## What was built

**Reusable components** (`resources/views/components/cloth-store/`)

| Component | Purpose |
|---|---|
| `page-header` | Title, subtitle and right-aligned actions |
| `stat-card` | KPI tile with tone-coloured icon chip |
| `panel` | White surface with optional toolbar and footer slots |
| `empty-state` | Works inside a `<tbody>` (via `colspan`) or standalone |
| `pagination` | "Showing X to Y of Z" + windowed page buttons |

The pagination component replaces Laravel's stock view, which is styled for a
generic Tailwind app. It windows the page list, so 400 pages no longer render
400 buttons, and every control is a plain `<a href>` so the SPA router
intercepts it — paging never triggers a full reload.

**Shared control styles** added to the Cloth Store layout: `.btn-cs-primary`,
`.btn-cs-ghost`, `.btn-cs-danger`, `.btn-cs-icon`, `.input-cs`, `.label-cs`,
`.table-cs`, plus `.card-in` for staggered grid entrances. All are written in
theme tokens, so dark mode follows automatically.

Two details worth noting: `.table-cs .row-actions` keeps row action buttons
hidden until hover or keyboard focus, which is what stops a dense table looking
noisy — with a `@media (hover: none)` fallback so they stay visible on touch.
And everything is wrapped in `prefers-reduced-motion` so the animations turn
themselves off for users who ask.

## Pages converted

- **Products** — 4 KPI cards, richer filter bar, and rebuilt cards with a stock
  level bar, live margin %, and a staggered entrance
- **Orders** — 4 KPI cards, avatar customer cells, tabular-figure money columns,
  hover-revealed row actions
- **Customers** — 4 KPI cards, avatar cells, outstanding-balance emphasis
- **Stock** — KPI cards converted, table restyled, tabs corrected to the Tailor
  underline pattern, decimals trimmed so metres read `12` not `12.00`
- **Suppliers** — KPI cards, avatar cells, purchase-count subtext, payable
  emphasis

New KPI queries were added to the Products, Orders and Customers controllers.
All are aggregates over the full table, **not** the current page — otherwise
applying a filter would make the shop's totals appear to shrink.

### One bug worth flagging
Writing `:sub="'<span class=&quot;text-red-500&quot;>…'"` looks reasonable but
does not work: Blade hands a bound attribute to PHP **without** HTML-decoding
it, so `&quot;` would have reached the browser literally and the class would
never have applied. Those strings are now built in `@php` blocks with real
quotes.

## Test checklist

- [ ] Products, Orders, Customers, Stock, Suppliers all render without errors
- [ ] KPI numbers stay constant when you apply a filter (they're global totals)
- [ ] Pagination reads "Showing 1 to 12 of 40 products" and pages without reload
- [ ] Row action buttons appear on hover, and are always visible on touch
- [ ] Toggle **dark mode** — all five pages follow the theme
- [ ] Product cards fade in with a slight stagger; stock bars reflect levels
- [ ] Empty states appear correctly when a filter matches nothing

---
---

# Phase 5 — POS rebuild and the remaining pages

## Smart Checkout

**A three-step machine.** The step transition used to be four `translate-*`
classes toggled by hand at each call site, with no way to represent a third
state. There is now one `goToStep()` that owns which panel is visible *and*
how the stepper reads, so the two can no longer disagree. A stepper across the
top shows Build Cart → Payment → Done.

**Motion that means something.** Every animation reports a specific event
rather than decorating:

| Motion | Reports |
|---|---|
| `cart-item-in` | a new line landed in the cart |
| `cart-bump` | an existing line was topped up, not added again |
| `cart-item-out` | a line is leaving — height collapses so the list doesn't jump |
| `tile-tap` | the tap registered, shown on the tile the cashier is looking at |
| `success-pop` / `success-ring` / drawn tick | the sale went through |

Durations are 150–450ms. Anything slower gets in a cashier's way. All of it is
wrapped in `prefers-reduced-motion`.

**A real success screen.** Completing a sale now lands on a success step showing
the invoice number, total, amount paid and either change due or balance owing —
then offers *View Receipt* or *New Sale* (or press `N`). `startNewSale()` resets
the till and re-queries products so stock figures reflect the sale just made.

**Two fixes worth flagging:**
- `closeReceipt()` called `window.location.reload()`, so every completed sale
  cost a full page load and destroyed the success screen. Resetting the till is
  `startNewSale()`'s job now.
- `showReceipt()` read `options[selectedIndex].text` unguarded — with an empty
  customer select that is `options[-1]` and throws. It now prefers the name the
  server saved on the order.

**Also added:** quick-tender buttons (+500 / +1000 / +2000 / +5000), a search
spinner, and payment-method cards driven by a single `.active` class instead of
eight class swaps per click.

## Pages converted

Payments, Expenses, Purchase Orders, Returns, Discounts, Stock History, Stock
Alerts and both Ledgers — all now use `page-header`, `stat-card`, `panel`,
`table-cs`, `empty-state` and the shared pagination.

### Bugs found while converting
- **Discounts** built its toggle button colour by interpolation
  (`hover:text-{{ … }}-600`). A class assembled at runtime is invisible to a
  compiled Tailwind build, so it rendered unstyled.
- **Stock Alerts** passed product names through `addslashes()` into a JS
  argument inside an HTML attribute — which does not escape quotes for HTML and
  broke on any name containing an apostrophe. Now uses `@js()`.
- Several pages still called `->links('pagination::tailwind')`, bypassing the
  styled component. All replaced.
- Stock History rendered raw decimals, so metres showed as `12.00`. Now trimmed.
- Stock History treated `adjustment` as always negative; direction now comes
  from the before/after snapshot.

## Test checklist

**POS**
- [ ] Add a product — the tile pulses and the line slides into the cart
- [ ] Tap the same product again — the existing line bumps, no duplicate row
- [ ] Remove a line — it collapses rather than vanishing
- [ ] Proceed to Payment — panels slide, stepper marks step 1 done
- [ ] Quick-tender buttons add to the amount received; Full sets the exact total
- [ ] Complete a sale — tick draws, invoice/total/change shown
- [ ] Part-pay a sale — success shows **Balance Due**, not Change
- [ ] Press `N` — till resets, stock figures refresh, no page reload
- [ ] View Receipt then Close — returns to the success screen, no reload

**Converted pages**
- [ ] Payments, Expenses, Purchase Orders, Returns, Discounts, Stock History,
      Stock Alerts, both Ledgers all render and paginate
- [ ] Toggle dark mode across all of them
- [ ] A product or offer whose name contains an apostrophe still works

## Still remaining

Categories, Reports, Loyalty, Users and Settings keep their old markup.
Modals and drawers across the module still use `font-black` and indigo accents
— secondary surfaces, worth a pass but not urgent. The `Atelier.fetch` call
sites still end in `window.location.reload()`.
