FIX ONLY THESE TWO CURRENT BUGS IN BST\_TLR:

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

IMPORTANT:

Work on the CURRENT latest repository.

DO NOT redesign any receipt.

DO NOT change font sizes, spacing, colors, layout, widths, customer copy design,

workshop design, order calculations, payments, measurements UI, quick actions,

or any unrelated functionality.

There are exactly TWO bugs to fix:

1\. Compound measurement values such as:

56.50-34.40

are not printing exactly on the Workshop Copy.

2\. Chrome/Windows print preview opens as a completely BLANK white thermal page

when using the receipt print buttons.

\==================================================

BUG 1 — COMPOUND MEASUREMENTS ARE DESTROYED

\==================================================

The new measurement-string work is already partly implemented.

Current repository already has:

app/Rules/MeasurementValue.php

and migration:

database/migrations/2026\_09\_17\_144148\_convert\_measurements\_to\_string.php

The Add/Update Measurement UI and Order Step 3 have also been changed to text

inputs.

DO NOT recreate this work.

FIRST verify migration status:

php artisan migrate:status

If:

2026\_09\_17\_144148\_convert\_measurements\_to\_string

is pending, run:

php artisan migrate

Do NOT edit old migrations.

\==================================================

EXACT ROOT CAUSE FOUND

\==================================================

Inspect:

app/Http/Controllers/OrderController.php

Current method:

measurementRows()

still contains numeric formatting similar to:

$clean = rtrim(

rtrim(number\_format((float) $value, 2, '.', ''), '0'),

'.'

);

THIS IS WRONG NOW.

Measurement values are no longer guaranteed to be a single numeric value.

Example:

56.50-34.40

When PHP executes:

(float) '56.50-34.40'

it becomes approximately:

56.5

Therefore the rest of the tailoring notation is destroyed before the receipt

ever reaches JavaScript.

\==================================================

REQUIRED FIX FOR measurementRows()

\==================================================

Measurement values must now be treated as TEXT FOR DISPLAY.

Do NOT:

\- cast them to float

\- use number\_format()

\- use parseFloat()

\- use Number()

\- perform subtraction

\- remove the part after "-"

\- normalize the notation

For every non-empty measurement:

$value = trim((string) $value);

Return the actual value.

Conceptually:

if ($value === null) continue;

$value = trim((string) $value);

if ($value === '') continue;

$rows\[\] = \[

'key' => $field,

'label' => ...,

'value' => $value,

\];

IMPORTANT:

56.50-34.40

must stay:

56.50-34.40

000

must stay:

000

0

must stay:

0

0.00

must stay:

0.00

Do NOT convert them.

\==================================================

EXPECTED WORKSHOP COPY

\==================================================

If database contains:

Length = 34.5

Shoulder = 56.50-34.40

Sleeves = 646

Collar = 2.25-1.50

Cuff = 000

Elbow = 15

Workshop Copy MUST display:

Length 34.5

Shoulder 56.50-34.40

Sleeves 646

Collar 2.25-1.50

Cuff 000

Elbow 15

exactly.

No:

56.5

instead of:

56.50-34.40

No:

NaN

No:

—

when a real value exists.

\==================================================

CHECK ALL RECEIPT MEASUREMENT FORMATTERS

\==================================================

Search the whole project for measurement-related code using:

(float)

