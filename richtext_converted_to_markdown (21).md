FIX ONLY THESE TWO WORKSHOP RECEIPT PRINT ISSUES.

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

Reference:

Use the supplied screenshot and ttt.pdf.

IMPORTANT:

DO NOT redesign the receipt.

DO NOT change receipt content.

DO NOT change measurement values.

DO NOT change measurement order.

DO NOT change Customer Copy.

DO NOT change database.

DO NOT change font sizes globally.

There are ONLY TWO issues:

1\. Compound measurement values such as:

34.50-45.50

56.50-34.40

2-45.50

are overlapping their labels.

2\. A long Workshop receipt is being split into multiple PDF/print pages.

The client wants ONE continuous thermal page regardless of receipt height.

\==================================================

ISSUE 1 — COMPOUND VALUE OVERLAP

\==================================================

Current Workshop measurement layout is two columns.

Example current broken output:

Length34.50-45.50 Shoulder 984.00

The value overlaps the "Length" label.

Other affected examples:

Waist 6-45.50

Koni 6-45.50

Pancho 2-45.50

This happens because a compound measurement can be much wider than the old

single numeric values.

DO NOT truncate the value.

DO NOT show ellipsis.

DO NOT convert:

34.50-45.50

into:

34.50

The complete value must remain visible.

\==================================================

KEEP TWO-COLUMN MEASUREMENT DESIGN

\==================================================

Keep:

LEFT measurement | RIGHT measurement

Do NOT convert the whole receipt to one-column measurements.

However each individual measurement cell must be ADAPTIVE.

For normal short values:

Length 34.50

keep the existing horizontal presentation.

For LONG / COMPOUND values:

Length

34.50-45.50

allow the value to move cleanly onto its OWN LINE inside that same measurement

cell.

This is the safest design for 72mm thermal width.

Example desired:

Length Shoulder 984.00

34.50-45.50

Sleeves 646.00 Collar 423.00

Chest 93.00 Chest Losing 127.00

Waist Waist Losing 642.00

6-45.50

...

Pancho

2-45.50

NO overlap.

\==================================================

BEST IMPLEMENTATION

\==================================================

In Workshop measurement rendering, detect long/compound values.

A value can be considered long if for example:

String(value).includes('-')

OR

String(value).length > a safe threshold.

Add a class such as:

m-long

Concept:

Length

34.50-45.50

For normal values keep existing:

m-row

\==================================================

CSS FOR NORMAL VALUE

\==================================================

Normal values can remain:

.m-row {

display: grid;

grid-template-columns: minmax(0, 1fr) auto;

align-items: baseline;

column-gap: 5px;

min-width: 0;

}

\==================================================

CSS FOR LONG / COMPOUND VALUE

\==================================================

