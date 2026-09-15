# ANTIGRAVITY TASK — Final 80mm Cloth Store Receipt Integration

## Repository

**Repository:** `https://github.com/noormuhammad2k20-a11y/BST_TLR`  
**Branch:** `main`

You must first inspect the current repository and understand the existing Cloth Store checkout, order re-print, thermal-print isolation, shared receipt styles, controllers, and tests before changing anything.

---

# 1. GOAL

Integrate the **final simple professional 80mm Cloth Store / POS receipt design** into the existing BST_TLR project.

This task is ONLY for the **Cloth Store / POS customer receipt**.

Do **NOT** redesign or break the Tailor Management customer/workshop receipts.

The same final Cloth Store receipt design must be used in both places:

1. Immediately after a sale from **Smart Checkout**.
2. Re-printing an old Cloth Store order from **Cloth Store → Orders**.

Both paths must produce the **same receipt layout and same print width**.

---

# 2. CURRENT REPOSITORY — IMPORTANT FILES ALREADY VERIFIED

The current repository already contains the complete Cloth Store thermal-print flow.

### Checkout receipt

`resources/views/cloth-store/checkout/index.blade.php`

It currently contains:

- `#receiptModal`
- `#receipt-content`
- `printClothThermal()`
- `window.printReceipt = printClothThermal`
- `showLastReceipt()`
- `buildReceiptHtml()`
- `lastOrder`
- dedicated `printing-cloth-thermal` print mode
- isolated `#thermal-print-area`

The current receipt inside `buildReceiptHtml()` still contains:

- `CUSTOMER COPY`
- the old `.slip-kind` block
- the bottom code like `* INV-000001 *`
- `.slip-code`
- the older receipt layout

### Cloth Store order re-print

`resources/views/cloth-store/orders/index.blade.php`

It currently contains:

- `#receiptModal`
- `#receipt-content`
- `printClothThermalDirect()`
- `window.printReceipt(...)`
- `renderReceipt(order)`
- the same dedicated `printing-cloth-thermal` print isolation

Its current `renderReceipt(order)` also still renders:

- `CUSTOMER COPY`
- the old `.slip-kind`
- bottom `* invoice_number *`
- `.slip-code`

### Shared Tailor receipt styles

`resources/views/receipts/slip-styles.blade.php`

This file is shared with Tailor Management receipts and already contains important thermal-density rules.

**DO NOT globally redesign this shared file.**

Do not delete `.slip-kind`, `.slip-code`, measurement rules, workshop styles, or thermal darkness rules from this file because Tailor receipts may still depend on them.

If extra styling is required for the Cloth Store receipt, use **cloth-specific namespaced classes**, preferably in a new small partial such as:

`resources/views/cloth-store/receipts/simple-styles.blade.php`

Then include that partial **after** `@include('receipts.slip-styles')` in both Cloth Store receipt pages.

### Checkout controller

`app/Http/Controllers/ClothStore/CheckoutController.php`

The server already owns the money calculations. It creates a real sequential invoice like:

`INV-000042`

and returns the saved order with:

- `customer`
- `items.product`
- `subtotal`
- `discount`
- `total_amount`
- `paid_amount`
- `invoice_number`
- `created_at`

**Do not move financial calculations to JavaScript.**

Use the saved server response exactly as the current project does.

---

# 3. VERY IMPORTANT — MINIMAL CHANGE ONLY

Do not rewrite the checkout system.

Do not change:

- checkout logic
- stock deduction
- inventory transactions
- customer ledger
- customer payments
- discounts
- payment logic
- order creation
- invoice numbering
- customer search
- product search
- barcode scanning
- Done screen
- routes
- controllers unless genuinely required for an existing missing field
- database tables
- migrations
- Tailor Management receipts
- Workshop Copy receipts
- existing thermal print isolation

The sale flow is already working.

This task is primarily a **Cloth Store receipt markup + styling update**.

---

# 4. FINAL RECEIPT DESIGN REQUIREMENTS

The final receipt must be:

- simple
- professional
- clean
- compact
- readable on a real 80mm thermal printer
- not decorative
- not over-designed
- no large boxed badges
- no giant headings
- no unnecessary promotional graphics
- no A4-style layout

## REMOVE COMPLETELY FROM CLOTH STORE RECEIPTS

Do not render:

```text
CUSTOMER RECEIPT
```

Do not render:

```text
CUSTOMER COPY
```

Do not render the bottom starred invoice code:

```text
* INV-99081 *
```

or:

```text
* INV-000042 *
```

