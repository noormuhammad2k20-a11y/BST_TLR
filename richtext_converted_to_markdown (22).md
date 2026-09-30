FIX ONLY THESE TWO CUSTOMER COPY ISSUES.

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

Page:

http://127.0.0.1:8000/orders

IMPORTANT:

Inspect the CURRENT latest repository first.

This task applies ONLY to:

Booking Receipt · Customer Copy

DO NOT change Workshop Copy.

DO NOT change Final Receipt.

DO NOT change Cloth Store receipt.

DO NOT change print-page height behavior.

DO NOT redesign the receipt.

\==================================================

ISSUE 1 — SUBTOTAL IS PRINTING TWICE

\==================================================

Current Customer Copy shows:

Subtotal Rs.8,000.00

Subtotal Rs.8,000.00

Total Rs.8,000.00

Advance Paid Rs.0.00

Balance Rs.8,000.00

This is WRONG.

Required:

Subtotal Rs.8,000.00

Total Rs.8,000.00

Advance Paid Rs.0.00

Balance Rs.8,000.00

ONLY ONE Subtotal row.

\==================================================

ROOT CAUSE

\==================================================

Current backend:

PricingService::orderLines()

already returns:

Subtotal

optional Tax

optional Service Charge

optional Historical adjustment

Total

But in:

resources/views/orders/index.blade.php

current code approximately does:

let extraLinesHtml = (o.lines || \[\])

.filter(l => l.label.toLowerCase() !== 'total')

...

Then below it manually renders another:

Subtotal

Therefore:

Subtotal from o.lines

+

manual Subtotal

\= duplicated Subtotal.

\==================================================

REQUIRED FIX

\==================================================

Do NOT simply hide random rows.

Normalize the billing-line rendering correctly.