For \`.m-row.m-long\` use a stacked layout inside its HALF column.

Concept:

.rc-workshop .rc-ms .m-row.m-long {

grid-template-columns: minmax(0, 1fr);

row-gap: 2px;

}

.rc-workshop .rc-ms .m-row.m-long .m-lbl {

grid-column: 1;

}

.rc-workshop .rc-ms .m-row.m-long .m-val {

grid-column: 1;

justify-self: end;

text-align: right;

width: 100%;

max-width: 100%;

white-space: nowrap;

}

The compound number must stay entirely within its own measurement cell.

DO NOT allow it to enter the next measurement column.

\==================================================

PAIR ROW HEIGHT

\==================================================

Because one side may become two lines, make the overall CSS grid naturally

expand that row.

Do NOT absolutely position anything.

Do NOT use negative margins.

Do NOT use transforms to force the value into place.

The next measurement row should start below the tallest cell naturally.

\==================================================

NO GLOBAL FONT-SIZE CHANGE

\==================================================

Current measurement font sizes are approved.

Do NOT globally reduce:

labels

digits

headers

instructions

If absolutely necessary for an exceptionally long compound value, a very small

Workshop-only fallback for ONLY \`.m-long .m-val\` is acceptable, but FIRST solve

it by stacking.

Preferred solution:

STACK LONG VALUE

not:

SHRINK EVERYTHING.

\==================================================

ISSUE 2 — RECEIPT MUST NEVER SPLIT INTO PAGE 1 / PAGE 2

\==================================================

The supplied ttt.pdf proves the problem:

Page 1 contains most of the Workshop Copy.

Page 2 contains only the remaining Instructions and bottom receipt content.

THIS IS NOT ACCEPTABLE.

This is a thermal roll.

The receipt can be:

300mm

400mm

500mm

600mm

or longer

That is fine.

The client wants ONE CONTINUOUS PAGE.

Do NOT split the Workshop receipt because it becomes long.

\==================================================

CURRENT PRINT CSS

\==================================================

The current application uses a named thermal page approximately like:

@page thermal80 {

size: auto;

margin: 0;

}

and:

html.printing-thermal {

page: thermal80;

}

\`size:auto\` still allows Chrome / PDF / printer page pagination.

For a long receipt this produces multiple pages.

\==================================================

REQUIRED SOLUTION — DYNAMIC PAGE HEIGHT

\==================================================

Before calling:

window.print()

calculate the ACTUAL height of the complete cloned thermal print content.

Then dynamically inject an @page rule with:

80mm width

+

the measured content height

Example concept:

const pxToMm = px => px \* 25.4 / 96;

const contentHeightPx = area.scrollHeight;

const contentHeightMm =

Math.ceil(pxToMm(contentHeightPx)) + safeBottomPadding;

Then dynamically create:

</p><p class="slate-paragraph">@media print {</p><p class="slate-paragraph"> @page thermal80 {</p><p class="slate-paragraph"> size: 80mm 487mm;</p><p class="slate-paragraph"> margin: 0;</p><p class="slate-paragraph"> }</p><p class="slate-paragraph">}</p><p class="slate-paragraph">

The \`487mm\` is only an example.

It MUST be calculated from actual content.

\==================================================

DO NOT HARDCODE PAGE HEIGHT

\==================================================

DO NOT set:

300mm

500mm

1000mm

permanently.

Different orders have different heights.

Calculate it every time Print is clicked.

Examples:

small receipt:

80mm × 210mm

long receipt:

80mm × 430mm

very long instructions:

80mm × 620mm

Each print job gets the height it actually needs.

\==================================================

VERY IMPORTANT — MEASURE AFTER CONTENT IS READY

\==================================================

For Workshop Copy:

wait until measurements have loaded.

Then:

1\. clone requested receipt(s)

2\. append them to #thermal-print-area

3\. make print area measurable

4\. wait for fonts/images/layout

5\. force layout

6\. calculate actual scrollHeight

7\. inject dynamic @page height

8\. call window.print()

Do NOT calculate height while:

display:none

because scrollHeight can become incorrect.

If necessary make the measuring print root:

position:absolute;

left:-10000px;

top:0;

visibility:hidden;

display:block;

during measurement.

Then switch to normal print state before window.print().

\==================================================

PRINT ONE RECEIPT

\==================================================

For:

Customer only

calculate Customer Copy total height and create ONE page.

For:

Workshop only

calculate full Workshop Copy height and create ONE page.

The entire:

Instructions

Not a Bill

Order number footer

must remain on that same page.

\==================================================

PRINT BOTH

\==================================================

For:

Print both

place:

Customer Copy

then

Workshop Copy

sequentially inside the SAME thermal print area.

Calculate the TOTAL combined height.

Create ONE continuous page long enough for BOTH.

Do NOT create:

Customer Copy = page 1

Workshop Copy = page 2

unless the client explicitly requests separate cutting later.

Current requirement:

ONE continuous print job / page.

\==================================================

REMOVE PAGE-BREAK CONFLICTS

\==================================================

Inside thermal print mode make sure receipt elements do NOT force pagination.

Use appropriately:

break-before: auto !important;

break-after: auto !important;

page-break-before: auto !important;

page-break-after: auto !important;

and:

break-inside: avoid

only for SMALL logical blocks such as:

Deliver By box

measurement row

status box

DO NOT put:

break-inside: avoid

on the entire very large receipt or Instructions box if it can exceed available

space.

The dynamic page itself must be tall enough to contain everything.

\==================================================

INSTRUCTIONS BOX

\==================================================

Long Instructions must remain ONE continuous box.

The box can become tall.

Example:

INSTRUCTIONS

\--------------------------------

Goll Daman xx,

Sherwani Goll 1,

Gheer Full Bara,

Double Kantii,

...

\--------------------------------

Do NOT push half of this box to page 2.

Do NOT limit its height.

Use:

height:auto

max-height:none

overflow:visible

during print.

\==================================================

PRINT CLEANUP

\==================================================

After printing/cancel:

remove:

#thermal-dynamic-page-size

#thermal-print-area

html.printing-thermal

using \`afterprint\`.

Also keep a safe fallback cleanup.

The next print must calculate a fresh page height.

\==================================================

PDF TEST

\==================================================

Use the same order shown in supplied ttt.pdf.

It currently generates 2 PDF pages.

After fix:

Save as PDF.

Expected:

1 PAGE ONLY.

That one page should simply be physically taller.

It must contain from:

BEST TAILOR

all the way through:

\* ORD-1079 \*

on a single continuous page.

\==================================================

MEASUREMENT TEST

\==================================================

Test:

Length = 34.50-45.50

Waist = 6-45.50

Koni = 6-45.50

Pancho = 2-45.50

Expected:

NO overlap.

NO text on top of another text.

NO clipping.

NO missing hyphen value.

NO horizontal overflow.

\==================================================

DO NOT CHANGE

\==================================================

Do NOT change:

\- receipt header

\- BEST TAILOR design

\- address

\- Workshop title

\- metadata

\- garment section

\- delivery box

\- measurement font sizes globally

\- measurement order

\- instructions content

\- Customer Copy design

\- colors

\- database

\- order workflow

\- measurement saving

\- Quick Actions

\- prices/payments

\- Final Receipt

\- Cloth Store receipt

\- sidebar/theme

\==================================================

FILES TO INSPECT

\==================================================

Main expected file:

resources/views/orders/index.blade.php

Also inspect:

resources/views/receipts/slip-styles.blade.php

but DO NOT globally change unrelated receipt printing.

\==================================================

FINAL RESULT

\==================================================

ISSUE 1:

34.50-45.50 and similar long measurements fit cleanly inside their own

measurement cell with NO overlap.

ISSUE 2:

No matter how long the Workshop receipt becomes, Chrome thermal print / Save as

PDF creates ONE continuous page, not page 1 + page 2.

DO NOT CHANGE ANYTHING ELSE.