TASK: REPLACE ONLY THE EXISTING CUSTOMER COPY RECEIPT DESIGN.

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

IMPORTANT:

Deeply inspect the CURRENT repository first.

The current receipt system is already working.

DO NOT rebuild receipt functionality.

DO NOT change Workshop Copy.

DO NOT change printing flow.

DO NOT change order calculations.

DO NOT change database.

DO NOT change customer ledger/payments.

DO NOT change any unrelated page.

I am giving you a NEW Customer Copy receipt design below.

I want this SAME visual design integrated into the CURRENT Tailor Management

Customer Copy receipt.

\==================================================

ABSOLUTE SCOPE

\==================================================

REPLACE ONLY:

CUSTOMER COPY

Keep:

WORKSHOP COPY

100% unchanged.

Existing buttons must continue working:

Customer only

Workshop only

Print both

Existing printThermal() functionality must continue working.

VERY IMPORTANT:

The current print system identifies the customer receipt using:

id="slip-customer"

KEEP THIS ID.

The workshop receipt uses:

id="slip-tailor"

DO NOT TOUCH IT.

\==================================================

DO NOT CREATE A STANDALONE HTML PAGE

\==================================================

The HTML/CSS/JS code provided below is the VISUAL DESIGN SOURCE.

DO NOT paste:

as another page inside Laravel.

Instead:

1\. Extract ONLY the required receipt CSS.