\`extraLinesHtml\` must EXCLUDE BOTH:

Subtotal

Total

Concept:

const excludedBillingLabels = new Set(\[

'subtotal',

'total'

\]);

let extraLinesHtml = (o.lines || \[\])

.filter(line => {

const label = String(line.label || '').trim().toLowerCase();

return !excludedBillingLabels.has(label);

})

.map(...)

.join('');

Then separately render ONE authoritative Subtotal row.

\==================================================

IMPORTANT — CORRECT SUBTOTAL VALUE

\==================================================

Do NOT blindly use:

o.total

as Subtotal if tax/service charge exists.

Current backend billing lines already contain the authoritative Subtotal.

Find it safely:

const subtotalLine = (o.lines || \[\]).find(

line => String(line.label || '').trim().toLowerCase() === 'subtotal'

);

const subtotalAmount =

subtotalLine

? subtotalLine.amount

: (o.subtotal ?? o.total ?? 0);

Then render:

Subtotal .... ${rs(subtotalAmount)}

And Total remains:

${rs(o.total)}

This ensures:

Subtotal

Tax / Service Charge if applicable

Total

remain mathematically correct.

\==================================================

EXPECTED PAYMENT SECTION

\==================================================

NO TAX / CHARGE:

PAYMENT

Subtotal ................ Rs.8,000.00

\[ TOTAL Rs.8,000.00 \]

Advance Paid ............ Rs.0.00

Balance ................. Rs.8,000.00

WITH TAX / SERVICE CHARGE:

PAYMENT

Subtotal ................ Rs.8,000.00

Service Charge .......... Rs.X

Tax ..................... Rs.X

\[ TOTAL Rs.Y \]

Advance Paid ............ Rs.Z

Balance ................. Rs.X

Subtotal must NEVER appear twice.

\==================================================

DO NOT REMOVE OTHER REAL BILLING LINES

\==================================================

Preserve legitimate lines such as:

Tax

Service Charge

Historical adjustment

Only prevent duplicate rendering of:

Subtotal

Total

Do NOT change backend PricingService calculations.

Do NOT change order total.

Do NOT change ledger/payment calculations.

\==================================================

ISSUE 2 — THANK-YOU MESSAGE IS TOO FAINT

\==================================================

Current text:

Thank you for choosing Best Tailor.

Please present this receipt when collecting your order.

is too light / difficult to read on physical thermal print.

Current \`.rc-note\` styling is approximately:

font-weight: 400

font-size: 8.5px

color: #333

The client wants this text CLEAR and properly visible.

\==================================================

REQUIRED THANK-YOU FIX

\==================================================

DO NOT redesign the receipt.

DO NOT make this a large heading.

Keep the same centered placement.

Create a Customer-Copy-specific class, for example:

rc-customer-message

Markup concept:

Thank you for choosing Best Tailor.  

Please present this receipt when collecting your order.

Style ONLY this message:

.rc-customer-message {

color: #000;

font-weight: 600;

opacity: 1;

line-height: 1.6;

}

If absolutely necessary for physical thermal readability, use a tiny increase

only for this message:

font-size: 9px;

But prefer preserving the current size and improving weight/contrast first.

\==================================================

PRINT READABILITY

\==================================================

On physical print ensure:

#thermal-print-area .rc-customer-message {

color: #000 !important;

opacity: 1 !important;

font-weight: 600 !important;

}

Do NOT make it gray.

Do NOT use:

opacity

filter

text-shadow

that reduces thermal clarity.

\==================================================

DO NOT CHANGE ALL .rc-note CONTENT

\==================================================

Important:

\`.rc-note\` may also be used for:

terms

other receipt notes

Do NOT globally make every \`.rc-note\` huge/bold.

Target ONLY the fixed thank-you/customer collection message using a dedicated

class.

\==================================================

CUSTOMER COPY FINAL EXPECTED OUTPUT

\==================================================

PAYMENT

Subtotal ................ Rs.8,000.00

\[ TOTAL Rs.8,000.00 \]

Advance Paid ............ Rs.0.00

Balance ................. Rs.8,000.00

\[ DELIVERY · ... \]

Thank you for choosing Best Tailor.

Please present this receipt when collecting your order.

The above two lines must be clearly visible in solid black.

Then existing:

DESIGNED & DEVELOPED BY

NOOR M. HINGORJO

SOFTWARE SUPPORT: 0303 4980786

and:

THANK YOU

remain exactly as they are.

\==================================================

DO NOT CHANGE

\==================================================

Do NOT change:

\- Customer Copy header

\- shop name

\- tagline

\- address

\- Order / Invoice / Date / Customer / Phone

\- Items

\- garment quantities

\- unit prices

\- Total box design

\- Advance Paid

\- Balance

\- Delivery box

\- developer credit

\- THANK YOU footer

\- Customer Copy compact page-height behavior

\- Workshop Copy

\- Workshop dynamic one-page behavior

\- measurements

\- compound measurements

\- database

\- PricingService

\- payment calculations

\- order workflow

\- Final Receipt

\- Cloth Store

\- theme

\==================================================

EXPECTED FILE

\==================================================

Primary expected change:

resources/views/orders/index.blade.php

This should be a very small targeted fix.

Do NOT refactor the whole receipt system.

\==================================================

TEST

\==================================================

TEST 1:

Order total:

Rs.8,000.00

No tax/service charge.

Expected exactly:

Subtotal Rs.8,000.00

Total Rs.8,000.00

Advance Paid Rs.0.00

Balance Rs.8,000.00

Count the word:

Subtotal

It must appear EXACTLY ONCE in Payment section.

TEST 2:

Enable Tax / Service Charge if supported.

Expected:

Subtotal real subtotal

Tax/Charge real values

Total real total

No duplicate Subtotal.

TEST 3:

Customer Copy preview.

Verify message:

Thank you for choosing Best Tailor.

Please present this receipt when collecting your order.

is clearly visible.

TEST 4:

Customer-only thermal print / Save as PDF.

Verify same message prints dark and readable.

TEST 5:

Workshop Copy.

Confirm ZERO visual or functional changes.

\==================================================

FINAL RULE

\==================================================

FIX ONLY:

1\. DUPLICATE SUBTOTAL ON CUSTOMER COPY

2\. FAINT CUSTOMER THANK-YOU MESSAGE

DO NOT CHANGE ANYTHING ELSE.