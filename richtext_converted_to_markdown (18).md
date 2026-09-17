FIX ONLY RECEIPT TEXT CONTRAST + WORKSHOP MEASUREMENT ALIGNMENT.

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

Reference PDF:

tes.pdf

IMPORTANT:

Inspect the CURRENT latest repository first.

This task applies ONLY to the Orders thermal receipts:

1\. Booking Receipt · Customer Copy

2\. Workshop Copy

DO NOT redesign either receipt.

DO NOT change any font sizes.

DO NOT change receipt width.

DO NOT change content/data.

DO NOT change measurements logic.

DO NOT change print flow.

\==================================================

ISSUE 1 — LABEL TEXT IS TOO LIGHT

\==================================================

On the physical receipt, labels such as:

CUSTOMER COPY:

Order

Invoice

Date

Customer

Phone

Subtotal

Advance Paid

Balance

etc.

WORKSHOP COPY:

Order

Booked

Customer

Tailor

Total Pieces

etc.

are printing too light / faded.

They are difficult to read on thermal paper.

The CURRENT CSS uses gray colors such as:

#5c5c5c

#555

#5a5a5a

#666

#9a9a9a

for receipt labels/dotted leaders.

This is too light for the client's thermal printer.

\==================================================

REQUIRED CONTRAST FIX

\==================================================

For BOTH:

Customer Copy

Workshop Copy

make all important receipt labels solid/dark and clearly readable.

DO NOT increase their text size.

Keep current font sizes exactly as they are.

Use strong thermal-friendly contrast.

For example:

.rc-m .k

\=> color: #000;

\=> font-weight: 700;

.rc-tr .k

\=> color: #000;

\=> preserve current size

Workshop metadata labels:

Order

Booked

Customer

Tailor

\=> solid black

\=> stronger weight

\=> SAME SIZE

The goal is:

CURRENT:

ORDER ........ ORD-1076

(label looks gray/faded)

REQUIRED:

ORDER ........ ORD-1076

(label clearly black/readable)

\==================================================

DOTTED LEADERS

\==================================================

The dotted lines between labels and values are also too faint.

Example:

ORDER ..................... ORD-1076

Keep the exact dotted leader design but make it darker.

Do NOT make the line thicker/heavy.

Use a thermal-safe dark gray/black such as:

#555

or

#444

instead of very light:

#9a9a9a

#a5a5a5

The dots should remain visually secondary to text but must be printable.

\==================================================

CUSTOMER COPY — DARKEN ONLY

\==================================================

Customer Copy currently has Neo classes such as:

.rc-m .k

.rc-m .dots

.rc-i1

.rc-i2

.rc-tr

Make the currently faded text darker.

Important examples:

Order

Invoice

Date

Customer

Phone

Items secondary information if currently too gray:

quantity

"Rs.X each"

Payment labels:

Subtotal

Advance Paid

Balance

All must remain readable on thermal print.

BUT:

DO NOT make everything huge.

DO NOT alter hierarchy.

DO NOT change spacing.

DO NOT change Customer Copy design.

Only improve contrast/ink density.

\==================================================

WORKSHOP COPY — DARKEN ONLY

\==================================================

For Workshop Copy make:

Order

Booked

Customer

Tailor

Garment labels

Total Pieces label

Deliver By label

solid and clearly readable.

Again:

NO SIZE CHANGE.

The existing sizes are approved by the client.

Only fix the light/faded appearance.

\==================================================

ISSUE 2 — WORKSHOP MEASUREMENTS ALIGNMENT

\==================================================

The measurement TEXT SIZE and DIGIT SIZE are already correct.

DO NOT make them larger or smaller.

The issue is ALIGNMENT only.

Current Workshop Copy approximately uses:

.rc-ms {

display: grid;

grid-template-columns: 1fr 1fr;

}

.rc-ms .m-row {

display: flex;

}

.rc-ms .m-lbl {

width: 95px;

}

.rc-ms .m-val {

...

}

The fixed:

width: 95px

inside each half of a 72mm receipt is causing poor alignment and crowding.

Examples from physical receipt:

Length 771 Shoulder 257

Sleeves 832 Collar 702

Chest 683 Chest Losing 29

Waist 452 Waist Losing 823

Hip 281 Hip Losing 460

...

Salwar Length 46 Pancho 184

Long labels are too close to values or inconsistent.

\==================================================

REQUIRED MEASUREMENT ALIGNMENT

\==================================================

KEEP the existing 2-column measurement layout.

DO NOT convert measurements into one column.

Each measurement cell should internally align as:

LABEL VALUE

with the value consistently aligned to the RIGHT side of that cell.

Use a robust internal grid instead of fixed label width.

Recommended concept:

.rc-workshop .rc-ms {

display: grid;

grid-template-columns: repeat(2, minmax(0, 1fr));

column-gap: 10px;

row-gap: 10px;

}

.rc-workshop .rc-ms .m-row {

display: grid;

grid-template-columns: minmax(0, 1fr) auto;

align-items: baseline;

column-gap: 5px;

min-width: 0;

}

.rc-workshop .rc-ms .m-lbl {

width: auto;

min-width: 0;

white-space: normal;

overflow: visible;

text-overflow: clip;

line-height: 1.15;

}