The invoice number must still appear normally in the **Invoice** row.

Important: **do not delete the shared `.slip-kind` or `.slip-code` CSS globally**. Simply stop using those elements in the Cloth Store receipt HTML.

---

# 5. FINAL CONTENT ORDER

Print the receipt in this exact logical order:

```text
SHOP NAME
Optional tagline
Shop address
Shop phone

--------------------------------
Invoice                         INV-000042
Date                       15 Sep 2026...
Customer                    Customer Name
--------------------------------

Items
Item                         Qty      Amount
---------------------------------------------
Product Name
4m x 1,500                    4m       6,000

Product Name
1 x 4,500                      1       4,500

--------------------------------
Subtotal                           Rs 10,500
Discount                             - Rs 500
--------------------------------
Total                              Rs 10,000

--------------------------------
Thank you for shopping with us.
Please keep this receipt for your records.

--------------------------------
Designed & Developed By
Noor M Hingorjo
0303 4980786
POS & MANAGEMENT SYSTEM
```

Do not add any extra invoice code at the bottom.

---

# 6. EXACT DEVELOPER CREDIT

At the end of every Cloth Store receipt, add exactly:

```text
Designed & Developed By
Noor M Hingorjo
0303 4980786
POS & MANAGEMENT SYSTEM
```

This block must be visible but subtle.

It must never become more visually prominent than:

- shop name
- invoice information
- purchased items
- total

Do not put it inside a heavy black box.

---

# 7. DATA MAPPING — NEVER HARDCODE DEMO VALUES

Do **not** hardcode:

- `INV-99081`
- `Muhammad Ali`
- demo shop address
- demo products
- demo totals
- demo dates

Use the existing real order object.

Map data as follows:

| Receipt field | Existing data |
|---|---|
| Shop name | `Atelier.shop.name` |
| Tagline | `Atelier.shop.tagline` |
| Address | `Atelier.shop.address` |
| Phone | `Atelier.shop.phone` |
| Invoice | `order.invoice_number` |
| Date | `order.created_at` |
| Customer | `order.customer?.name` with existing safe fallback |
| Product name | `item.product?.name` |
| Unit | `item.product?.unit` |
| Quantity | `item.quantity` |
| Unit price | `item.unit_price` |
| Line total | `item.total` |
| Subtotal | `order.subtotal` |
| Discount | `order.discount` |
| Total | `order.total_amount` |

Use the existing `Atelier.escapeHtml(...)` for dynamic text wherever available.

Do not trust raw product/customer/shop text inside template literals.

---

# 8. QUANTITY DISPLAY

Preserve decimal meter quantities correctly.

Examples:

```text
4 meters      -> 4m
5.5 meters    -> 5.5m
5.25 meters   -> 5.25m
1 piece       -> 1
2 pieces      -> 2
```

Do not force `5.50` to become an incorrect integer.

Do not display ugly floating-point artifacts such as:

```text
5.5000000001
```

---

# 9. PRINTING — KEEP THE CURRENT PROVEN ARCHITECTURE

The repository already solved an important issue: the global Cloth Store layout has normal/A4 print behavior, while thermal receipts need a separate print canvas.

Keep the existing architecture:

- `html.printing-cloth-thermal`
- `#thermal-print-area`
- clone the receipt before printing
- hide everything except the cloned thermal receipt
- call `window.print()`
- cleanup after printing

Do **not** go back to printing the entire page or modal.

Do **not** remove the current isolated print mechanism.

Do **not** use:

```css
transform: scale(...)
zoom: ...
```

to fake an 80mm receipt.

The real printer is an **80mm thermal printer** and the usable printing canvas is approximately **72mm / 72.1mm**.

Keep the print receipt at real physical width.

The existing print mode already uses 72mm. Preserve that behavior.

---

# 10. THERMAL DARKNESS / READABILITY

Do not reintroduce faded gray print.

The project already has shared thermal-density print rules in:

`resources/views/receipts/slip-styles.blade.php`

Keep those rules working.

For the new Cloth Store-specific classes, ensure print output is:

- solid black
- opacity 1
- no filters
- no text-shadow
- no faint gray text on actual print

Screen preview may use slightly muted text, but `@media print` should force receipt text to black.

Do not make every heading excessively bold. The visual design should remain simple, but physical thermal output must be legible.

---

# 11. RECOMMENDED SAFE IMPLEMENTATION

Prefer this minimal structure:

### A. Create one Cloth Store-specific style partial

Recommended path:

```text
resources/views/cloth-store/receipts/simple-styles.blade.php
```

Put only `.cloth-receipt...` / `.cr-...` styles there.

### B. Include it after the existing shared slip styles

In both:

```text
resources/views/cloth-store/checkout/index.blade.php
resources/views/cloth-store/orders/index.blade.php
```

keep:

```blade
@include('receipts.slip-styles')
```

and immediately after it add:

```blade
@include('cloth-store.receipts.simple-styles')
```

### C. Keep the outer `.slip` class for compatibility

Use:

```html
<div class="slip cloth-receipt slip-preview">
```

This allows the current print helper to continue finding:

```js
#receipt-content .slip
```

without changing proven print logic.

The new `.cloth-receipt` class can override the old generic slip font/layout only for Cloth Store receipts.

### D. Update both receipt renderers

Update only the receipt markup in:

```text
buildReceiptHtml()
```

inside checkout and:

```text
renderReceipt(order)
```

inside Cloth Store Orders.

Both must render the same visual structure.

Do not duplicate two different receipt designs.

---

# 12. FINAL CLOTH STORE RECEIPT CSS REFERENCE

Use this as the target design. Adapt only where required by the existing repo architecture.

```css
/* =========================================================
   CLOTH STORE — SIMPLE 80MM RECEIPT
   Scoped so Tailor/Workshop receipts are untouched.
========================================================= */

.cloth-receipt {
    width: 72mm;
    max-width: 72mm;
    box-sizing: border-box;
    margin: 0 auto;
    padding: 2.5mm 2mm 5mm;
    background: #fff;
    color: #111;
    font-family: Arial, Helvetica, sans-serif !important;
    font-size: 11px;
    line-height: 1.4;
    -webkit-font-smoothing: antialiased;
}

.cloth-receipt .cr-header {
    text-align: center;
}

.cloth-receipt .cr-brand {
    margin: 0;
    font-size: 19px;
    line-height: 1.15;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.cloth-receipt .cr-tagline {
    margin-top: 3px;
    font-size: 9px;
    color: #555;
}

.cloth-receipt .cr-contact {
    margin-top: 3px;
    font-size: 9px;
    line-height: 1.4;
    color: #333;
    overflow-wrap: anywhere;
}

.cloth-receipt .cr-separator {
    border-top: 1px dashed #777;
    margin: 9px 0;
}

.cloth-receipt .cr-info {
    display: grid;
    gap: 4px;
}

.cloth-receipt .cr-info-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}

.cloth-receipt .cr-info-label {
    flex: 0 0 auto;
    color: #555;
    white-space: nowrap;
}

.cloth-receipt .cr-info-value {
    min-width: 0;
    font-weight: 600;
    text-align: right;
    overflow-wrap: anywhere;
}

.cloth-receipt .cr-section-title {
    margin-bottom: 6px;
    font-size: 10px;
    font-weight: 700;
}

.cloth-receipt .cr-item-head,
.cloth-receipt .cr-item-main {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 32px 58px;
    gap: 5px;
}

.cloth-receipt .cr-item-head {
    padding-bottom: 4px;
    border-bottom: 1px solid #222;
    font-size: 9px;
    font-weight: 700;
}

.cloth-receipt .cr-item-head > :nth-child(2),
.cloth-receipt .cr-item-qty {
    text-align: center;
}

.cloth-receipt .cr-item-head > :nth-child(3),
.cloth-receipt .cr-item-total {
    text-align: right;
}

.cloth-receipt .cr-item {
    padding: 6px 0;
    border-bottom: 1px dotted #aaa;
    break-inside: avoid;
    page-break-inside: avoid;
}

.cloth-receipt .cr-item:last-child {
    border-bottom: 0;
}

.cloth-receipt .cr-item-main {
    align-items: start;
}

.cloth-receipt .cr-item-name {
    min-width: 0;
    font-size: 10px;
    line-height: 1.3;
    font-weight: 600;
    overflow-wrap: anywhere;
}

.cloth-receipt .cr-item-meta {
    margin-top: 2px;
    font-size: 8.5px;
    color: #555;
}

.cloth-receipt .cr-item-qty {
    font-size: 9.5px;
}

.cloth-receipt .cr-item-total {
    font-size: 10px;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.cloth-receipt .cr-totals {
    margin-top: 3px;
}

.cloth-receipt .cr-total-row {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 2px 0;
    font-size: 10px;
}

.cloth-receipt .cr-total-row > :last-child {
    text-align: right;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.cloth-receipt .cr-grand-total {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 15px;
    margin-top: 6px;
    padding-top: 7px;
    border-top: 1.5px solid #111;
    font-size: 14px;
    font-weight: 700;
}

.cloth-receipt .cr-grand-total > :last-child {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.cloth-receipt .cr-footer {
    margin-top: 15px;
    text-align: center;
}

.cloth-receipt .cr-thanks {
    font-size: 10.5px;
    font-weight: 700;
}

.cloth-receipt .cr-footer-note {
    margin-top: 4px;
    font-size: 8.5px;
    line-height: 1.4;
    color: #555;
}

.cloth-receipt .cr-developer {
    margin-top: 11px;
    padding-top: 8px;
    border-top: 1px dashed #999;
    text-align: center;
    font-size: 7.8px;
    line-height: 1.45;
    color: #555;
}

.cloth-receipt .cr-dev-title {
    font-size: 7.5px;
}

.cloth-receipt .cr-dev-name {
    margin-top: 1px;
    font-size: 9px;
    font-weight: 700;
    color: #111;
}

.cloth-receipt .cr-dev-phone {
    margin-top: 1px;
    font-size: 8px;
    color: #333;
}

.cloth-receipt .cr-dev-system {
    margin-top: 2px;
    font-size: 7px;
    letter-spacing: .05em;
}

@media print {
    .cloth-receipt {
        width: 100% !important;
        max-width: 100% !important;
        margin: 0 !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        color: #000 !important;
        font-weight: 600;
        -webkit-font-smoothing: none;
    }

    .cloth-receipt,
    .cloth-receipt * {
        color: #000 !important;
        opacity: 1 !important;
        filter: none !important;
        text-shadow: none !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .cloth-receipt .cr-item {
        border-bottom-color: #000 !important;
    }

    .cloth-receipt .cr-separator,
    .cloth-receipt .cr-developer {
        border-color: #000 !important;
    }
}
```