number\_format(

Number(

parseFloat(

.toFixed(

is\_numeric(

Do NOT globally remove numeric formatting.

Prices/payment values MUST remain numeric.

Only measurement-value formatting must preserve the stored text exactly.

Especially inspect:

app/Http/Controllers/OrderController.php

resources/views/orders/index.blade.php

resources/views/delivery/receipt.blade.php

resources/views/measurements/index.blade.php

\==================================================

BUG 2 — CHROME PRINT PREVIEW IS BLANK

\==================================================

Current order receipt preview itself appears correctly on screen.

But when pressing:

Customer only

Workshop only

Print both

Chrome print dialog opens with a completely blank thermal page.

Inspect:

resources/views/orders/index.blade.php

Current print function:

window.printThermal()

Current actual receipt roots are:

#slip-customer

class="rc slip-preview"

and:

#slip-tailor

class="rc-workshop slip-preview"

But much of the current print CSS still targets the OLD \`.slip\` structure.

Examples currently include selectors such as:

#thermal-print-area .slip

#thermal-print-area .slip \*

#thermal-print-area .slip-row

#thermal-print-area .slip-workshop

#thermal-print-area .slip-mcell

This print path MUST be updated to match the CURRENT Neo receipt roots/classes.

\==================================================

DO NOT CHANGE THE RECEIPT DESIGN

\==================================================

Screen preview is already approved.

Do NOT modify the visual appearance of:

.rc

.rc-workshop

.rc-head

.rc-name

.rc-tag

.rc-meta

.rc-m

.rc-sec

.rc-item

.rc-tot

.rc-ms

.rc-inst

etc.

Only make the existing receipt reliably printable.

\==================================================

PRINT AREA REQUIREMENTS

\==================================================

Keep the dedicated temporary print container approach.

The print flow must be:

1\. Find requested receipt element(s).

2\. Clone the CURRENT rendered receipt(s).

3\. Put clones inside:

#thermal-print-area

4\. Remove screen-only class from clones:

slip-preview

5\. Add a reliable print-root class if useful, for example:

thermal-print-slip

without changing visual design.

6\. Append print area directly to document.body.

7\. Make it visible.

8\. Apply the printing class.

9\. FORCE browser layout before calling print.

Use a reliable sequence such as:

await new Promise(resolve =>

requestAnimationFrame(() =>

requestAnimationFrame(resolve)

)

);

and/or force layout through:

area.offsetHeight;

before:

window.print();

This is important for Chrome print preview.

\==================================================

DO NOT REMOVE PRINT DOM TOO EARLY

\==================================================

Current implementation performs cleanup in \`finally\` immediately around

window.print().

Make cleanup reliable using the browser print lifecycle.

Use:

window.addEventListener('afterprint', cleanup, { once: true });

The temporary print DOM and:

html.printing-thermal

must remain active until print preview/printing has actually finished.

Also provide a safe fallback cleanup timer if necessary.

Do NOT delete:

#thermal-print-area

before Chrome has generated the preview.

\==================================================

PRINT CSS MUST TARGET CURRENT RECEIPTS

\==================================================

Inside @media print, explicitly support:

#thermal-print-area .rc

#thermal-print-area .rc-workshop

and their children.

Do NOT depend on \`.slip\` existing.

Required:

#thermal-print-area {

display: block !important;

visibility: visible !important;

}

#thermal-print-area,

#thermal-print-area \* {

visibility: visible !important;

}

#thermal-print-area .rc,

#thermal-print-area .rc-workshop {

display: block !important;

visibility: visible !important;

width: 72mm or the currently approved thermal printable width;

max-width: 72mm;

height: auto !important;

overflow: visible !important;

margin: 0 !important;

background: #fff !important;

}

IMPORTANT:

Use the CURRENT approved physical print width already used by this application.

Do not arbitrarily redesign the receipt to a different width.

\==================================================

HIDE APP, NOT PRINT CONTENT

\==================================================

During thermal print:

everything outside:

#thermal-print-area

may be hidden.

BUT make absolutely sure no rule causes the cloned receipt or any of its

children to inherit:

display:none

visibility:hidden

opacity:0

The print area itself and ALL children must remain printable.

Test CSS specificity carefully.

\==================================================

PRINT BOTH

\==================================================

When user clicks:

Print both

both receipts must be present in the actual print DOM:

Customer Copy

Workshop Copy

Do not accidentally overwrite one clone with another.

Do not create a blank leading page.

Do not create an empty thermal roll before the receipt.

\==================================================

PRINT CUSTOMER ONLY

\==================================================

Customer only:

must print exactly the Customer Copy visible in the modal.

No blank page.

\==================================================

PRINT WORKSHOP ONLY

\==================================================

Workshop only:

must print exactly the Workshop Copy visible in the modal including:

Order

Booked

Customer

Tailor

Garments

Total Pieces

Delivery

Measurements

Instructions

Not a Bill

No blank page.

\==================================================

ASYNC MEASUREMENT LOADING

\==================================================

There is another important race in:

openReceipt()

The receipt modal opens first and then:

Atelier.api.get(ROUTES.receipt(dbId))

loads the workshop measurements asynchronously.

Before printing Workshop only or Print both:

make sure the server receipt payload has finished populating:

#job-measure-body

Do NOT print:

Loading measurements…

and do not print a stale job card.

Best implementation:

store a receipt-loading promise/state.

If Workshop or Both is clicked while measurements are still loading:

wait for that existing receipt request to settle before cloning.

Do NOT issue unnecessary duplicate API requests.

Customer-only printing can remain immediately available because it does not

depend on measurement loading.

\==================================================

DO NOT USE SCREENSHOT/CANVAS PRINTING

\==================================================

Do NOT rasterize the receipt.

No:

html2canvas

canvas screenshot

image conversion

Keep it normal HTML/CSS thermal printing so text remains sharp.

\==================================================

MEASUREMENT VALUE LENGTH

\==================================================

Compound values can be longer than old numeric values.

Example:

56.50-34.40

Keep the current TWO-COLUMN Workshop measurement layout.

Do NOT change to one-column.

Only make each measurement value fit safely.

Current classes:

.rc-workshop .rc-ms

.m-row

.m-lbl

.m-val

must continue using the same visual design.

Allow \`.m-val\` enough room for:

56.50-34.40

without overlapping the label.

If required, use:

min-width: 0

overflow-wrap / font sizing already approved

but DO NOT globally shrink the current receipt fonts.

Prefer fixing grid sizing/gap rather than reducing text size.

\==================================================

VERY IMPORTANT — CURRENT SCREEN DESIGN

\==================================================

The screenshot preview is already good.

DO NOT change:

font size

font family

font weight

measurement ordering

label ordering

black/dark appearance

receipt header

receipt address

customer receipt

Workshop layout

padding

borders

section headings

Only fix:

A) exact measurement values

B) blank physical print preview

\==================================================

TEST EXACTLY

\==================================================

TEST 1

Save:

Shoulder = 56.50-34.40

Database must contain exactly:

56.50-34.40

TEST 2

Open Workshop Copy.

Expected:

Shoulder 56.50-34.40

NOT:

56.5

TEST 3

Save:

Cuff = 000

Workshop Copy must show:

Cuff 000

TEST 4

Save:

Elbow = 0.00

Workshop Copy must show:

Elbow 0.00

TEST 5

Click:

Customer only

Chrome print preview must contain the complete Customer Copy.

TEST 6

Click:

Workshop only

Chrome print preview must contain the complete Workshop Copy.

TEST 7

Click:

Print both

Chrome print preview must contain BOTH copies.

TEST 8

Open Chrome at:

100% zoom

repeat all three print actions.

TEST 9

Open Chrome at:

90% zoom

repeat all three print actions.

Browser zoom must not produce a blank print.

TEST 10

Cancel the print dialog.

The application must return normally.

There must be no leftover:

#thermal-print-area

and no leftover:

html.printing-thermal

TEST 11

Print again immediately after canceling.

It must still work.

TEST 12

Use an order where Workshop measurements are still loading.

Click Workshop print immediately.

The code must wait for measurement loading and print the completed Workshop

Copy, not a blank/stale receipt.

\==================================================

IMPORTANT MIGRATION CHECK

\==================================================

Also verify:

php artisan migrate:status

The migration:

2026\_09\_17\_144148\_convert\_measurements\_to\_string

must show:

Ran

If it is pending, run it.

Do NOT create another duplicate migration unless the existing migration itself

is genuinely defective.

\==================================================

FINAL REPORT

\==================================================

After implementation report:

1\. Exact root cause of compound value truncation.

2\. Exact change made in measurementRows().

3\. Confirm 56.50-34.40 remains exact.

4\. Confirm 000 remains exact.

5\. Migration status.

6\. Exact root cause of blank Chrome print preview.

7\. Exact print lifecycle change.

8\. Confirmation Customer only prints.

9\. Confirmation Workshop only prints.

10\. Confirmation Print both prints.

11\. Confirmation Chrome 100% zoom tested.

12\. Confirmation Chrome 90% zoom tested.

13\. Confirmation no receipt visual design was changed.

FINAL RULE:

DO NOT CHANGE ANYTHING ELSE.