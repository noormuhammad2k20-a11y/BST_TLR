TASK: REPLACE ONLY THE EXISTING FINAL RECEIPT · CUSTOMER COPY DESIGN.

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

FIRST deeply inspect the CURRENT repository and current delivery receipt flow.

The existing Final Receipt is located at:

resources/views/delivery/receipt.blade.php

DO NOT rebuild delivery functionality.

DO NOT change payment calculations.

DO NOT change database.

DO NOT change the Booking Receipt / normal Customer Copy.

DO NOT change Workshop Copy.

DO NOT change Cloth Store receipts.

ONLY replace the visual design of:

FINAL RECEIPT · CUSTOMER COPY

with the exact Neo receipt design I provide below.

\==================================================

CURRENT DATA MUST REMAIN REAL

\==================================================

The existing Final Receipt already receives real Laravel data such as:

$receipt

$order

$dues

KEEP using those values.

DO NOT hardcode sample values such as:

ORD-1070

INV-1070

Noor M

0345 3587094

Boski

Rs.2,500

Those are DESIGN EXAMPLES ONLY.

\==================================================

EXACT DATA MAPPING

\==================================================

Map the supplied design to the current Laravel data.

Use:

FINAL.no

\=> $receipt\['order'\]

FINAL.invoice

\=> $receipt\['invoice'\]

FINAL.collected

\=> \\App\\Services\\Dates::formatWithTime($order->delivered\_at)

FINAL.customer

\=> $receipt\['customer'\]

FINAL.phone

\=> $receipt\['customer\_ph'\]

FINAL.items

\=> $receipt\['items'\]

Each item must use the existing real:

$item\['name'\]

$item\['qty'\]

$item price / unit price where available

$item\['price'\]

Do NOT invent prices.

Use the application's existing Money service:

\\App\\Services\\Money::format(...)

where appropriate.

\==================================================

PAYMENT DATA — EXTREMELY IMPORTANT

\==================================================

Do NOT calculate customer financials incorrectly from the sample JavaScript.

The existing Laravel/backend values are authoritative.

Render the real existing:

Subtotal / existing billing lines

Total

Total Paid

Balance

Current Order Due

Previous Due

Customer Total Due