---

# 13. FINAL RECEIPT HTML/JS MARKUP REFERENCE

This is the **production data structure**, not demo data.

Use this markup inside both existing receipt render paths.

Do not create a fake standalone order object.

```js
function formatReceiptQty(item) {
    const quantity = Number(item?.quantity || 0);
    const clean = Number.isInteger(quantity)
        ? String(quantity)
        : quantity.toFixed(2).replace(/\.?0+$/, '');

    return item?.product?.unit === 'meter'
        ? `${clean}m`
        : clean;
}

function formatReceiptDate(value) {
    const date = value ? new Date(value) : new Date();

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    });
}

function buildSimpleClothReceipt(order, customerName = null) {
    const shop = window.Atelier?.shop || {};
    const escapeHtml = value => window.Atelier?.escapeHtml
        ? window.Atelier.escapeHtml(value ?? '')
        : String(value ?? '');

    const name = customerName
        || order?.customer?.name
        || 'Walk-in Customer';

    const itemsHtml = (order?.items || []).map(item => {
        const qtyText = formatReceiptQty(item);
        const unitPrice = Number(item?.unit_price || 0);
        const lineTotal = Number(item?.total || 0);
        const productName = item?.product?.name || 'Item';

        return `
            <div class="cr-item">
                <div class="cr-item-main">
                    <div>
                        <div class="cr-item-name">${escapeHtml(productName)}</div>
                        <div class="cr-item-meta">${escapeHtml(qtyText)} x ${unitPrice.toLocaleString()}</div>
                    </div>

                    <div class="cr-item-qty">${escapeHtml(qtyText)}</div>
                    <div class="cr-item-total">${lineTotal.toLocaleString()}</div>
                </div>
            </div>
        `;
    }).join('');

    return `
        <div class="slip cloth-receipt slip-preview">

            <header class="cr-header">
                <div class="cr-brand">${escapeHtml(shop.name || 'Cloth Store')}</div>

                ${shop.tagline ? `
                    <div class="cr-tagline">${escapeHtml(shop.tagline)}</div>
                ` : ''}

                ${shop.address ? `
                    <div class="cr-contact">${escapeHtml(shop.address)}</div>
                ` : ''}

                ${shop.phone ? `
                    <div class="cr-contact">${escapeHtml(shop.phone)}</div>
                ` : ''}
            </header>

            <div class="cr-separator"></div>

            <section class="cr-info">
                <div class="cr-info-row">
                    <span class="cr-info-label">Invoice</span>
                    <span class="cr-info-value">${escapeHtml(order?.invoice_number || '')}</span>
                </div>

                <div class="cr-info-row">
                    <span class="cr-info-label">Date</span>
                    <span class="cr-info-value">${escapeHtml(formatReceiptDate(order?.created_at))}</span>
                </div>

                <div class="cr-info-row">
                    <span class="cr-info-label">Customer</span>
                    <span class="cr-info-value">${escapeHtml(name)}</span>
                </div>
            </section>

            <div class="cr-separator"></div>

            <section>
                <div class="cr-section-title">Items</div>

                <div class="cr-item-head">
                    <div>Item</div>
                    <div>Qty</div>
                    <div>Amount</div>
                </div>

                <div>
                    ${itemsHtml || `
                        <div class="cr-item">
                            <div class="cr-item-name">No items</div>
                        </div>
                    `}
                </div>
            </section>

            <div class="cr-separator"></div>

            <section class="cr-totals">
                <div class="cr-total-row">
                    <span>Subtotal</span>
                    <span>Rs ${Number(order?.subtotal || 0).toLocaleString()}</span>
                </div>

                ${Number(order?.discount || 0) > 0 ? `
                    <div class="cr-total-row">
                        <span>Discount</span>
                        <span>- Rs ${Number(order.discount).toLocaleString()}</span>
                    </div>
                ` : ''}

                <div class="cr-grand-total">
                    <span>Total</span>
                    <span>Rs ${Number(order?.total_amount || 0).toLocaleString()}</span>
                </div>
            </section>

            <footer class="cr-footer">
                <div class="cr-separator"></div>

                <div class="cr-thanks">
                    Thank you for shopping with us.
                </div>

                <div class="cr-footer-note">
                    Please keep this receipt for your records.
                </div>

                <div class="cr-developer">
                    <div class="cr-dev-title">Designed &amp; Developed By</div>
                    <div class="cr-dev-name">Noor M Hingorjo</div>
                    <div class="cr-dev-phone">0303 4980786</div>
                    <div class="cr-dev-system">POS &amp; MANAGEMENT SYSTEM</div>
                </div>
            </footer>

        </div>
    `;
}
```