2\. Namespace it under the new Customer Copy \`.rc\` design.

3\. Replace only the current \`customerSlip\` markup/template.

4\. Use CURRENT live order/customer/shop data.

5\. Preserve the current modal and printing infrastructure.

\==================================================

IMPORTANT CSS SAFETY

\==================================================

The supplied standalone template contains global rules such as:

\* { ... }

body { ... }

and:

@page { size:80mm auto; margin:0 }

DO NOT copy those global rules directly into the Laravel page.

The application already has a tested thermal print system.

DO NOT reintroduce global \`@page\` or body CSS.

Preserve the CURRENT project's working:

\- thermal page sizing

\- 72mm printable canvas

\- #thermal-print-area

\- printing-thermal state

\- print clone logic

\- Customer only / Workshop only / Print both behavior

Use the NEW visual design INSIDE the existing thermal canvas.

The physical paper is 80mm but the current project correctly uses approximately

72mm printable width.

Match the supplied design visually within that existing width.

\==================================================

DO NOT AFFECT WORKSHOP COPY CSS

\==================================================

All new styles must be namespaced to:

.rc

or

.slip-customer-new

Do NOT change:

.slip-workshop

.slip-mgrid

.slip-mcell

.slip-note

Workshop receipt typography

Workshop measurement layout

Workshop instructions

Workshop garment summary

The Workshop Copy is already approved.

\==================================================

DYNAMIC DATA — NO HARDCODED SAMPLE ORDER

\==================================================

The supplied template contains sample data:

ORD-1074

INV-1074

Waqas

0327 3142770

Boski

Cotton

Wash & Wear

etc.

DO NOT hardcode any of these.

Replace sample data with the CURRENT order data already passed into:

window.modals\['thermal-receipt'\]

and openReceipt().

Use the existing receipt/order object.

Map approximately:

SHOP.name

\=> current shop/store name

SHOP.tag

\=> current shop tagline

SHOP.addr

\=> current shop address

SHOP.ph

\=> current shop phone

BOOKING.no

\=> o.order

BOOKING.invoice

\=> o.invoice

BOOKING.date

\=> o.date

BOOKING.customer

\=> o.customer

BOOKING.phone

\=> o.customer\_ph

BOOKING.items

\=> o.items

item.name

\=> current garment/service name

item.qty

\=> quantity

item.each

\=> current unit\_price

item.total

\=> current item total / price

BOOKING.advance

\=> actual current paid/advance amount

BOOKING.delivery

\=> o.due

BALANCE

\=> use the CURRENT backend/order balance

DO NOT independently create incorrect financial values.

\==================================================

FINANCIAL DATA MUST REMAIN AUTHORITATIVE

\==================================================

The new receipt is a DESIGN change only.

Do NOT change:

order\_items.unit\_price

order total

subtotal calculations

discounts

charges

payments

advance

balance

previous due

customer total due

ledger

Use the financial values already calculated by the existing application.

If the current receipt has additional legitimate billing lines

(discount, charges, previous due, current order due, customer total due etc.),

keep the current financial truth.

Render any required extra lines using the SAME new \`.rc-tr\` visual style.

DO NOT lose financial information merely because the standalone sample did not

contain that field.

\==================================================

CUSTOMER RECEIPT DESIGN

\==================================================

The new Customer Copy should visually contain:

BEST TAILOR

TAILORING & CLOTH HOUSE

Near Al Falah Bank, Pakora Stop, Qasimabad, Hyderabad

PH · 0317 3780121

\--------------------------------

BOOKING RECEIPT · CUSTOMER COPY

Order ........ ORD-xxxx

Invoice ...... INV-xxxx

Date ......... xx/xx/xxxx

Customer ..... Customer Name

Phone ........ Phone

\------------- ITEMS -------------

Boski × 1

Rs.2,500.00 each ................. Rs.2,500.00

Cotton × 2

Rs.2,000.00 each ................. Rs.4,000.00

Wash & Wear × 2

Rs.2,000.00 each ................. Rs.4,000.00

\------------ PAYMENT ------------

Subtotal ........................ Rs.xx

\[ TOTAL BOX Rs.xx \]

Advance Paid ................... Rs.xx

Balance ........................ Rs.xx

\[ DELIVERY · Sep 16 · 8:47 PM \]

Thank you for choosing us.

Please bring this receipt when collecting your order.

\[ SOFTWARE CREDIT BOX \]

THANK YOU

\==================================================

SHOP SETTINGS

\==================================================

Where the current project already gets shop details from Settings,

continue using Settings.

Do NOT replace dynamic shop details with hardcoded values unless that specific

software credit is intentionally fixed.

Use current:

store/shop name

tagline

address

phone

from the existing receipt object/settings.

\==================================================

SOFTWARE CREDIT

\==================================================

Keep this exact credit content/design:

Powered by

TAILORING & CLOTH HOUSE MANAGEMENT SYSTEM

Designed & Developed by

NOOR M HINGORJO

Software Support

0303 4980786

Keep it inside the exact new bordered credit box style.

Do not duplicate this information elsewhere on the receipt.

\==================================================

LOGO

\==================================================

The provided design intentionally uses text branding:

BEST TAILOR

Do NOT insert a large logo that changes this design.

If existing configuration requires logo handling, do not allow it to distort

this new Customer Copy layout.

Workshop Copy behavior must remain untouched.

\==================================================

FONT

\==================================================

The supplied design uses:

IBM Plex Mono

Space Grotesk

Use these fonts for \`.rc\` only.

Do NOT globally change application fonts.

Load them only once in a safe way.

If the remote font is temporarily unavailable, include sensible fallbacks so the

receipt remains printable.

Do NOT change Workshop Copy font.

\==================================================

EXACT REFERENCE DESIGN CODE

\==================================================

Use the following code as the visual source of truth.

Preserve its:

\- typography

\- hierarchy

\- spacing

\- dotted leaders

\- section separators

\- item arrangement

\- payment rows

\- Total box

\- Delivery status box

\- developer credit box

\- Thank You separator

\- clean monochrome style

ADAPT ONLY what is technically required to integrate it into the current Laravel

receipt architecture.

\---------------- REFERENCE START ----------------

Best Tailor — Booking Receipt · Customer Copy

</p><p class="slate-paragraph">\*{box-sizing:border-box;margin:0;padding:0}</p><p class="slate-paragraph">body{background:#fff;min-height:100vh;display:flex;justify-content:center;align-items:flex-start;padding:32px 16px;font-family:&#x27;Space Grotesk&#x27;,sans-serif;-webkit-font-smoothing:antialiased}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc{</p><p class="slate-paragraph"> width:302px;</p><p class="slate-paragraph"> background:#fff;</p><p class="slate-paragraph"> color:#141414;</p><p class="slate-paragraph"> padding:19px 15px 21px;</p><p class="slate-paragraph"> font:400 10px/1.5 &#x27;IBM Plex Mono&#x27;</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-head{text-align:center}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-name{</p><p class="slate-paragraph"> font:700 23px/1.1 &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.03em;</p><p class="slate-paragraph"> color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tag{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> align-items:center;</p><p class="slate-paragraph"> gap:8px;</p><p class="slate-paragraph"> margin:9px 0 0</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tag::before,</p><p class="slate-paragraph">.rc-tag::after{</p><p class="slate-paragraph"> content:&#x27;&#x27;;</p><p class="slate-paragraph"> flex:1;</p><p class="slate-paragraph"> height:1px;</p><p class="slate-paragraph"> background:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tag span{</p><p class="slate-paragraph"> font:600 7.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.32em;</p><p class="slate-paragraph"> margin-right:-.32em;</p><p class="slate-paragraph"> text-transform:uppercase;</p><p class="slate-paragraph"> white-space:nowrap</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-addr{</p><p class="slate-paragraph"> font:400 8.5px/1.55 &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#444;</p><p class="slate-paragraph"> margin-top:8px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-ph{</p><p class="slate-paragraph"> font:600 8.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> margin-top:2px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-rule{</p><p class="slate-paragraph"> height:1.5px;</p><p class="slate-paragraph"> background:#000;</p><p class="slate-paragraph"> border:0;</p><p class="slate-paragraph"> margin:12px 0 14px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-doc{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> justify-content:center;</p><p class="slate-paragraph"> margin:3px 0 13px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-doc b{</p><p class="slate-paragraph"> font:700 8.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.3em;</p><p class="slate-paragraph"> margin-right:-.3em;</p><p class="slate-paragraph"> text-transform:uppercase;</p><p class="slate-paragraph"> color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-meta{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> flex-direction:column;</p><p class="slate-paragraph"> gap:6px;</p><p class="slate-paragraph"> margin:0 0 3px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-m{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> align-items:baseline;</p><p class="slate-paragraph"> gap:6px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-m .k{</p><p class="slate-paragraph"> font:600 8px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.12em;</p><p class="slate-paragraph"> color:#5c5c5c;</p><p class="slate-paragraph"> white-space:nowrap;</p><p class="slate-paragraph"> text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-m .dots{</p><p class="slate-paragraph"> flex:1;</p><p class="slate-paragraph"> min-width:12px;</p><p class="slate-paragraph"> border-bottom:1px dotted #9a9a9a;</p><p class="slate-paragraph"> transform:translateY(-3px)</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-m .v{</p><p class="slate-paragraph"> font:500 10.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-m .v.b{font-weight:700}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-sec{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> align-items:center;</p><p class="slate-paragraph"> gap:8px;</p><p class="slate-paragraph"> margin:16px 0 8px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-sec::before,</p><p class="slate-paragraph">.rc-sec::after{</p><p class="slate-paragraph"> content:&#x27;&#x27;;</p><p class="slate-paragraph"> flex:1;</p><p class="slate-paragraph"> height:1px;</p><p class="slate-paragraph"> background:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-sec span{</p><p class="slate-paragraph"> font:700 8px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.3em;</p><p class="slate-paragraph"> margin-right:-.3em;</p><p class="slate-paragraph"> text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-item{padding:7px 0}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-item+.rc-item{</p><p class="slate-paragraph"> border-top:1px dashed #d5d5d5</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i1{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> justify-content:space-between;</p><p class="slate-paragraph"> align-items:baseline;</p><p class="slate-paragraph"> gap:8px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i1 .nm{</p><p class="slate-paragraph"> font:600 11px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i1 .qt{</p><p class="slate-paragraph"> font:500 9px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#555;</p><p class="slate-paragraph"> white-space:nowrap</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i2{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> align-items:baseline;</p><p class="slate-paragraph"> gap:6px;</p><p class="slate-paragraph"> margin-top:2px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i2 .rt{</p><p class="slate-paragraph"> font:400 8.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#5a5a5a;</p><p class="slate-paragraph"> white-space:nowrap</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i2 .dots{</p><p class="slate-paragraph"> flex:1;</p><p class="slate-paragraph"> min-width:10px;</p><p class="slate-paragraph"> border-bottom:1px dotted #a5a5a5;</p><p class="slate-paragraph"> transform:translateY(-3px)</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-i2 .tt{</p><p class="slate-paragraph"> font:700 11px &#x27;IBM Plex Mono&#x27;</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tot{margin-top:3px}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> align-items:baseline;</p><p class="slate-paragraph"> gap:6px;</p><p class="slate-paragraph"> padding:3.5px 0</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr .k{</p><p class="slate-paragraph"> font:600 8px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.14em;</p><p class="slate-paragraph"> color:#555;</p><p class="slate-paragraph"> white-space:nowrap;</p><p class="slate-paragraph"> text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr .dots{</p><p class="slate-paragraph"> flex:1;</p><p class="slate-paragraph"> min-width:10px;</p><p class="slate-paragraph"> border-bottom:1px dotted #a5a5a5;</p><p class="slate-paragraph"> transform:translateY(-3px)</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr .v{</p><p class="slate-paragraph"> font:600 10.5px &#x27;IBM Plex Mono&#x27;</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr.due .k{color:#000}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr.due .v{</p><p class="slate-paragraph"> font-weight:700;</p><p class="slate-paragraph"> font-size:11.5px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr.sub .k{color:#666}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tr.sub .v{</p><p class="slate-paragraph"> font-weight:500;</p><p class="slate-paragraph"> font-size:9.5px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tb{</p><p class="slate-paragraph"> border:1.5px solid #000;</p><p class="slate-paragraph"> border-radius:3px;</p><p class="slate-paragraph"> margin:10px 0 8px;</p><p class="slate-paragraph"> padding:10px 12px;</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> justify-content:space-between;</p><p class="slate-paragraph"> align-items:center</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tb span{</p><p class="slate-paragraph"> font:700 9px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.24em;</p><p class="slate-paragraph"> text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-tb b{</p><p class="slate-paragraph"> font:700 15px &#x27;IBM Plex Mono&#x27;</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-stat{</p><p class="slate-paragraph"> margin-top:13px;</p><p class="slate-paragraph"> text-align:center;</p><p class="slate-paragraph"> font:700 7.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.18em;</p><p class="slate-paragraph"> border:1px solid #000;</p><p class="slate-paragraph"> border-radius:3px;</p><p class="slate-paragraph"> padding:6px 4px;</p><p class="slate-paragraph"> text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-note{</p><p class="slate-paragraph"> text-align:center;</p><p class="slate-paragraph"> font:400 8.5px/1.65 &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#333;</p><p class="slate-paragraph"> margin-top:13px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-g{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> align-items:baseline;</p><p class="slate-paragraph"> gap:6px;</p><p class="slate-paragraph"> padding:5px 0</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-g .nm{</p><p class="slate-paragraph"> font:600 11px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-g .dots{</p><p class="slate-paragraph"> flex:1;</p><p class="slate-paragraph"> min-width:10px;</p><p class="slate-paragraph"> border-bottom:1px dotted #a5a5a5;</p><p class="slate-paragraph"> transform:translateY(-3px)</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-g .qt{</p><p class="slate-paragraph"> font:600 10px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#000;</p><p class="slate-paragraph"> white-space:nowrap</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-ms{</p><p class="slate-paragraph"> display:grid;</p><p class="slate-paragraph"> grid-template-columns:1fr 1fr;</p><p class="slate-paragraph"> column-gap:18px;</p><p class="slate-paragraph"> row-gap:5px;</p><p class="slate-paragraph"> margin-top:2px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-ms .w{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> align-items:baseline;</p><p class="slate-paragraph"> gap:5px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-ms .w .k{</p><p class="slate-paragraph"> font:600 7.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.05em;</p><p class="slate-paragraph"> color:#5c5c5c;</p><p class="slate-paragraph"> text-transform:uppercase;</p><p class="slate-paragraph"> white-space:nowrap</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-ms .w .dots{</p><p class="slate-paragraph"> flex:1;</p><p class="slate-paragraph"> min-width:6px;</p><p class="slate-paragraph"> border-bottom:1px dotted #a5a5a5;</p><p class="slate-paragraph"> transform:translateY(-3px)</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-ms .w .v{</p><p class="slate-paragraph"> font:600 9.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-inst{</p><p class="slate-paragraph"> border:1px solid #000;</p><p class="slate-paragraph"> border-radius:3px;</p><p class="slate-paragraph"> padding:8px 10px 9px;</p><p class="slate-paragraph"> margin-top:4px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-inst .lbl{</p><p class="slate-paragraph"> font:400 7px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#8a8a8a;</p><p class="slate-paragraph"> letter-spacing:.2em;</p><p class="slate-paragraph"> margin-right:-.2em;</p><p class="slate-paragraph"> text-transform:uppercase;</p><p class="slate-paragraph"> margin-bottom:3px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-inst .txt{</p><p class="slate-paragraph"> font:400 8.5px/1.6 &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#333</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit{</p><p class="slate-paragraph"> margin-top:15px;</p><p class="slate-paragraph"> border:1px solid #000;</p><p class="slate-paragraph"> border-radius:3px;</p><p class="slate-paragraph"> padding:10px 10px 12px;</p><p class="slate-paragraph"> text-align:center</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c1{</p><p class="slate-paragraph"> font:400 7px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#8a8a8a;</p><p class="slate-paragraph"> letter-spacing:.28em;</p><p class="slate-paragraph"> margin-right:-.28em;</p><p class="slate-paragraph"> text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c2{</p><p class="slate-paragraph"> font:700 7.5px/1.6 &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.04em;</p><p class="slate-paragraph"> margin-top:4px;</p><p class="slate-paragraph"> color:#000;</p><p class="slate-paragraph"> text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .cline{</p><p class="slate-paragraph"> display:block;</p><p class="slate-paragraph"> width:24px;</p><p class="slate-paragraph"> height:1.5px;</p><p class="slate-paragraph"> background:#000;</p><p class="slate-paragraph"> margin:7px auto</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c3{</p><p class="slate-paragraph"> font:700 8.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.08em;</p><p class="slate-paragraph"> color:#000;</p><p class="slate-paragraph"> text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c3 span{</p><p class="slate-paragraph"> font:400 7.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#8a8a8a;</p><p class="slate-paragraph"> letter-spacing:.08em;</p><p class="slate-paragraph"> margin-right:6px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c4{</p><p class="slate-paragraph"> font:600 9px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#000;</p><p class="slate-paragraph"> letter-spacing:.03em;</p><p class="slate-paragraph"> margin-top:5px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-credit .c4 span{</p><p class="slate-paragraph"> font:400 7.5px &#x27;IBM Plex Mono&#x27;;</p><p class="slate-paragraph"> color:#8a8a8a;</p><p class="slate-paragraph"> letter-spacing:.08em;</p><p class="slate-paragraph"> margin-right:6px;</p><p class="slate-paragraph"> text-transform:uppercase</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-thx{</p><p class="slate-paragraph"> display:flex;</p><p class="slate-paragraph"> align-items:center;</p><p class="slate-paragraph"> gap:8px;</p><p class="slate-paragraph"> margin-top:13px</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-thx::before,</p><p class="slate-paragraph">.rc-thx::after{</p><p class="slate-paragraph"> content:&#x27;&#x27;;</p><p class="slate-paragraph"> flex:1;</p><p class="slate-paragraph"> height:1px;</p><p class="slate-paragraph"> background:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">.rc-thx span{</p><p class="slate-paragraph"> font:700 8.5px &#x27;Space Grotesk&#x27;;</p><p class="slate-paragraph"> letter-spacing:.4em;</p><p class="slate-paragraph"> margin-right:-.4em;</p><p class="slate-paragraph"> text-transform:uppercase;</p><p class="slate-paragraph"> color:#000</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">@media screen{</p><p class="slate-paragraph"> .rc{</p><p class="slate-paragraph"> border:1px solid #BFBAB0</p><p class="slate-paragraph"> }</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">@media print{</p><p class="slate-paragraph"> .rc{</p><p class="slate-paragraph"> width:100%;</p><p class="slate-paragraph"> max-width:100%;</p><p class="slate-paragraph"> }</p><p class="slate-paragraph">}</p><p class="slate-paragraph">

</p><p class="slate-paragraph"></p><p class="slate-paragraph">const SHOP = {</p><p class="slate-paragraph"> name:&#x27;BEST TAILOR&#x27;,</p><p class="slate-paragraph"> tag:&#x27;Tailoring & Cloth House&#x27;,</p><p class="slate-paragraph"> addr:&#x27;Near Al Falah Bank, Pakora Stop, Qasimabad, Hyderabad&#x27;,</p><p class="slate-paragraph"> ph:&#x27;0317 3780121&#x27;,</p><p class="slate-paragraph"> devName:&#x27;NOOR M HINGORJO&#x27;,</p><p class="slate-paragraph"> devSys:&#x27;TAILORING & CLOTH HOUSE MANAGEMENT SYSTEM&#x27;,</p><p class="slate-paragraph"> devSysSales:&#x27;POS & MANAGEMENT SYSTEM&#x27;,</p><p class="slate-paragraph"> devPh:&#x27;0303 4980786&#x27;</p><p class="slate-paragraph">};</p><p class="slate-paragraph"></p><p class="slate-paragraph">const rs = n =></p><p class="slate-paragraph"> &#x27;Rs.&#x27; +</p><p class="slate-paragraph"> Number(n||0).toLocaleString(</p><p class="slate-paragraph"> &#x27;en-US&#x27;,</p><p class="slate-paragraph"> {</p><p class="slate-paragraph"> minimumFractionDigits:2,</p><p class="slate-paragraph"> maximumFractionDigits:2</p><p class="slate-paragraph"> }</p><p class="slate-paragraph"> );</p><p class="slate-paragraph"></p><p class="slate-paragraph">const headHTML = () => \`</p><p class="slate-paragraph"> <header class="rc-head"></p><p class="slate-paragraph"> <div class="rc-name">${SHOP.name}</div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="rc-tag"></p><p class="slate-paragraph"> <span>${SHOP.tag}</span></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="rc-addr">${SHOP.addr}</div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="rc-ph"></p><p class="slate-paragraph"> PH · ${SHOP.ph}</p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"> </header></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="rc-rule"></div></p><p class="slate-paragraph">\`;</p><p class="slate-paragraph"></p><p class="slate-paragraph">const doc = t => \`</p><p class="slate-paragraph"> <div class="rc-doc"></p><p class="slate-paragraph"> <b>${t}</b></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph">\`;</p><p class="slate-paragraph"></p><p class="slate-paragraph">const mrow = (k,v,b) => \`</p><p class="slate-paragraph"> <div class="rc-m"></p><p class="slate-paragraph"> <span class="k">${k}</span></p><p class="slate-paragraph"> <i class="dots"></i></p><p class="slate-paragraph"> <span class="v${b?&#x27; b&#x27;:&#x27;&#x27;}">${v}</span></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph">\`;</p><p class="slate-paragraph"></p><p class="slate-paragraph">const sec = t => \`</p><p class="slate-paragraph"> <div class="rc-sec"></p><p class="slate-paragraph"> <span>${t}</span></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph">\`;</p><p class="slate-paragraph"></p><p class="slate-paragraph">const trow = (k,v,cls) => \`</p><p class="slate-paragraph"> <div class="rc-tr${cls?&#x27; &#x27;+cls:&#x27;&#x27;}"></p><p class="slate-paragraph"> <span class="k">${k}</span></p><p class="slate-paragraph"> <i class="dots"></i></p><p class="slate-paragraph"> <span class="v">${v}</span></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph">\`;</p><p class="slate-paragraph"></p><p class="slate-paragraph">const tbox = v => \`</p><p class="slate-paragraph"> <div class="rc-tb"></p><p class="slate-paragraph"> <span>Total</span></p><p class="slate-paragraph"> <b>${v}</b></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph">\`;</p><p class="slate-paragraph"></p><p class="slate-paragraph">const stat = t => \`</p><p class="slate-paragraph"> <div class="rc-stat"></p><p class="slate-paragraph"> ${t}</p><p class="slate-paragraph"> </div></p><p class="slate-paragraph">\`;</p><p class="slate-paragraph"></p><p class="slate-paragraph">const note = (a,b) => \`</p><p class="slate-paragraph"> <p class="rc-note"></p><p class="slate-paragraph"> ${a}<br></p><p class="slate-paragraph"> ${b}</p><p class="slate-paragraph"> </p></p><p class="slate-paragraph">\`;</p><p class="slate-paragraph"></p><p class="slate-paragraph">const itemsHTML = items => items.map(i => \`</p><p class="slate-paragraph"> <div class="rc-item"></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="rc-i1"></p><p class="slate-paragraph"> <span class="nm">${i.name}</span></p><p class="slate-paragraph"> <span class="qt">× ${i.qty}</span></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="rc-i2"></p><p class="slate-paragraph"> <span class="rt">${rs(i.each)} each</span></p><p class="slate-paragraph"> <i class="dots"></i></p><p class="slate-paragraph"> <span class="tt">${rs(i.total)}</span></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph">\`).join(&#x27;&#x27;);</p><p class="slate-paragraph"></p><p class="slate-paragraph">const credit = sys => \`</p><p class="slate-paragraph"> <footer class="rc-credit"></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="c1"></p><p class="slate-paragraph"> Powered by</p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="c2"></p><p class="slate-paragraph"> ${sys}</p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <i class="cline"></i></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="c3"></p><p class="slate-paragraph"> <span>Designed &amp; Developed by</span></p><p class="slate-paragraph"> ${SHOP.devName}</p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="c4"></p><p class="slate-paragraph"> <span>Software Support</span></p><p class="slate-paragraph"> ${SHOP.devPh}</p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> </footer></p><p class="slate-paragraph">\`;</p><p class="slate-paragraph"></p><p class="slate-paragraph">const thx = () => \`</p><p class="slate-paragraph"> <div class="rc-thx"></p><p class="slate-paragraph"> <span>Thank You</span></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph">\`;</p><p class="slate-paragraph"></p><p class="slate-paragraph">const BOOKING = {</p><p class="slate-paragraph"> no:&#x27;ORD-1074&#x27;,</p><p class="slate-paragraph"> invoice:&#x27;INV-1074&#x27;,</p><p class="slate-paragraph"> date:&#x27;16/9/2026&#x27;,</p><p class="slate-paragraph"></p><p class="slate-paragraph"> customer:&#x27;Waqas&#x27;,</p><p class="slate-paragraph"> phone:&#x27;0327 3142770&#x27;,</p><p class="slate-paragraph"></p><p class="slate-paragraph"> items:\[</p><p class="slate-paragraph"> {</p><p class="slate-paragraph"> name:&#x27;Boski&#x27;,</p><p class="slate-paragraph"> qty:1,</p><p class="slate-paragraph"> each:2500,</p><p class="slate-paragraph"> total:2500</p><p class="slate-paragraph"> },</p><p class="slate-paragraph"> {</p><p class="slate-paragraph"> name:&#x27;Cotton&#x27;,</p><p class="slate-paragraph"> qty:2,</p><p class="slate-paragraph"> each:2000,</p><p class="slate-paragraph"> total:4000</p><p class="slate-paragraph"> },</p><p class="slate-paragraph"> {</p><p class="slate-paragraph"> name:&#x27;Wash & Wear&#x27;,</p><p class="slate-paragraph"> qty:2,</p><p class="slate-paragraph"> each:2000,</p><p class="slate-paragraph"> total:4000</p><p class="slate-paragraph"> }</p><p class="slate-paragraph"> \],</p><p class="slate-paragraph"></p><p class="slate-paragraph"> advance:0,</p><p class="slate-paragraph"></p><p class="slate-paragraph"> delivery:&#x27;Sep 16 · 8:47 PM&#x27;</p><p class="slate-paragraph">};</p><p class="slate-paragraph"></p><p class="slate-paragraph">function render(){</p><p class="slate-paragraph"></p><p class="slate-paragraph"> const SUB =</p><p class="slate-paragraph"> BOOKING.items.reduce(</p><p class="slate-paragraph"> (a,i)=>a+i.total,</p><p class="slate-paragraph"> 0</p><p class="slate-paragraph"> );</p><p class="slate-paragraph"></p><p class="slate-paragraph"> const BAL =</p><p class="slate-paragraph"> Math.max(</p><p class="slate-paragraph"> 0,</p><p class="slate-paragraph"> SUB - BOOKING.advance</p><p class="slate-paragraph"> );</p><p class="slate-paragraph"></p><p class="slate-paragraph"> document.getElementById(&#x27;rc&#x27;).innerHTML = \`</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${headHTML()}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${doc(&#x27;Booking Receipt · Customer Copy&#x27;)}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="rc-meta"></p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${mrow(</p><p class="slate-paragraph"> &#x27;Order&#x27;,</p><p class="slate-paragraph"> BOOKING.no,</p><p class="slate-paragraph"> true</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${mrow(</p><p class="slate-paragraph"> &#x27;Invoice&#x27;,</p><p class="slate-paragraph"> BOOKING.invoice</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${mrow(</p><p class="slate-paragraph"> &#x27;Date&#x27;,</p><p class="slate-paragraph"> BOOKING.date</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${mrow(</p><p class="slate-paragraph"> &#x27;Customer&#x27;,</p><p class="slate-paragraph"> BOOKING.customer,</p><p class="slate-paragraph"> true</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${mrow(</p><p class="slate-paragraph"> &#x27;Phone&#x27;,</p><p class="slate-paragraph"> BOOKING.phone</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${sec(&#x27;Items&#x27;)}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="rc-items"></p><p class="slate-paragraph"> ${itemsHTML(</p><p class="slate-paragraph"> BOOKING.items</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${sec(&#x27;Payment&#x27;)}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> <div class="rc-tot"></p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${trow(</p><p class="slate-paragraph"> &#x27;Subtotal&#x27;,</p><p class="slate-paragraph"> rs(SUB)</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${tbox(</p><p class="slate-paragraph"> rs(SUB)</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${trow(</p><p class="slate-paragraph"> &#x27;Advance Paid&#x27;,</p><p class="slate-paragraph"> rs(BOOKING.advance)</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${trow(</p><p class="slate-paragraph"> &#x27;Balance&#x27;,</p><p class="slate-paragraph"> rs(BAL),</p><p class="slate-paragraph"> &#x27;due&#x27;</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> </div></p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${stat(</p><p class="slate-paragraph"> &#x27;Delivery · &#x27; +</p><p class="slate-paragraph"> BOOKING.delivery</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${note(</p><p class="slate-paragraph"> &#x27;Thank you for choosing us.&#x27;,</p><p class="slate-paragraph"> &#x27;Please bring this receipt when collecting your order.&#x27;</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${credit(</p><p class="slate-paragraph"> SHOP.devSys</p><p class="slate-paragraph"> )}</p><p class="slate-paragraph"></p><p class="slate-paragraph"> ${thx()}</p><p class="slate-paragraph"> \`;</p><p class="slate-paragraph">}</p><p class="slate-paragraph"></p><p class="slate-paragraph">render();</p><p class="slate-paragraph"></p><p class="slate-paragraph">

\---------------- REFERENCE END ----------------

\==================================================

HOW TO ADAPT THIS INTO CURRENT MODAL

\==================================================

DO NOT keep:

const BOOKING = {...}

in production.

Instead build the Customer Copy directly from the existing \`o\` object.

Conceptually:

const customerItems = (o.items || \[\]).map(item => ({

name: item.name,

qty: Number(item.qty || 0),

each: Number(item.unit\_price ?? item.each ?? 0),

total: Number(item.price ?? item.total ?? 0)

}));

Then render the \`.rc\` markup using actual values.

Use escaping through the project's existing:

Atelier.escapeHtml()

for every textual value.

Do NOT inject raw customer/order text.

Use existing money formatting where appropriate.

\==================================================

IMPORTANT ITEM HANDLING

\==================================================

An order can contain:

1 × Boski

2 × Cotton

2 × Wash & Wear

Render each as a separate clean \`.rc-item\`.

Do NOT combine them into one long line.

The expected visual result is:

Boski × 1

Rs.2,500 each ............. Rs.2,500

Cotton × 2

Rs.2,000 each ............. Rs.4,000

Wash & Wear × 2

Rs.2,000 each ............. Rs.4,000

\==================================================

LONG NAMES

\==================================================

Customer names, garment names and addresses may be longer than the sample.

Make the smallest responsive safeguards necessary so:

\- nothing clips

\- nothing overlaps

\- nothing goes outside 72mm printable width

\- quantity remains readable

\- amounts remain readable

Do not visually redesign the supplied template.

\==================================================

PRINT INTEGRATION

\==================================================

Final customer receipt root MUST be:

NOT:

id="rc"

because current Laravel printThermal() expects:

slip-customer.

Keep the Workshop Copy root:

id="slip-tailor"

exactly unchanged.

When printThermal('customer') runs:

only the new Customer Copy should print.

When printThermal('tailor') runs:

the existing Workshop Copy should print unchanged.

When printThermal('both') runs:

new Customer Copy + existing Workshop Copy should print.

\==================================================

PRINT CSS

\==================================================

Integrate \`.rc\` with the existing:

#thermal-print-area

rules.

DO NOT create another global @page rule.

For print, ensure:

#thermal-print-area .rc {

width: 100%;

max-width: 100%;

min-width: 0;

box-sizing: border-box;

background: #fff;

}

Keep the exact existing thermal page driver handling.

\==================================================

SCREEN PREVIEW

\==================================================

The new Customer Copy preview inside the modal must also show this exact design.

It can retain the subtle:

border:1px solid #BFBAB0

on screen only.

Do not print that outer preview-only border unless the reference design actually

requires it.

\==================================================

DO NOT ADD MEASUREMENTS TO CUSTOMER COPY

\==================================================

The CUSTOMER COPY should remain a billing/booking receipt.

Do NOT add Workshop measurements here.

Measurements belong to Workshop Copy.

Do not copy:

Chest

Waist

Hip

Losing

etc.

into Customer Copy.

\==================================================

DO NOT CHANGE WORKSHOP COPY

\==================================================

This is EXTREMELY IMPORTANT.

Do not modify:

Workshop Copy header

Garment display

Measurements

Measurement font sizes

Shared measurement logic

Instructions

Delivery

CUT/STITCH/CHECK

Workshop print width

Workshop receipt data

ZERO Workshop Copy visual changes.

\==================================================

FILES TO INSPECT

\==================================================

Inspect at minimum:

resources/views/orders/index.blade.php

resources/views/receipts/slip-styles.blade.php

app/Http/Controllers/OrderController.php

existing receipt response/payload

tests/Browser/workshop-print.cjs

But modify only what is required for CUSTOMER COPY.

Do not make unrelated cleanup/refactors.

\==================================================

TEST THESE CASES

\==================================================

1\. Single garment:

1 × Boski

2\. Multiple garments:

1 × Boski

2 × Cotton

2 × Wash & Wear

3\. Long customer name.

4\. Long garment name.

5\. Rs.0 advance.

6\. Partial advance/payment.

7\. Fully paid order.

8\. Balance due.

9\. Customer-only printing.

10\. Workshop-only printing.

11\. Print both.

12\. Physical 80mm thermal printer / current 72mm printable canvas.

Verify:

\- no clipping

\- no horizontal overflow

\- amounts stay aligned

\- dotted leaders stay clean

\- Total box remains inside width

\- developer credit remains centered

\- Workshop Copy is visually unchanged

\==================================================

FINAL DESIGN LOCK

\==================================================

I want the NEW CUSTOMER COPY to match the supplied reference visually:

same typography

same spacing

same separators

same dotted leaders

same Items design

same Payment design

same Total box

same Delivery box

same software credit box

same Thank You ending

same clean black/white premium thermal appearance

Do NOT improvise another design.

Only adapt dimensions/data binding where required by the existing Laravel

thermal architecture.

\==================================================

AFTER IMPLEMENTATION REPORT

\==================================================

Report:

1\. Exact files changed.

2\. Exact Customer Copy markup replaced.

3\. How existing order data maps into the new template.

4\. Confirmation no hardcoded sample order remains.

5\. Confirmation actual prices/payments/balance still come from backend.

6\. Confirmation Workshop Copy was untouched.

7\. Confirmation printThermal('customer') works.

8\. Confirmation printThermal('tailor') works.

9\. Confirmation printThermal('both') works.

10\. Tests run and results.

FINAL RULE:

REPLACE ONLY THE EXISTING CUSTOMER COPY VISUAL DESIGN WITH THE PROVIDED DESIGN.

PRESERVE ALL REAL APPLICATION DATA AND EXISTING PRINT FUNCTIONALITY.

DO NOT CHANGE ANYTHING ELSE.