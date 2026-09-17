TASK: REPLACE ONLY THE EXISTING WORKSHOP COPY RECEIPT DESIGN.

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

FIRST deeply inspect the CURRENT latest repository.

The current Order receipt system is already working.

DO NOT rebuild receipt functionality.

DO NOT change Customer Copy.

DO NOT change Final Receipt.

DO NOT change Cloth Store receipts.

DO NOT change order workflow.

DO NOT change measurements saving.

DO NOT change database.

DO NOT change printing behavior.

ONLY replace the visual design of:

WORKSHOP COPY

with the exact Neo Workshop design supplied below.

\==================================================

CRITICAL CURRENT INTEGRATION

\==================================================

The Workshop receipt is part of the existing Orders receipt modal.

The existing print system expects the Workshop Copy root element:

id="slip-tailor"

KEEP THIS ID EXACTLY.

DO NOT rename or remove it.

The current print functions must continue working:

printThermal('customer')

printThermal('tailor')

printThermal('both')

Expected:

Customer only

\=> existing Customer Copy, unchanged

Workshop only

\=> NEW Workshop Copy design

Print both

\=> existing Customer Copy + NEW Workshop Copy

\==================================================

DO NOT CREATE A NEW STANDALONE HTML PAGE

\==================================================

The supplied HTML below is a VISUAL REFERENCE.

DO NOT add:

inside the Laravel modal.

DO NOT create a new route/page.

Instead:

\- extract/adapt the required Neo Workshop CSS