---

# 14. CHECKOUT PAGE INTEGRATION

In:

`resources/views/cloth-store/checkout/index.blade.php`

keep the current `buildReceiptHtml()` entry point because other checkout code already calls it.

Replace only its old receipt markup with the new builder output.

Conceptually:

```js
function buildReceiptHtml() {
    const order = lastOrder;
    if (!order) return;

    const selected = customerSelect?.options?.[customerSelect.selectedIndex];

    const selectedName = selected && selected.value
        ? selected.text.split('(')[0].trim()
        : '';

    const customerName = order.customer?.name
        || selectedName
        || 'Walk-in Customer';

    document.getElementById('receipt-content').innerHTML =
        buildSimpleClothReceipt(order, customerName);
}
```

Do not change the successful-sale flow.

Do not remove the automatic print call after sale unless there is an existing bug that specifically requires it.

Keep:

```js
window.printReceipt = printClothThermal;
```

and the existing clone/cleanup mechanism.

---

# 15. ORDERS PAGE / REPRINT INTEGRATION

In:

`resources/views/cloth-store/orders/index.blade.php`

keep the current `window.printReceipt(id, orderData = null)` behavior.

Keep fetching the full saved order when needed.

Update `renderReceipt(order)` so it uses the **same exact final receipt builder**.

Conceptually:

```js
function renderReceipt(order) {
    const customerName = order?.customer?.name || 'Walk-in Customer';

    document.getElementById('receipt-content').innerHTML =
        buildSimpleClothReceipt(order, customerName);

    document.getElementById('receiptModal').classList.remove('hidden');
    document.getElementById('orderModal').classList.add('hidden');
}
```

Keep:

```js
printClothThermalDirect()
```

and the existing isolated thermal print area.

---

# 16. IMPORTANT CONSISTENCY RULE

The checkout receipt and old-order reprint must visually match exactly.

For the same saved order, these two paths must not produce different receipts:

```text
Smart Checkout -> completed sale -> Print 80mm Receipt
```

and:

```text
Cloth Store -> Orders -> Print Receipt
```

Use the same class names, spacing, typography, columns, footer, and developer credit.

---

# 17. DO NOT BREAK TAILOR RECEIPTS

The repository also contains Tailor Management thermal receipt logic and browser tests.

Do not modify Tailor customer/workshop output just to achieve the Cloth Store design.

Especially do not globally remove or redefine shared classes in a way that changes:

- Customer Copy
- Workshop Copy
- garment measurements
- job-card pagination
- branding/logo/stamp printing
- existing print density

Cloth Store styling must stay scoped under:

```css
.cloth-receipt
```

or equivalent cloth-specific namespace.

---

# 18. BROWSER / THERMAL PRINT EXPECTATION

The final physical test target is:

```text
Printer: BC-80POS / Black Copper 80mm thermal
Paper: 80(72.1) x 297 mm
Scale: 100%
Margins: None
Headers and footers: Off
```

Expected physical output:

- nearly full usable 72mm width
- no huge blank area on left/right
- no A4 shrinking
- no tiny receipt in the center of a large page
- no side-by-side layout
- no faded gray text
- clean black readable print

---

# 19. ERROR / GLITCH PREVENTION

Before editing, inspect all call sites for:

```text
buildReceiptHtml
renderReceipt
printClothThermal
printClothThermalDirect
printReceipt
showLastReceipt
receiptModal
receipt-content
thermal-print-area
printing-cloth-thermal
```

Do not rename public functions unless every call site is updated and tested.

Do not create duplicate DOM IDs.

Do not leave stale print areas in the DOM.

Do not make the modal itself the only source of print visibility; the current clone-to-print-area approach must remain safe.

Do not use inline demo data.

Do not silently fall back to browser `window.print()` of the whole page.

---

# 20. TESTS / VERIFICATION REQUIRED

After implementation, run the relevant existing test suite and build checks.

At minimum:

```bash
php artisan optimize:clear
php artisan test
npm run build
```

Also run existing receipt-related browser tests where the local project supports them:

```bash
node tests/Browser/receipt-images.cjs
node tests/Browser/workshop-print.cjs
```

The Tailor receipt tests must continue passing because this Cloth Store update must not regress shared receipt behavior.

Then manually verify:

### Checkout

1. Add a meter-based product.
2. Add a piece-based product.
3. Use a decimal meter quantity such as `5.5`.
4. Add a discount.
5. Complete sale.
6. Verify the receipt contains the real generated invoice.
7. Verify no `CUSTOMER COPY` appears.
8. Verify no bottom `* INV-... *` appears.
9. Verify developer credit appears exactly once.
10. Print through BC-80POS.

### Order reprint

1. Open Cloth Store → Orders.
2. Print the same saved order.
3. Compare it with the checkout receipt.
4. Both must match visually and financially.

### Thermal width

Verify:

- receipt is 72mm printable width
- content is not tiny
- no huge side margins
- no A4 scaling
- no clipped product names
- Qty and Amount columns remain aligned
- long product names wrap naturally

---

# 21. ACCEPTANCE CRITERIA

Do not consider the task complete until all are true:

- [ ] Cloth Store checkout receipt uses the new simple design.
- [ ] Cloth Store Orders reprint uses the same design.
- [ ] `CUSTOMER RECEIPT` is absent.
- [ ] `CUSTOMER COPY` is absent from Cloth Store receipt.
- [ ] bottom `* INV-... *` is absent.
- [ ] normal Invoice row remains.
- [ ] shop name/address/phone remain dynamic.
- [ ] customer remains dynamic.
- [ ] items remain dynamic.
- [ ] meter decimals remain correct.
- [ ] unit prices are real saved order values.
- [ ] totals use saved server values.
- [ ] discount only appears when greater than zero.
- [ ] developer credit is present exactly once.
- [ ] receipt prints at real thermal width.
- [ ] thermal text is dark/readable.
- [ ] no A4 shrink bug.
- [ ] no giant blank left/right margin caused by app CSS.
- [ ] no Tailor receipt regression.
- [ ] no Workshop Copy regression.
- [ ] existing checkout/payment/inventory logic remains untouched.
- [ ] relevant tests pass.
- [ ] `npm run build` passes.

---

# 22. FINAL RESPONSE REQUIRED FROM ANTIGRAVITY

After implementing, do not just say “done”.

Return:

1. Exact files changed.
2. Exact files added.
3. What old receipt markup was removed.
4. Confirmation that both checkout and Orders reprint use the same final design.
5. Confirmation that `CUSTOMER COPY` was removed only from the Cloth Store receipt.
6. Confirmation that bottom starred invoice code was removed.
7. Confirmation that Tailor/Workshop receipts were not redesigned.
8. Confirmation that the existing 72mm isolated thermal print mechanism was preserved.
9. Commands/tests run.
10. Test/build results.
11. Any issue still remaining — do not hide it.

**Do not make unrelated changes anywhere in the project.**