.rc-workshop .rc-ms .m-val {

justify-self: end;

text-align: right;

white-space: nowrap;

min-width: 2.5ch;

}

Adapt exact spacing based on current 72mm printable width.

\==================================================

EXPECTED VISUAL RESULT

\==================================================

The two columns should visually align like:

Length 771 | Shoulder 257

Sleeves 832 | Collar 702

Chest 683 | Chest Losing 29

Waist 452 | Waist Losing 823

Hip 281 | Hip Losing 460

Galla 476 | F/Patti 978

Button 585 | Cuff 246

Koni 221 | Elbow 731

Armor 364 | Takki 611

Salwar Length 46 | Pancho 184

IMPORTANT:

Values should form a clean right edge inside each measurement column.

Labels should have enough space.

Long labels must NOT collide with numbers.

\==================================================

MEASUREMENT PAIRING

\==================================================

Preserve the current measurement order.

Especially preserve:

Chest | Chest Losing

Waist | Waist Losing

Hip | Hip Losing

Do NOT change measurement keys or values.

Do NOT swap data.

Do NOT alter saved measurements.

Only fix visual alignment.

\==================================================

TEXT SIZE LOCK

\==================================================

VERY IMPORTANT:

The client explicitly says the existing text sizes are PERFECT.

DO NOT change font-size for:

Customer Copy

Workshop Copy

Measurement labels

Measurement digits

Instructions

Headers

Shop name

Receipt title

Garments

Total Pieces

This task is NOT a font-size change.

ONLY:

1\. darker text / better thermal contrast

2\. better measurement alignment

\==================================================

INSTRUCTIONS BOX

\==================================================

Do NOT change the Instructions box.

Its current size/layout is approved.

Do NOT change:

instruction font size

box border

padding

text wrapping

unless a tiny alignment-only fix is required as a consequence of receipt width.

\==================================================

THERMAL PRINT

\==================================================

Physical paper:

80mm roll

approximately 72mm printable width

KEEP the current thermal architecture.

Do NOT change:

@page handling

#thermal-print-area

72mm printable width

printThermal()

Customer only

Workshop only

Print both

\==================================================

SCREEN + PRINT CONSISTENCY

\==================================================

The preview should look dark/readable.

The printed receipt must also print dark/readable.

If needed add a print-only safeguard such as:

#thermal-print-area .rc,

#thermal-print-area .rc \* {

print-color-adjust: exact;

\-webkit-print-color-adjust: exact;

}

But DO NOT globally affect the whole application.

Do not use opacity below 1 for receipt labels.

\==================================================

SCOPE CSS CAREFULLY

\==================================================

Prefer scoped rules.

Customer Copy:

.rc ...

Workshop-specific measurement fixes:

.rc-workshop .rc-ms ...

.rc-workshop .m-row ...

.rc-workshop .m-lbl ...

.rc-workshop .m-val ...

Do not change unrelated app text colors.

\==================================================

DO NOT CHANGE

\==================================================

Do NOT change:

\- actual receipt data

\- Order number

\- Invoice

\- Date

\- Customer

\- Tailor

\- garment names

\- quantities

\- measurements

\- measurement sizes

\- instructions

\- prices

\- totals

\- payment calculations

\- Workshop design

\- Customer Copy design

\- receipt width

\- fonts

\- database

\- Orders workflow

\- Final Receipt

\- Cloth Store receipts

\- Measurements page

\- delivery

\- staff

\- ledger

\- SMS

\==================================================

TEST

\==================================================

Test the exact receipt shown in the supplied PDF.

CUSTOMER COPY:

Order ORD-1076

Invoice INV-1076

Customer Ayaz Baloch

Verify:

Order / Invoice / Date / Customer / Phone labels print clearly black.

WORKSHOP COPY:

Order ORD-1076

Booked

Customer Ayaz Baloch

Tailor Vikram

Verify all labels are clearly readable.

Then verify measurements:

Length / 771

Shoulder / 257

Sleeves / 832

Collar / 702

Chest / 683

Chest Losing / 29

Waist / 452

Waist Losing / 823

Hip / 281

Hip Losing / 460

...

Salwar Length / 46

Pancho / 184

Verify:

\- values align consistently

\- no overlap

\- no clipping

\- long labels remain readable

\- both columns have clean alignment

\- receipt remains within 72mm

\- text sizes remain exactly unchanged

\==================================================

EXPECTED FILE

\==================================================

Primary expected change:

resources/views/orders/index.blade.php

If shared receipt styling is involved:

resources/views/receipts/slip-styles.blade.php

ONLY modify shared CSS if absolutely required and confirm it does not change

unrelated receipts.

\==================================================

FINAL RULE

\==================================================

FIX ONLY:

1\. FADED/LIGHT RECEIPT TEXT ON CUSTOMER + WORKSHOP COPIES

2\. WORKSHOP MEASUREMENT ALIGNMENT

KEEP EVERY FONT SIZE EXACTLY AS IT IS.

KEEP THE EXISTING DESIGN EXACTLY AS IT IS.

DO NOT CHANGE ANYTHING ELSE.