\- integrate it inside the current \`resources/views/orders/index.blade.php\`

\- replace only the existing \`tailorSlip\` / Workshop Copy markup

\- preserve current order receipt modal

\- preserve current asynchronous receipt-data loading

\- preserve current physical printing flow

\==================================================

CUSTOMER COPY LOCK

\==================================================

The normal Customer Copy has already been redesigned with the Neo \`.rc\` design.

DO NOT change it.

Do not alter:

id="slip-customer"

Do not change its:

\- Items

\- Payment

\- Total

\- Delivery

\- developer credit

\- fonts

\- spacing

\- data

\- print behavior

This task is ONLY Workshop Copy.

\==================================================

WORKSHOP ROOT

\==================================================

Use a root similar to:

or another safely scoped equivalent.

Because \`.rc\` is already used by the Customer Copy, use:

.rc-workshop

for all Workshop-only differences.

Do NOT let Workshop CSS accidentally alter Customer Copy.

\==================================================

USE THE SAME NEO VISUAL LANGUAGE

\==================================================

The Workshop Copy must visually match the supplied design:

\- IBM Plex Mono

\- Space Grotesk

\- premium black/white thermal style

\- large shop name

\- Tailoring & Cloth House separator

\- shop address

\- phone

\- centered WORKSHOP COPY title

\- dotted leader metadata

\- clean GARMENTS section

\- each garment on its own row

\- dotted line between garment name and quantity

\- Total Pieces

\- bordered Deliver By box

\- two-column measurements grid

\- bordered Instructions box

\- final "NOT A BILL — WORKSHOP USE ONLY" box

Do not improvise a different receipt design.

\==================================================

DYNAMIC DATA — DO NOT HARDCODE SAMPLE DATA

\==================================================

The supplied reference contains sample:

ORD-1074

Waqas

Usman Raza

Boski

Cotton

Wash & Wear

etc.

DO NOT hardcode any sample value.

Use the CURRENT real \`o\` / receipt response data.

Conceptual mapping:

WORKSHOP.no

\=> current order number

WORKSHOP.booked

\=> current booking/order date

WORKSHOP.customer

\=> current customer name

WORKSHOP.tailor

\=> current assigned tailor

WORKSHOP.garments

\=> current real order garments/items

WORKSHOP.pieces

\=> actual total quantity

WORKSHOP.deliverBy

\=> current delivery date + time slot

WORKSHOP.measurements

\=> current shared measurement set

WORKSHOP.instructions

\=> current Special Instructions / Notes

\==================================================

IMPORTANT — CURRENT SHARED MEASUREMENT FLOW

\==================================================

The client has already changed the business requirement:

ONE CUSTOMER

\=

ONE SHARED MEASUREMENT SET

\=

ALL GARMENTS IN THAT ORDER USE THE SAME MEASUREMENTS

PRESERVE THIS.

DO NOT return to separate measurements for:

Boski

Cotton

Wash & Wear

Workshop Copy must show:

MEASUREMENTS · IN

only ONCE.

Example:

GARMENTS

Boski ........................ × 1

Cotton ....................... × 2

Wash & Wear .................. × 2

TOTAL PIECES ................. 5

\[ DELIVER BY · Sep 16 · 8:47 PM \]

\------------ MEASUREMENTS · IN ------------

Length ................. 253

Shoulder ............... 639

Sleeves ................ 216

Collar ................. 378

Chest .................. 493

Chest Losing ........... 137

Waist .................. 302

Waist Losing ........... 735

Hip .................... 459

Hip Losing ............. 888

...

\[ INSTRUCTIONS

Customer instruction...

\]

\[ NOT A BILL — WORKSHOP USE ONLY \]

Only ONE measurement block.

\==================================================

MEASUREMENT ORDER

\==================================================

Preserve the recently approved logical measurement order.

Important paired values:

Chest

Chest Losing

Waist

Waist Losing

Hip

Hip Losing

They must remain clearly associated.

Use the current real measurement data.

Never invent measurement values.

Only render measurements that exist / are relevant according to the current

receipt payload.

\==================================================

MEASUREMENT READABILITY — VERY IMPORTANT

\==================================================

The stitching tailors have eyesight difficulty.

The current Workshop Copy was intentionally made more readable.

DO NOT make measurements tiny simply because the reference sample uses smaller

font sizes.

Preserve the NEW Neo appearance but keep Workshop measurements highly readable

on physical thermal paper.

Requirements:

\- measurement labels clearly readable

\- measurement DIGITS slightly larger/bolder than labels

\- pure black thermal text

\- no gray/faded values on physical print

\- instructions large enough to read easily

\- do not compress measurements until they become difficult to see

Within the Neo design, increase Workshop-only measurement sizes where required.

The customer receipt must remain unchanged.

\==================================================

GARMENTS

\==================================================

Render EACH garment separately.

Example:

Boski ............................. × 1

Cotton ............................ × 2

Wash & Wear ....................... × 2

Do NOT create:

Garment 1 × Boski, 2 × Cotton, 2 × Wash & Wear

as one badly wrapped line.

Do NOT duplicate garment details inside the Measurements section.

Garments should appear ONLY in the GARMENTS section.

Then:

TOTAL PIECES ...................... 5

\==================================================

LONG GARMENT NAMES

\==================================================

Support long names safely.

Garment name and its quantity must remain understandable.

Never allow:

× 2

to become visually associated with the wrong garment.

No clipping.

No overlap.

No horizontal overflow.

\==================================================

DELIVER BY

\==================================================

Use the exact bordered Neo status style:

DELIVER BY · actual date/time

Use current delivery date and existing time slot.

Do not calculate a new delivery date.

\==================================================

INSTRUCTIONS

\==================================================

Use the current saved:

Special Instructions / Notes

Print them only ONCE.

Do not duplicate the same note above Measurements and again under Instructions.

Required:

\[ Instructions \]

actual unique customer stitching instruction

If empty, either omit the box or show a clean existing fallback according to

current receipt behavior.

Do NOT repeat the placeholder text.

\==================================================

TAILOR

\==================================================

Display the actual currently assigned tailor.

Example:

TAILOR ...................... USMAN RAZA

If no tailor assigned, preserve the current appropriate fallback.

Do not hardcode a tailor.

\==================================================

PRIORITY / URGENT

\==================================================

If the current Workshop Copy already communicates a real High/Urgent priority,

DO NOT silently lose that business information.

Integrate it minimally into the Neo design using the same black/white visual

language, for example a compact bordered status box.

Do not redesign the receipt around it.

If priority is normal/not applicable, do not add unnecessary noise.

\==================================================

SHOP DATA

\==================================================

Use existing dynamic receipt/shop configuration:

shop/store name

tagline

address

phone

Do NOT hardcode shop values if the current receipt already obtains them from

settings.

The sample values only show the expected visual design.

\==================================================

NO CUSTOMER FINANCIAL DATA

\==================================================

Workshop Copy is NOT a bill.

DO NOT add:

\- prices

\- subtotal

\- total

\- advance

\- balance

\- customer ledger

\- previous due

\- payment details

Workshop Copy should contain only workshop-required information.

\==================================================

NO CUSTOMER PHONE IF CURRENT PRIVACY RULE EXCLUDES IT

\==================================================

If the existing Workshop Copy intentionally does not print the customer's phone

for workshop privacy, preserve that behavior.

Do not add phone merely because it exists in the customer object.

\==================================================

IMPORTANT ASYNC MEASUREMENT FLOW

\==================================================

The current order receipt opens immediately and then receives measurement data

from the server.

DO NOT break this.

Current logic includes a function such as:

fillJobCard(...)

which fills the Workshop measurement information after the receipt response

arrives.

You may minimally update \`fillJobCard()\` to render the new Neo markup.

But preserve:

\- asynchronous loading

\- order modal opening

\- measurement response

\- error handling

\- print flow

If the current code relies on IDs such as:

job-tailor

job-measure-body

job-measure-unit

job-notes

either KEEP those IDs in the new markup

OR update only the minimum corresponding Workshop-specific code safely.

Do not make unrelated changes.

\==================================================

CSS NAMESPACE

\==================================================

The current Customer Copy already uses \`.rc\` classes.

Reuse shared Neo CSS where safe.

Add only Workshop-specific classes such as:

.rc-workshop

.rc-g

.rc-gs

.rc-ms

.rc-inst

Do not duplicate hundreds of identical CSS declarations unnecessarily if the

same \`.rc\` styles already exist.

But the final Workshop design must visually match the supplied reference.

\==================================================

GLOBAL CSS SAFETY

\==================================================

DO NOT paste these standalone rules directly:

\* { margin:0; padding:0; }

body { ... }

@page { size:80mm auto; }

into the existing application globally.

They can break the entire app/print system.

The current project already has a working thermal print architecture.

Keep:

\- \`html.printing-thermal\`

\- \`#thermal-print-area\`

\- existing named thermal page

\- existing 72mm printable canvas

\- existing cloning behavior

\- existing image/load behavior

\- current printer compatibility

Adapt the Neo Workshop receipt INSIDE that system.

\==================================================

PHYSICAL PAPER

\==================================================

Physical paper is 80mm.

The current project deliberately uses approximately 72mm printable content

inside the 80mm thermal roll.

KEEP this.

Do NOT change the whole thermal system to literal \`80mm\` just because the

standalone sample says:

.rc { width:80mm }

Use:

width:100%

inside the existing 72mm thermal print root as appropriate.

\==================================================

WORKSHOP PRINT ROOT

\==================================================

Final root MUST still be identifiable by:

id="slip-tailor"

Example concept:

Do NOT use:

id="rc"

in production.

\==================================================

SCREEN PREVIEW

\==================================================

The Workshop Copy preview inside the current receipt modal should also show the

new design.

It may have the subtle screen-only receipt border.

Do not alter modal controls.

Keep buttons:

Close

Customer only

Workshop only

Print both

exactly working.

\==================================================

REFERENCE DESIGN

\==================================================

Use the exact Workshop Neo HTML/CSS I provide below as the VISUAL SOURCE OF

TRUTH.

IMPORTANT:

DO NOT retain:

const WORKSHOP = {...}

in production.

DO NOT retain:

render()

document.getElementById('rc')

as a separate standalone receipt implementation.

Convert/adapt it into the current Laravel/JavaScript modal template using real

existing order data.

\----- PASTE MY EXACT WORKSHOP HTML/CSS REFERENCE HERE -----

\[PASTE THE WORKSHOP CODE FROM THIS MESSAGE HERE\]

\----- END REFERENCE -----

\==================================================

EXPECTED PRODUCTION STRUCTURE

\==================================================

Conceptually the Workshop template should look like:

Dynamic Shop Name

Dynamic Tagline

Dynamic Address

Dynamic Phone

divider

WORKSHOP COPY

Order ................. ORD-xxxx

Booked ................ real date

Customer .............. real customer

Tailor ................ real tailor

GARMENTS

Boski ......................... × 1

Cotton ........................ × 2

Wash & Wear ................... × 2

Total Pieces .................. 5

\[ DELIVER BY · actual date/time \]

MEASUREMENTS · IN

\[one shared two-column measurement grid\]

\[ INSTRUCTIONS

actual note

\]

\[ NOT A BILL — WORKSHOP USE ONLY \]

\==================================================

DO NOT ADD SOFTWARE CREDIT TO WORKSHOP COPY

\==================================================

The supplied Workshop design intentionally ends with:

NOT A BILL — WORKSHOP USE ONLY

Keep it that way.

Do NOT add:

Designed & Developed by

software support

THANK YOU

customer billing footer

to Workshop Copy unless it already explicitly belongs to this supplied Workshop

reference.

\==================================================

DO NOT CHANGE

\==================================================

Absolutely DO NOT change:

\- Customer Copy design

\- Final Receipt design

\- Cloth Store receipts

\- customer prices

\- payments

\- ledger

\- order totals

\- measurements database

\- shared-measurement business rule

\- customer workflow

\- delivery

\- SMS

\- Tailor wage calculations

\- Staff module

\- Products/Stitching Rates

\- Sidebar

\- Dashboard

\- global theme

\- authentication

\==================================================

TEST THESE CASES

\==================================================

1\. One garment:

Boski × 1

2\. Multiple garments:

Boski × 1

Cotton × 2

Wash & Wear × 2

3\. Long garment names.

4\. Quantity greater than 1.

5\. One shared measurement set.

6\. Chest + Chest Losing.

7\. Waist + Waist Losing.

8\. Hip + Hip Losing.

9\. Long Special Instructions.

10\. No Instructions.

11\. High/Urgent order if current priority exists.

12\. Assigned tailor.

13\. Unassigned tailor.

14\. Customer-only print:

must remain unchanged.

15\. Workshop-only print:

new Neo Workshop Copy only.

16\. Print both:

existing Customer Copy + new Workshop Copy.

17\. Physical 80mm thermal / current 72mm printable canvas.

Verify:

\- no clipping

\- no horizontal overflow

\- garment/quantity association stays clear

\- measurement digits remain easy to read

\- one measurement block only

\- instruction appears once

\- no customer prices appear

\- no duplicated data

\- Customer Copy unchanged

\==================================================

AFTER IMPLEMENTATION REPORT

\==================================================

Report:

1\. Exact files changed.

2\. Exact old Workshop markup replaced.

3\. Exact new Workshop-specific CSS added.

4\. How real order data maps into the new design.

5\. How one shared measurement set is rendered.

6\. Confirmation measurement digits remain large/readable.

7\. Confirmation instructions render once.

8\. Confirmation Customer Copy was untouched.

9\. Confirmation Final Receipt was untouched.

10\. Confirmation printThermal('customer') still works.

11\. Confirmation printThermal('tailor') works with new design.

12\. Confirmation printThermal('both') works.

13\. Tests run and results.

FINAL RULE:

REPLACE ONLY THE EXISTING WORKSHOP COPY VISUAL DESIGN WITH THE PROVIDED NEO

WORKSHOP DESIGN.

KEEP ALL EXISTING REAL DATA, SHARED MEASUREMENTS, PRINTING, ORDER LOGIC AND

CUSTOMER RECEIPTS UNCHANGED.

DO NOT CHANGE ANYTHING ELSE.