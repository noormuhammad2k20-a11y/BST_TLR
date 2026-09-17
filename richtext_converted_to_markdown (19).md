TASK: FIX THE ENTIRE MEASUREMENT VALUE STORAGE PIPELINE.

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

IMPORTANT:

FIRST deeply inspect the CURRENT latest repository.

There are TWO related problems:

1\. Some valid measurement values such as:

Cuff

Koni

Elbow

Armor

Takki

etc.

can appear blank / "—" after saving, especially when the entered value is

zero-like such as:

0

00

000

0.00

2\. The client now needs tailoring measurements to support compound notation such

as:

56.50-34.40

This is NOT subtraction.

It is a tailoring measurement notation containing TWO values separated by a

hyphen.

Example:

56.50-34.40

must be stored literally and printed literally as:

56.50-34.40

\==================================================

CURRENT ARCHITECTURE PROBLEM

\==================================================

Currently measurements are treated as purely numeric throughout the system.

Inspect especially:

app/Models/Measurement.php

app/Http/Controllers/MeasurementController.php

app/Http/Requests/StoreOrderRequest.php

app/Http/Requests/UpdateOrderRequest.php

app/Services/OrderItemsService.php

app/Services/MeasurementEditor.php

app/Services/MeasurementLibrary.php

resources/views/measurements/index.blade.php

resources/views/orders/item-editor.blade.php

resources/views/orders/index.blade.php