using the existing \`$receipt\`, \`$order\`, \`$dues\` data.

The current Final Receipt already correctly supports:

$dues\['current\_order\_due'\]

$dues\['previous\_due'\]

$dues\['customer\_total\_due'\]

KEEP THEM.

The new receipt Payment section should visually look like:

PAYMENT

Subtotal ................ Rs.X

\[ TOTAL Rs.X \]

Total Paid .............. Rs.X

Balance ................. Rs.X

Current Order Due ....... Rs.X

Previous Due ............ Rs.X

Customer Total Due ...... Rs.X

But values MUST come from the existing backend.

DO NOT change ledger/accounting logic.

\==================================================

STATUS BOX

\==================================================

Use the existing real payment status.

If fully paid show:

COLLECTED BY CUSTOMER · FULLY PAID

Otherwise show:

COLLECTED BY CUSTOMER · PAYMENT PENDING

Do not hardcode status.

Use the current \`$order->payment\_status\` / authoritative current state.

\==================================================

SHOP DETAILS

\==================================================

Use the current receipt settings dynamically:

$receipt\['store'\]

$receipt\['tagline'\]

$receipt\['address'\]

$receipt\['phone'\]

Do NOT hardcode BEST TAILOR/address/phone if the project already provides them

through Settings.

The visual appearance should match the provided design, but the values must stay

dynamic.

\==================================================

SOFTWARE CREDIT

\==================================================

Keep this content exactly in the new bordered credit box:

Powered by

TAILORING & CLOTH HOUSE MANAGEMENT SYSTEM

Designed & Developed by

NOOR M HINGORJO

Software Support

0303 4980786

Do not duplicate the developer credit anywhere else on the receipt.

\==================================================

DOCUMENT TITLE

\==================================================

The central receipt title MUST be:

FINAL RECEIPT · CUSTOMER COPY

Not just:

FINAL RECEIPT

\==================================================

ITEMS SECTION

\==================================================

Heading:

ITEMS COLLECTED

Each garment must have the exact new layout.

Example only:

Boski × 1

Rs.2,500.00 each ................. Rs.2,500.00

Cotton × 2

Rs.2,000.00 each ................. Rs.4,000.00

Use real order data.

If unit price exists in the existing item payload, show:

Rs.X each

If current backend payload does not expose an authoritative unit price,

derive it ONLY safely from the existing real item total / quantity where quantity > 0.

Do not change stored order data.

\==================================================

DESIGN REQUIREMENT

\==================================================

I want the supplied design visually SAME:

\- IBM Plex Mono

\- Space Grotesk

\- large BEST TAILOR/store heading

\- Tailoring & Cloth House separator

\- centered address/phone

\- thin black divider

\- spaced FINAL RECEIPT · CUSTOMER COPY title

\- dotted metadata leaders

\- ITEMS COLLECTED section

\- clean item name + quantity

\- item unit price + dotted leader + total

\- PAYMENT separator

\- Total bordered box

\- payment rows

\- final collection/payment status bordered box

\- thank-you message

\- developer/software credit bordered box

\- THANK YOU divider at bottom

\- monochrome black/white premium thermal appearance

DO NOT improvise another design.

\==================================================

80MM THERMAL SAFETY

\==================================================

The new design must remain suitable for the project's physical 80mm thermal printer.

Do not cause:

\- clipping

\- horizontal overflow

\- amount truncation

\- text overlap

The current application has an established thermal setup.

Preserve the working printable width behavior.

If the application uses 72mm printable content inside 80mm paper, adapt the

provided 302px design to that existing printable width.

Do NOT break printer compatibility just to force literal 302px/80mm values.

\==================================================

PRINT BUTTON / PAGE

\==================================================

The existing standalone Final Receipt page currently has:

Print Final Receipt

KEEP that working.

Do not change delivery flow after collection.

\`window.print()\` must continue to print only the receipt cleanly.

The button must remain hidden on physical print.

\==================================================

IMPORTANT CSS RULE

\==================================================

This Final Receipt is a standalone delivery receipt page, but still do not allow

new CSS to leak into the application globally.

Scope the visual receipt styling primarily under \`.rc\`.

Do not modify:

resources/views/receipts/slip-styles.blade.php

unless absolutely required.

Prefer keeping all new Final Receipt-specific CSS in:

resources/views/delivery/receipt.blade.php

because this design applies only to the Final Receipt.

\==================================================

DO NOT CHANGE THESE RECEIPTS

\==================================================

DO NOT TOUCH:

1\. Create Order Booking Receipt · Customer Copy

2\. Workshop Copy

3\. Cloth Store receipts

4\. Any other thermal receipt

This task is ONLY:

resources/views/delivery/receipt.blade.php

Final Receipt · Customer Copy

\==================================================

OPTIONAL EXISTING DATA

\==================================================

The current receipt may contain optional:

stamp

terms

footer

barcode

Do not allow old markup to destroy the new design.

If these are still required by application settings, integrate them minimally

and cleanly AFTER the main receipt content using the same visual language.

However:

\- never duplicate developer credit

\- never duplicate Thank You

\- never duplicate phone

\- never duplicate management system text

\==================================================

SECURITY / ESCAPING

\==================================================

Use Blade escaped output:

{{ ... }}

for customer names, phone, item names, etc.

Do NOT render customer-controlled content as raw HTML.

\==================================================

REFERENCE DESIGN

\==================================================

Use the following HTML/CSS as the exact visual reference.

IMPORTANT:

The JavaScript SAMPLE DATA inside this design is NOT production data.

Convert this design to Blade and bind real Laravel variables.

\----- REFERENCE DESIGN START -----

Best Tailor — Final Receipt

</p><p class="slate-paragraph">\*{box-sizing:border-box;margin:0;padding:0}</p><p class="slate-paragraph"></p><p class="slate-paragraph">body{</p><p class="slate-paragraph">background:#fff;</p><p class="slate-paragraph">min-height:100vh;</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">justify-content:center;</p><p class="slate-paragraph">align-items:flex-start;</p><p class="slate-paragraph">padding:32px 16px;</p><p class="slate-paragraph">font-family:&#x27;Space Grotesk&#x27;,sans-serif;</p><p class="slate-paragraph">-webkit-font-smoothing:antialiased</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc{</p><p class="slate-paragraph">width:302px;</p><p class="slate-paragraph">background:#fff;</p><p class="slate-paragraph">color:#141414;</p><p class="slate-paragraph">padding:19px 15px 21px;</p><p class="slate-paragraph">font:400 10px/1.5 &#x27;IBM Plex Mono&#x27;</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-head{text-align:center}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-name{</p><p class="slate-paragraph">font:700 23px/1.1 &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.03em;</p><p class="slate-paragraph">color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tag{</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">align-items:center;</p><p class="slate-paragraph">gap:8px;</p><p class="slate-paragraph">margin:9px 0 0</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tag::before,</p><p class="slate-paragraph">.rc-tag::after{</p><p class="slate-paragraph">content:&#x27;&#x27;;</p><p class="slate-paragraph">flex:1;</p><p class="slate-paragraph">height:1px;</p><p class="slate-paragraph">background:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tag span{</p><p class="slate-paragraph">font:600 7.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.32em;</p><p class="slate-paragraph">margin-right:-.32em;</p><p class="slate-paragraph">text-transform:uppercase;</p><p class="slate-paragraph">white-space:nowrap</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-addr{</p><p class="slate-paragraph">font:400 8.5px/1.55 &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph">color:#444;</p><p class="slate-paragraph">margin-top:8px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-ph{</p><p class="slate-paragraph">font:600 8.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph">margin-top:2px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-rule{</p><p class="slate-paragraph">height:1.5px;</p><p class="slate-paragraph">background:#000;</p><p class="slate-paragraph">border:0;</p><p class="slate-paragraph">margin:12px 0 14px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-doc{</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">justify-content:center;</p><p class="slate-paragraph">margin:3px 0 13px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-doc b{</p><p class="slate-paragraph">font:700 8.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.3em;</p><p class="slate-paragraph">margin-right:-.3em;</p><p class="slate-paragraph">text-transform:uppercase;</p><p class="slate-paragraph">color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-meta{</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">flex-direction:column;</p><p class="slate-paragraph">gap:6px;</p><p class="slate-paragraph">margin:0 0 3px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-m{</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">align-items:baseline;</p><p class="slate-paragraph">gap:6px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-m .k{</p><p class="slate-paragraph">font:600 8px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.12em;</p><p class="slate-paragraph">color:#5c5c5c;</p><p class="slate-paragraph">white-space:nowrap;</p><p class="slate-paragraph">text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-m .dots{</p><p class="slate-paragraph">flex:1;</p><p class="slate-paragraph">min-width:12px;</p><p class="slate-paragraph">border-bottom:1px dotted #9a9a9a;</p><p class="slate-paragraph">transform:translateY(-3px)</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-m .v{</p><p class="slate-paragraph">font:500 10.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph">color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-m .v.b{font-weight:700}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-sec{</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">align-items:center;</p><p class="slate-paragraph">gap:8px;</p><p class="slate-paragraph">margin:16px 0 8px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-sec::before,</p><p class="slate-paragraph">.rc-sec::after{</p><p class="slate-paragraph">content:&#x27;&#x27;;</p><p class="slate-paragraph">flex:1;</p><p class="slate-paragraph">height:1px;</p><p class="slate-paragraph">background:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-sec span{</p><p class="slate-paragraph">font:700 8px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.3em;</p><p class="slate-paragraph">margin-right:-.3em;</p><p class="slate-paragraph">text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-item{padding:7px 0}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-item+.rc-item{</p><p class="slate-paragraph">border-top:1px dashed #d5d5d5</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i1{</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">justify-content:space-between;</p><p class="slate-paragraph">align-items:baseline;</p><p class="slate-paragraph">gap:8px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i1 .nm{</p><p class="slate-paragraph">font:600 11px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i1 .qt{</p><p class="slate-paragraph">font:500 9px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph">color:#555;</p><p class="slate-paragraph">white-space:nowrap</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i2{</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">align-items:baseline;</p><p class="slate-paragraph">gap:6px;</p><p class="slate-paragraph">margin-top:2px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i2 .rt{</p><p class="slate-paragraph">font:400 8.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph">color:#5a5a5a;</p><p class="slate-paragraph">white-space:nowrap</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i2 .dots{</p><p class="slate-paragraph">flex:1;</p><p class="slate-paragraph">min-width:10px;</p><p class="slate-paragraph">border-bottom:1px dotted #a5a5a5;</p><p class="slate-paragraph">transform:translateY(-3px)</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i2 .tt{</p><p class="slate-paragraph">font:700 11px &#x27;IBM Plex Mono&#x27;</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tot{margin-top:3px}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr{</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">align-items:baseline;</p><p class="slate-paragraph">gap:6px;</p><p class="slate-paragraph">padding:3.5px 0</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr .k{</p><p class="slate-paragraph">font:600 8px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.14em;</p><p class="slate-paragraph">color:#555;</p><p class="slate-paragraph">white-space:nowrap;</p><p class="slate-paragraph">text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr .dots{</p><p class="slate-paragraph">flex:1;</p><p class="slate-paragraph">min-width:10px;</p><p class="slate-paragraph">border-bottom:1px dotted #a5a5a5;</p><p class="slate-paragraph">transform:translateY(-3px)</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr .v{</p><p class="slate-paragraph">font:600 10.5px &#x27;IBM Plex Mono&#x27;</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr.due .k{color:#000}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr.due .v{</p><p class="slate-paragraph">font-weight:700;</p><p class="slate-paragraph">font-size:11.5px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr.sub .k{color:#666}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr.sub .v{</p><p class="slate-paragraph">font-weight:500;</p><p class="slate-paragraph">font-size:9.5px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tb{</p><p class="slate-paragraph">border:1.5px solid #000;</p><p class="slate-paragraph">border-radius:3px;</p><p class="slate-paragraph">margin:10px 0 8px;</p><p class="slate-paragraph">padding:10px 12px;</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">justify-content:space-between;</p><p class="slate-paragraph">align-items:center</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tb span{</p><p class="slate-paragraph">font:700 9px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.24em;</p><p class="slate-paragraph">text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tb b{</p><p class="slate-paragraph">font:700 15px &#x27;IBM Plex Mono&#x27;</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-stat{</p><p class="slate-paragraph">margin-top:13px;</p><p class="slate-paragraph">text-align:center;</p><p class="slate-paragraph">font:700 7.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.18em;</p><p class="slate-paragraph">border:1px solid #000;</p><p class="slate-paragraph">border-radius:3px;</p><p class="slate-paragraph">padding:6px 4px;</p><p class="slate-paragraph">text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-note{</p><p class="slate-paragraph">text-align:center;</p><p class="slate-paragraph">font:400 8.5px/1.65 &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph">color:#333;</p><p class="slate-paragraph">margin-top:13px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit{</p><p class="slate-paragraph">margin-top:15px;</p><p class="slate-paragraph">border:1px solid #000;</p><p class="slate-paragraph">border-radius:3px;</p><p class="slate-paragraph">padding:10px 10px 12px;</p><p class="slate-paragraph">text-align:center</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c1{</p><p class="slate-paragraph">font:400 7px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph">color:#8a8a8a;</p><p class="slate-paragraph">letter-spacing:.28em;</p><p class="slate-paragraph">margin-right:-.28em;</p><p class="slate-paragraph">text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c2{</p><p class="slate-paragraph">font:700 7.5px/1.6 &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.04em;</p><p class="slate-paragraph">margin-top:4px;</p><p class="slate-paragraph">color:#000;</p><p class="slate-paragraph">text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .cline{</p><p class="slate-paragraph">display:block;</p><p class="slate-paragraph">width:24px;</p><p class="slate-paragraph">height:1.5px;</p><p class="slate-paragraph">background:#000;</p><p class="slate-paragraph">margin:7px auto</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c3{</p><p class="slate-paragraph">font:700 8.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.08em;</p><p class="slate-paragraph">color:#000;</p><p class="slate-paragraph">text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c3 span{</p><p class="slate-paragraph">font:400 7.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph">color:#8a8a8a;</p><p class="slate-paragraph">letter-spacing:.08em;</p><p class="slate-paragraph">margin-right:6px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c4{</p><p class="slate-paragraph">font:600 9px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph">color:#000;</p><p class="slate-paragraph">letter-spacing:.03em;</p><p class="slate-paragraph">margin-top:5px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c4 span{</p><p class="slate-paragraph">font:400 7.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph">color:#8a8a8a;</p><p class="slate-paragraph">letter-spacing:.08em;</p><p class="slate-paragraph">margin-right:6px;</p><p class="slate-paragraph">text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-thx{</p><p class="slate-paragraph">display:flex;</p><p class="slate-paragraph">align-items:center;</p><p class="slate-paragraph">gap:8px;</p><p class="slate-paragraph">margin-top:13px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-thx::before,</p><p class="slate-paragraph">.rc-thx::after{</p><p class="slate-paragraph">content:&#x27;&#x27;;</p><p class="slate-paragraph">flex:1;</p><p class="slate-paragraph">height:1px;</p><p class="slate-paragraph">background:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-thx span{</p><p class="slate-paragraph">font:700 8.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph">letter-spacing:.4em;</p><p class="slate-paragraph">margin-right:-.4em;</p><p class="slate-paragraph">text-transform:uppercase;</p><p class="slate-paragraph">color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">@media screen{</p><p class="slate-paragraph">.rc{</p><p class="slate-paragraph">border:1px solid #BFBAB0</p><p class="slate-paragraph">}</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">@media print{</p><p class="slate-paragraph">@page{</p><p class="slate-paragraph">size:80mm auto;</p><p class="slate-paragraph">margin:0</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">\*,\*::before,\*::after{</p><p class="slate-paragraph">-webkit-print-color-adjust:exact!important;</p><p class="slate-paragraph">print-color-adjust:exact!important</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">body{</p><p class="slate-paragraph">background:#fff!important;</p><p class="slate-paragraph">padding:0!important;</p><p class="slate-paragraph">display:block!important</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc{</p><p class="slate-paragraph">width:80mm</p><p class="slate-paragraph">}</p><p class="slate-paragraph">}</p><p class="slate-paragraph">

</p><p class="slate-paragraph"></p><p class="slate-paragraph">const SHOP = {</p><p class="slate-paragraph">name:&#x27;BEST TAILOR&#x27;,</p><p class="slate-paragraph">tag:&#x27;Tailoring & Cloth House&#x27;,</p><p class="slate-paragraph">addr:&#x27;Near Al Falah Bank, Pakora Stop, Qasimabad, Hyderabad&#x27;,</p><p class="slate-paragraph">ph:&#x27;0317 3780121&#x27;,</p><p class="slate-paragraph">devName:&#x27;NOOR M HINGORJO&#x27;,</p><p class="slate-paragraph">devSys:&#x27;TAILORING & CLOTH HOUSE MANAGEMENT SYSTEM&#x27;,</p><p class="slate-paragraph">devPh:&#x27;0303 4980786&#x27;</p><p class="slate-paragraph">};</p><p class="slate-paragraph"></p><p class="slate-paragraph">const FINAL = {</p><p class="slate-paragraph">no:&#x27;ORD-1070&#x27;,</p><p class="slate-paragraph">invoice:&#x27;INV-1070&#x27;,</p><p class="slate-paragraph">collected:&#x27;17/09/2026 · 2:16 AM&#x27;,</p><p class="slate-paragraph">customer:&#x27;Noor M&#x27;,</p><p class="slate-paragraph">phone:&#x27;0345 3587094&#x27;,</p><p class="slate-paragraph">items:\[</p><p class="slate-paragraph">{</p><p class="slate-paragraph">name:&#x27;Boski&#x27;,</p><p class="slate-paragraph">qty:1,</p><p class="slate-paragraph">each:2500,</p><p class="slate-paragraph">total:2500</p><p class="slate-paragraph">}</p><p class="slate-paragraph">\],</p><p class="slate-paragraph">paid:2500,</p><p class="slate-paragraph">prevDue:0</p><p class="slate-paragraph">};</p><p class="slate-paragraph"></p><p class="slate-paragraph">

\----- REFERENCE DESIGN END -----

\==================================================

IMPORTANT:

CONVERT REFERENCE TO BLADE — DO NOT KEEP SAMPLE JS

\==================================================

The final production file should NOT need:

const FINAL = ...

render()

document.getElementById(...)

The current Final Receipt is server-rendered Blade.

KEEP IT server-rendered Blade.

Use Laravel/Blade loops:

@foreach($receipt\['items'\] as $item)

and current variables directly.

The reference JavaScript exists only to show the intended visual layout.

\==================================================

EXPECTED BLADE STRUCTURE

\==================================================

Conceptually the production markup should become:

dynamic shop data

**Final Receipt · Customer Copy**

Order

Invoice

Collected

Customer

Phone

ITEMS COLLECTED

real items...

PAYMENT

real billing/payment values...

status

thank-you message

software credit

THANK YOU

Do not use sample data.

\==================================================

PRINT BUTTON

\==================================================

Keep:

Print Final Receipt

above the receipt on screen.

Screen only.

Do not print this button.

\==================================================

REGRESSION SAFETY

\==================================================

Test:

1\. Fully paid delivered order.

2\. Delivered order with remaining current-order balance.

3\. Customer with Previous Due.

4\. Multiple garments.

5\. Quantity > 1.

6\. Long customer name.

7\. Long garment name.

8\. Large monetary amounts.

9\. Phone hidden/empty.

10\. Physical 80mm printing.

Verify mathematically:

Customer Total Due remains exactly the backend value.

Do NOT accidentally calculate:

Current Order Due + Previous Due

again if the backend has already done it.

Use \`$dues\['customer\_total\_due'\]\`.

\==================================================

ABSOLUTE LOCK

\==================================================

DO NOT TOUCH:

resources/views/orders/index.blade.php Customer Copy

Workshop Copy

Create Order receipt

Measurements

Delivery logic

Payments

Customer ledger

Staff

Cloth Store

SMS

Dashboard

Sidebar

Settings

Database

Controllers unless absolutely necessary to expose existing authoritative data

The required change should primarily be:

resources/views/delivery/receipt.blade.php

FINAL RULE:

ONLY REPLACE THE VISUAL DESIGN OF:

FINAL RECEIPT · CUSTOMER COPY

WITH THE PROVIDED NEO DESIGN.

ALL EXISTING REAL DATA, PAYMENT CALCULATIONS, DELIVERY FLOW AND ACCOUNTING MUST

REMAIN EXACTLY THE SAME.