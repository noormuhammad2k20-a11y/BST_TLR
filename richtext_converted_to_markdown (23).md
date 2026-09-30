FIX ONLY THE WORKSHOP COPY MEASUREMENTS LAYOUT.

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

IMPORTANT:

Inspect the CURRENT latest repository first.

This task applies ONLY to:

WORKSHOP COPY receipt measurements section

DO NOT change:

\- Customer Copy

\- Final Receipt

\- Cloth Store receipt

\- receipt width

\- fonts

\- colors

\- header

\- metadata

\- garments

\- delivery box

\- instructions box

\- footer

\- measurement values

\- measurement order

\- print logic

\- one-page workshop behavior

\- any other functionality

\==================================================

CLIENT REQUIREMENT

\==================================================

The current Workshop Copy measurement pattern is not properly structured.

I want the measurements section to use this exact visual structure style:

────────── MEASUREMENTS (IN) ──────────

Length Shoulder

34.50-45.50 984.00

────────────────────────────────

Sleeves Collar

646.00 423.00

────────────────────────────────

Chest Chest Losing

93.00 127.00

────────────────────────────────

Waist Waist Losing

6-45.50 642.00

────────────────────────────────

Hip Hip Losing

857.00 316.00

────────────────────────────────

This is the pattern to follow for the FULL measurements section.

\==================================================

REQUIRED STRUCTURE

\==================================================

Use a "pair card style" layout.

Each measurement pair should be one block.

Inside each block:

Row 1:

LEFT LABEL RIGHT LABEL

Row 2:

LEFT VALUE RIGHT VALUE

Then below that pair block:

a full-width divider line

Example:

Length Shoulder

34.50-45.50 984.00

────────────────────────────────

Next:

Sleeves Collar

646.00 423.00

────────────────────────────────

\==================================================

APPLY TO ALL PAIRS

\==================================================

Use this same structure for all existing Workshop measurement pairs in their

current order.

Example expected sequence:

Length | Shoulder

Sleeves | Collar

Chest | Chest Losing

Waist | Waist Losing

Hip | Hip Losing

Galla | F/Patti

Button | Cuff

Koni | Elbow

Armor | Takki

Salwar Length | Pancho

Do NOT change the pair ordering.

\==================================================

VERY IMPORTANT — KEEP FONT SIZE SAME

\==================================================

The current Workshop receipt font sizes are already approved.

DO NOT increase font size.

DO NOT decrease font size.

Keep the SAME existing font sizes for:

\- measurement labels

\- measurement values

\- section heading

\- everything else

Only change the STRUCTURE / LAYOUT of the measurement blocks.

\==================================================

ALIGNMENT RULE

\==================================================

For each pair block:

\- left label aligned left

\- right label aligned left inside its right column

\- left value aligned left under its label

\- right value aligned left under its label

\- both columns should have equal width

\- enough spacing between left and right columns

\- values must never overlap labels

\- compound values like:

56.50-34.40

6-45.50

2-45.50

must fit cleanly

\==================================================

DIVIDER STYLE

\==================================================

After every pair block add one clean horizontal divider.

The divider should:

\- span across the measurement area width

\- match the existing receipt style

\- be subtle but clearly visible

\- not be too thick

\- not look like a table border

\- just a neat separator line

This should create a clean stacked-pair appearance.

\==================================================

NO TABLE LOOK

\==================================================

Do NOT make it a bordered HTML table.

Do NOT add boxes around every cell.

Do NOT create a spreadsheet look.

This should remain a clean thermal receipt style, only more organized.

\==================================================

NO OVERLAP

\==================================================

This is critical.

Current issues include values visually colliding or looking uneven.

Examples:

\- 34.50-45.50

\- 6-45.50

\- 2-45.50

In the new structure, long values must remain fully visible and never overlap

with labels or the next column.

\==================================================

SUGGESTED IMPLEMENTATION

\==================================================

Best approach:

Render the measurements as stacked pair blocks instead of the current tight row

pattern.

Example concept:

Length

Shoulder

34.50-45.50

984.00

Sleeves

Collar

646.00

423.00

You may name classes differently, but structure should behave like this.

\==================================================

CSS BEHAVIOR

\==================================================

Use a clean 2-column grid/flex layout inside each pair.

Example concept:

.ms-pair {

display: grid;

row-gap: 3px;

}

.ms-labels,

.ms-values {

display: grid;

grid-template-columns: 1fr 1fr;

column-gap: 14px;

}

.ms-divider {

border-top: 1px dashed or solid appropriate to current style;

margin: 6px 0;

}

Keep exact font sizes same as current system.

\==================================================

HEADING

\==================================================

Keep the measurements heading exactly in the same style already being used:

MEASUREMENTS

(IN)

No need to redesign that heading.

Only change the layout below it.

\==================================================

WORKSHOP ONLY

\==================================================

Apply this ONLY to the Workshop Copy measurements section.

Do NOT apply this pattern to:

\- Customer Copy

\- Measurement page

\- Order step inputs

\- printed measurement sheet elsewhere

\- any other receipt

\==================================================

EXPECTED RESULT

\==================================================

The Workshop Copy measurements section should visually read like:

Length Shoulder

34.50-45.50 984.00

\--------------------------------

Sleeves Collar

646.00 423.00

\--------------------------------

Chest Chest Losing

93.00 127.00

\--------------------------------

Waist Waist Losing

6-45.50 642.00

\--------------------------------

Hip Hip Losing

857.00 316.00

\--------------------------------

Galla F/Patti

704.00 846.00

\--------------------------------

Button Cuff

295.00 810.00

\--------------------------------

Koni Elbow

6-45.50 15.00

\--------------------------------

Armor Takki

536.00 462.00

\--------------------------------

Salwar Length Pancho

388.00 2-45.50

\==================================================

DO NOT CHANGE

\==================================================

Do NOT change:

\- measurement data source

\- pairing logic

\- print page logic

\- one-page workshop print

\- customer receipt

\- receipt theme

\- text sizes

\- backend

\- database

\- saved measurement logic

\- order logic

\==================================================

TEST

\==================================================

Test with compound values such as:

Length = 34.50-45.50

Waist = 6-45.50

Koni = 6-45.50

Pancho = 2-45.50

Expected:

\- no overlap

\- clean structured blocks

\- same font sizes as before

\- workshop receipt still prints correctly

FINAL RULE:

ONLY RESTRUCTURE THE WORKSHOP COPY MEASUREMENTS INTO PAIR BLOCK STYLE.

KEEP THE FONT SIZE EXACTLY THE SAME.

DO NOT CHANGE ANYTHING ELSE.