Also inspect all migrations touching the \`measurements\` table.

Current issues include:

\- measurement inputs use type="number"

\- backend rules use \`numeric\`

\- backend rules use decimal validation

\- database measurement columns are DECIMAL

\- some display/formatting code may use Number(), parseFloat(), toFixed(),

truthiness checks, or numeric comparisons

\- those assumptions are no longer valid

The system must now treat a measurement VALUE as tailoring measurement text,

not arithmetic data.

\==================================================

BUSINESS RULE

\==================================================

Every measurement field must support BOTH:

NORMAL SINGLE VALUE:

56

56.5

56.50

0

000

0.00

AND COMPOUND / DUAL VALUE:

56.50-34.40

42-18

42.5-18.25

0-0

000-00

The hyphen means a measurement separator.

DO NOT calculate:

56.50 - 34.40

DO NOT convert it to:

22.10

DO NOT split it into database columns.

Store and display:

56.50-34.40

as ONE measurement value.

\==================================================

FIELDS AFFECTED

\==================================================

Apply this consistently to EVERY field in:

Measurement::FIELDS

including:

Length

Shoulder

Sleeves

Chest

Chest Losing

West / Waist

Waist Losing

Hip

Hip Losing

Collar

Galla

F/Patti

Button

Cuff

Koni

Elbow

Armor

Takki

Salwar Length

Pancho

Do NOT fix only Cuff and Elbow.

This must be a consistent measurement-value architecture.

\==================================================

ZERO VALUES — CRITICAL BUG FIX

\==================================================

ZERO IS A VALID MEASUREMENT VALUE.

The following values MUST NOT be treated as empty:

0

00

000

0.0

0.00

0-0

000-00

Never use logic like:

if (!value)

or:

value || null

or:

value || '—'

for measurement values.

Use explicit empty checks only.

Correct concept:

const isEmptyMeasurement = value =>

value === null ||

value === undefined ||

String(value).trim() === '';

Therefore:

"0"

\=> VALID

"000"

\=> VALID

0

\=> VALID

"0.00"

\=> VALID

Only:

null

undefined

""

are empty.

\==================================================

FIX /measurements SAVE PAYLOAD

\==================================================

Current code contains logic similar to:

payload\[field\] =

document.getElementById(\`meas-${field}\`)?.value || null;

DO NOT use truthy/falsy conversion.

Change to explicit handling:

const raw = document.getElementById(\`meas-${field}\`)?.value;

payload\[field\] =

raw === undefined || raw.trim() === ''

? null

: raw.trim();

This guarantees valid zero values remain values.

Likewise required-field validation must NOT use:

!payload\[field\]

Use:

payload\[field\] === null ||

payload\[field\] === undefined ||

String(payload\[field\]).trim() === ''

\==================================================

INPUT TYPES

\==================================================

Current measurement forms use:

That blocks:

56.50-34.40

Replace measurement VALUE inputs ONLY with:

type="text"

Do this in:

1\. /measurements Add Measurements

2\. /measurements Update Measurements

3\. Create Order → Step 3 Measurements

4\. Edit Order → Step 3 Measurements

5\. Any genuine measurement-value editor

Do NOT change unrelated numeric inputs such as:

prices

quantity

advance

staff rates

payments

expenses

Measurement input example concept:

type="text"

inputmode="text"

autocomplete="off"

...

\>

Do not use HTML number min/max/step attributes on measurement fields anymore.

\==================================================

ALLOWED FORMAT

\==================================================

Create ONE reusable server-side measurement-value validation rule/helper.

Allowed:

single number

OR

two numbers joined by ONE hyphen.

Each numeric component may contain decimals.

Examples VALID:

0

000

56

56.5

56.50

56.50-34.40

42-18

42.5-18

0-0

Examples INVALID:

abc

56--34

56-

\-34

56/34

56+34

56.50-34.40-20

Leading/trailing whitespace may be trimmed.

Do not interpret the hyphen mathematically.

\==================================================

DECIMAL PRECISION

\==================================================

The project already has:

Settings::measurementDecimals()

Preserve that configuration where sensible.

If decimals setting is 2:

VALID:

56

56.5

56.50

56-34

56.50-34.40

INVALID:

56.500

unless the configured decimal precision permits 3 digits.

Build validation based on the configured measurement decimal precision.

\==================================================

RANGE LIMIT

\==================================================

Current individual measurement values use maximum 999.

Preserve that business protection for EACH numeric component.

Example:

999

999.99

999-500

allowed if decimal precision permits.

1000

1000-20

must fail.

Do NOT compare the complete compound text numerically.

Validate each side independently.

\==================================================

DATABASE — VERY IMPORTANT

\==================================================

Current measurement columns are DECIMAL columns.

DECIMAL cannot store:

56.50-34.40

Therefore create a NEW migration.

DO NOT EDIT OLD MIGRATIONS.

Convert ONLY Measurement::FIELDS columns from numeric DECIMAL storage to

nullable string/VARCHAR storage suitable for these values.

Example conceptual target:

VARCHAR(50) NULL

or another sensible short size.

Fields include:

length

shoulder\_width

sleeve\_length

chest

chest\_losing

waist

waist\_losing

hip

hip\_losing

collar

ghera

patti

button

cuff

koni

elbow

armhole

takai

salwar\_length

pancho

Do NOT change:

customer\_id

unit

notes

tailor

order relationships

foreign keys

financial columns

\==================================================

MIGRATION SAFETY

\==================================================

Existing data MUST survive.

Existing values such as:

42.00

18.50

0.00

must remain available after migration.

Do NOT:

truncate measurements

drop/recreate the table

delete historical measurements

reset IDs

detach orders

Create a reversible migration.

Before implementation verify how MySQL will convert the existing DECIMAL values

to strings safely.

\==================================================

PRESERVE USER'S VALUE

\==================================================

Do not unnecessarily convert:

000

into:

0.00

if the user entered:

000

The new architecture should preserve meaningful tailoring notation as text.

Likewise:

56.50-34.40

must remain exactly:

56.50-34.40

Do not run:

Number()

parseFloat()

toFixed()

on stored measurement display values.

\==================================================

MEASUREMENT MODEL

\==================================================

Measurement::FIELDS remains the same.

DO NOT remove fields.

DO NOT rename database keys.

Cuff must still map to:

cuff

Koni:

koni

Elbow:

elbow

Armor:

armhole

Takki:

takai

etc.

Update comments/type assumptions that incorrectly describe every measurement as

strictly numeric.

\==================================================

MEASUREMENT CONTROLLER VALIDATION

\==================================================

Current MeasurementController validates every field approximately as:

numeric

min:0

max:999

decimal:...

Replace this with the reusable tailoring measurement value validator.

Requirements:

nullable unless required

single or compound measurement syntax

configured decimal precision

each component maximum 999

zero accepted

Do NOT weaken customer/tailor/unit/notes validation.

\==================================================

STORE ORDER REQUEST

\==================================================

StoreOrderRequest also contains numeric validation for:

pieces.\*.

and:

measurements.

Update ONLY measurement-field validation there.

The same syntax must work when measurements are entered while creating an

order.

Example:

Create New Order

Step 3

Cuff:

56.50-34.40

must successfully submit.

\==================================================

UPDATE ORDER REQUEST

\==================================================

Inspect UpdateOrderRequest too.

Edit Order must support exactly the same measurement syntax.

Do not fix Create only.

\==================================================

ORDER ITEMS SERVICE

\==================================================

OrderItemsService currently dynamically validates profile measurement fields as

numeric.

Replace ONLY those measurement-value rules with the shared measurement value

validator.

Preserve:

required profile fields

saved measurement ownership

piece/order integrity

measurement profiles

shared measurement behavior

notes

units

\==================================================

IMPORTANT: STRING COMPARISON

\==================================================

Review:

sameValues()

or any equivalent measurement comparison code.

Current code may do numeric normalization such as:

is\_numeric(...)

(float)$a === (float)$b

That is unsafe for the new notation.

For measurements, compare normalized measurement TEXT.

Examples:

Stored:

56.50-34.40

Submitted:

56.50-34.40

\=> unchanged

Stored:

56.50

Submitted:

56.50-34.40

\=> changed

Stored:

0.00

Submitted:

000

\=> if the user deliberately changed the notation, preserve the submitted text.

Do not cast compound values to floats.

\==================================================

ORDER STEP 3 UI

\==================================================

Current Create Order Step 3 has measurement inputs using:

type="number"

step="any"

min="0"

max="999"

Change ONLY measurement inputs to text-compatible inputs.

Example:

Cuff

\[ 56.50-34.40 \]

Elbow

\[ 000 \]

Do NOT redesign Step 3.

Keep:

Body Measurements

Customer name

New Measurement

Use Saved Measurement

Unit

Notes

Quick Actions

all current styling

Only change the measurement input behavior necessary to support the new values.

\==================================================

/measurements UI

\==================================================

Update the Add / Update Measurements modal similarly.

Example:

Update Measurements

Customer:

Sadaqat Ali Janweri

Body Metrics

Cuff

\[ 000 \]

Elbow

\[ 56.50-34.40 \]

When Update Measurements is clicked:

both values must persist.

Reopen the modal:

Cuff:

000

Elbow:

56.50-34.40

must still be there.

\==================================================

DO NOT LOSE CUFF / ELBOW / ETC

\==================================================

Specifically test ALL these fields:

Button

Cuff

Koni

Elbow

Armor

Takki

Salwar Length

Pancho

because the client has already reported values disappearing from these areas.

Every Measurement::FIELDS value must make this full round-trip:

INPUT

→ JS state

→ request payload

→ backend validation

→ MeasurementLibrary / MeasurementEditor

→ database

→ API response

→ Edit modal

→ View Details

→ Order

→ Workshop receipt

\==================================================

VIEW DETAILS

\==================================================

View Measurement Details must show zero correctly.

WRONG:

Cuff

—

when stored value is:

0

RIGHT:

Cuff

0

Likewise:

Cuff

000

should display:

000

and:

Elbow

56.50-34.40

should display:

56.50-34.40

\==================================================

PRINT MEASUREMENT SHEET

\==================================================

Search for any code using:

Number(value)

parseFloat(value)

.toFixed(...)

when printing measurement values.

REMOVE numeric formatting ONLY for measurement values.

This would otherwise turn:

56.50-34.40

into:

NaN

Print the escaped stored value directly.

Example:

Cuff ............... 56.50-34.40

\==================================================

WORKSHOP COPY

\==================================================

The Workshop Copy must show the exact measurement text.

Examples:

Cuff 000

Elbow 56.50-34.40

Chest 42-18

DO NOT perform arithmetic.

DO NOT show:

NaN

0 unexpectedly

—

for a stored zero value

Keep current Workshop design, sizes, dark text and alignment.

Do not redesign the receipt.

\==================================================

RECEIPT ZERO SAFETY

\==================================================

Inspect ALL measurement receipt rendering for patterns like:

value || '—'

value ? value : '—'

if (value)

filter(Boolean)

Those are wrong for zero measurements.

Use explicit empty checks:

value !== null &&

value !== undefined &&

String(value) !== ''

Thus:

0

"0"

"000"

"0.00"

must print.

\==================================================

ONE SHARED MEASUREMENT SET

\==================================================

Do NOT reverse the recently implemented rule:

ONE CUSTOMER

\=

ONE CURRENT SAVED MEASUREMENT SET

\=

ALL GARMENTS USE IT

This task must remain compatible with that architecture.

Cotton

Boski

Wash & Wear

still use the same customer measurement set.

\==================================================

NOTES / QUICK ACTIONS

\==================================================

Do NOT change the recently added measurement Notes Quick Actions.

They must keep working.

This task applies to BODY MEASUREMENT VALUES, not Notes syntax.

\==================================================

SECURITY

\==================================================

Measurement strings printed into HTML must still go through:

Atelier.escapeHtml()

or Blade escaped output.

The hyphen support must not introduce raw HTML output.

\==================================================

DO NOT ALLOW RANDOM TEXT

\==================================================

Although measurement columns become strings, DO NOT turn them into free-form

notes.

Allowed measurement syntax is still controlled.

Examples:

56.50

56.50-34.40

000

are valid.

"very loose"

"abc"

""</p><p class="slate-paragraph"></p><p class="slate-paragraph">must not pass measurement validation.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Use Notes for textual instructions.</p><p class="slate-paragraph"></p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph">HISTORICAL DATA</p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph"></p><p class="slate-paragraph">Historical measurements and historical orders must continue to display.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Do not rewrite old order snapshots unnecessarily.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Existing numeric values must still work after migration.</p><p class="slate-paragraph"></p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph">TEST EXACT SCENARIOS</p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph"></p><p class="slate-paragraph">TEST 1 — ZERO</p><p class="slate-paragraph"></p><p class="slate-paragraph">Customer:</p><p class="slate-paragraph">Sadaqat Ali Janweri</p><p class="slate-paragraph"></p><p class="slate-paragraph">Cuff:</p><p class="slate-paragraph">000</p><p class="slate-paragraph"></p><p class="slate-paragraph">Elbow:</p><p class="slate-paragraph">000</p><p class="slate-paragraph"></p><p class="slate-paragraph">Save.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Reopen.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Expected:</p><p class="slate-paragraph"></p><p class="slate-paragraph">Cuff = 000</p><p class="slate-paragraph">Elbow = 000</p><p class="slate-paragraph"></p><p class="slate-paragraph">NOT:</p><p class="slate-paragraph"></p><p class="slate-paragraph">—</p><p class="slate-paragraph">blank</p><p class="slate-paragraph">null</p><p class="slate-paragraph"></p><p class="slate-paragraph"></p><p class="slate-paragraph">TEST 2 — SINGLE ZERO</p><p class="slate-paragraph"></p><p class="slate-paragraph">Cuff:</p><p class="slate-paragraph">0</p><p class="slate-paragraph"></p><p class="slate-paragraph">Expected:</p><p class="slate-paragraph">stored and displayed as 0.</p><p class="slate-paragraph"></p><p class="slate-paragraph"></p><p class="slate-paragraph">TEST 3 — DECIMAL</p><p class="slate-paragraph"></p><p class="slate-paragraph">Elbow:</p><p class="slate-paragraph">56.50</p><p class="slate-paragraph"></p><p class="slate-paragraph">Expected:</p><p class="slate-paragraph">56.50</p><p class="slate-paragraph"></p><p class="slate-paragraph"></p><p class="slate-paragraph">TEST 4 — COMPOUND</p><p class="slate-paragraph"></p><p class="slate-paragraph">Elbow:</p><p class="slate-paragraph">56.50-34.40</p><p class="slate-paragraph"></p><p class="slate-paragraph">Expected database:</p><p class="slate-paragraph">56.50-34.40</p><p class="slate-paragraph"></p><p class="slate-paragraph">Expected View:</p><p class="slate-paragraph">56.50-34.40</p><p class="slate-paragraph"></p><p class="slate-paragraph">Expected Workshop Receipt:</p><p class="slate-paragraph">56.50-34.40</p><p class="slate-paragraph"></p><p class="slate-paragraph"></p><p class="slate-paragraph">TEST 5 — DIFFERENT FIELDS</p><p class="slate-paragraph"></p><p class="slate-paragraph">Length:</p><p class="slate-paragraph">42-18</p><p class="slate-paragraph"></p><p class="slate-paragraph">Cuff:</p><p class="slate-paragraph">12.50-6.25</p><p class="slate-paragraph"></p><p class="slate-paragraph">Koni:</p><p class="slate-paragraph">000</p><p class="slate-paragraph"></p><p class="slate-paragraph">Elbow:</p><p class="slate-paragraph">56.50-34.40</p><p class="slate-paragraph"></p><p class="slate-paragraph">Armor:</p><p class="slate-paragraph">23</p><p class="slate-paragraph"></p><p class="slate-paragraph">Takki:</p><p class="slate-paragraph">0</p><p class="slate-paragraph"></p><p class="slate-paragraph">Salwar Length:</p><p class="slate-paragraph">40.50</p><p class="slate-paragraph"></p><p class="slate-paragraph">Pancho:</p><p class="slate-paragraph">14-7</p><p class="slate-paragraph"></p><p class="slate-paragraph">All must persist.</p><p class="slate-paragraph"></p><p class="slate-paragraph"></p><p class="slate-paragraph">TEST 6 — ORDER STEP 3</p><p class="slate-paragraph"></p><p class="slate-paragraph">Create an order.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Use:</p><p class="slate-paragraph"></p><p class="slate-paragraph">Chest = 42-18</p><p class="slate-paragraph">Cuff = 000</p><p class="slate-paragraph">Elbow = 56.50-34.40</p><p class="slate-paragraph"></p><p class="slate-paragraph">Submit order.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Reopen order.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Expected exact values remain.</p><p class="slate-paragraph"></p><p class="slate-paragraph"></p><p class="slate-paragraph">TEST 7 — WORKSHOP COPY</p><p class="slate-paragraph"></p><p class="slate-paragraph">Print Workshop Copy.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Expected:</p><p class="slate-paragraph"></p><p class="slate-paragraph">Chest 42-18</p><p class="slate-paragraph">Cuff 000</p><p class="slate-paragraph">Elbow 56.50-34.40</p><p class="slate-paragraph"></p><p class="slate-paragraph">No NaN.</p><p class="slate-paragraph">No blank.</p><p class="slate-paragraph">No dash placeholder.</p><p class="slate-paragraph"></p><p class="slate-paragraph"></p><p class="slate-paragraph">TEST 8 — NORMAL LEGACY VALUES</p><p class="slate-paragraph"></p><p class="slate-paragraph">Enter:</p><p class="slate-paragraph"></p><p class="slate-paragraph">Length = 42</p><p class="slate-paragraph">Shoulder = 18.5</p><p class="slate-paragraph"></p><p class="slate-paragraph">Expected normal behavior remains.</p><p class="slate-paragraph"></p><p class="slate-paragraph"></p><p class="slate-paragraph">TEST 9 — INVALID INPUT</p><p class="slate-paragraph"></p><p class="slate-paragraph">Reject:</p><p class="slate-paragraph"></p><p class="slate-paragraph">abc</p><p class="slate-paragraph">56--34</p><p class="slate-paragraph">56-</p><p class="slate-paragraph">-34</p><p class="slate-paragraph">56/34</p><p class="slate-paragraph">56-34-20</p><p class="slate-paragraph"></p><p class="slate-paragraph"></p><p class="slate-paragraph">TEST 10 — EXISTING RECORDS</p><p class="slate-paragraph"></p><p class="slate-paragraph">Open existing saved measurements created before this migration.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Expected:</p><p class="slate-paragraph">all historical numeric values still visible.</p><p class="slate-paragraph"></p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph">SEARCH THE ENTIRE REPOSITORY</p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph"></p><p class="slate-paragraph">Before declaring complete search measurement-related code for:</p><p class="slate-paragraph"></p><p class="slate-paragraph">type="number"</p><p class="slate-paragraph">numeric</p><p class="slate-paragraph">decimal:</p><p class="slate-paragraph">min:0</p><p class="slate-paragraph">max:999</p><p class="slate-paragraph">Number(</p><p class="slate-paragraph">parseFloat(</p><p class="slate-paragraph">toFixed(</p><p class="slate-paragraph">is\_numeric(</p><p class="slate-paragraph">|| null</p><p class="slate-paragraph">|| &#x27;—&#x27;</p><p class="slate-paragraph">filter(Boolean)</p><p class="slate-paragraph">if (value)</p><p class="slate-paragraph"></p><p class="slate-paragraph">Do NOT globally replace these.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Only replace occurrences that deal with MEASUREMENT VALUES.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Prices and financial values must remain numeric.</p><p class="slate-paragraph"></p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph">EXPECTED FILES</p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph"></p><p class="slate-paragraph">Likely changes include:</p><p class="slate-paragraph"></p><p class="slate-paragraph">app/Models/Measurement.php</p><p class="slate-paragraph">app/Http/Controllers/MeasurementController.php</p><p class="slate-paragraph">app/Http/Requests/StoreOrderRequest.php</p><p class="slate-paragraph">app/Http/Requests/UpdateOrderRequest.php</p><p class="slate-paragraph">app/Services/OrderItemsService.php</p><p class="slate-paragraph">resources/views/measurements/index.blade.php</p><p class="slate-paragraph">resources/views/orders/item-editor.blade.php</p><p class="slate-paragraph">resources/views/orders/index.blade.php</p><p class="slate-paragraph"></p><p class="slate-paragraph">plus:</p><p class="slate-paragraph"></p><p class="slate-paragraph">ONE NEW migration</p><p class="slate-paragraph"></p><p class="slate-paragraph">and preferably:</p><p class="slate-paragraph"></p><p class="slate-paragraph">ONE reusable measurement value validation Rule/service.</p><p class="slate-paragraph"></p><p class="slate-paragraph">Do not modify old migration files.</p><p class="slate-paragraph"></p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph">DO NOT CHANGE</p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph"></p><p class="slate-paragraph">Do NOT change:</p><p class="slate-paragraph"></p><p class="slate-paragraph">customer measurement ownership</p><p class="slate-paragraph">one-customer-one-measurement rule</p><p class="slate-paragraph">garment logic</p><p class="slate-paragraph">quantities</p><p class="slate-paragraph">prices</p><p class="slate-paragraph">payment system</p><p class="slate-paragraph">ledger</p><p class="slate-paragraph">staff/payroll</p><p class="slate-paragraph">delivery</p><p class="slate-paragraph">SMS</p><p class="slate-paragraph">receipt design</p><p class="slate-paragraph">receipt font sizes</p><p class="slate-paragraph">Workshop alignment</p><p class="slate-paragraph">Notes Quick Actions</p><p class="slate-paragraph">Cloth Store</p><p class="slate-paragraph">sidebar</p><p class="slate-paragraph">dashboard</p><p class="slate-paragraph">theme</p><p class="slate-paragraph"></p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph">AFTER IMPLEMENTATION REPORT</p><p class="slate-paragraph">==================================================</p><p class="slate-paragraph"></p><p class="slate-paragraph">Report:</p><p class="slate-paragraph"></p><p class="slate-paragraph">1. Root cause found for zero values disappearing.</p><p class="slate-paragraph">2. Exact files changed.</p><p class="slate-paragraph">3. New migration filename.</p><p class="slate-paragraph">4. Previous DB type and new DB type.</p><p class="slate-paragraph">5. Exact allowed measurement syntax.</p><p class="slate-paragraph">6. Confirmation Cuff saves.</p><p class="slate-paragraph">7. Confirmation Koni saves.</p><p class="slate-paragraph">8. Confirmation Elbow saves.</p><p class="slate-paragraph">9. Confirmation Armor saves.</p><p class="slate-paragraph">10. Confirmation Takki saves.</p><p class="slate-paragraph">11. Confirmation 000 is NOT considered empty.</p><p class="slate-paragraph">12. Confirmation 56.50-34.40 saves literally.</p><p class="slate-paragraph">13. Confirmation Order Step 3 supports it.</p><p class="slate-paragraph">14. Confirmation /measurements supports it.</p><p class="slate-paragraph">15. Confirmation View Details displays it.</p><p class="slate-paragraph">16. Confirmation Workshop receipt prints it exactly.</p><p class="slate-paragraph">17. Confirmation historical measurements still work.</p><p class="slate-paragraph">18. Tests run and exact results.</p><p class="slate-paragraph"></p><p class="slate-paragraph">FINAL RULE:</p><p class="slate-paragraph"></p><p class="slate-paragraph">MEASUREMENT VALUES ARE TAILORING NOTATION, NOT ARITHMETIC.</p><p class="slate-paragraph"></p><p class="slate-paragraph">SUPPORT BOTH:</p><p class="slate-paragraph"></p><p class="slate-paragraph">56.50</p><p class="slate-paragraph"></p><p class="slate-paragraph">AND:</p><p class="slate-paragraph"></p><p class="slate-paragraph">56.50-34.40</p><p class="slate-paragraph"></p><p class="slate-paragraph">AND NEVER TREAT:</p><p class="slate-paragraph"></p><p class="slate-paragraph">0 / 00 / 000 / 0.00</p><p class="slate-paragraph"></p><p class="slate-paragraph">AS EMPTY.</p><p class="slate-paragraph"></p><p class="slate-paragraph">DO NOT CHANGE ANYTHING ELSE.</p></x-turndown>