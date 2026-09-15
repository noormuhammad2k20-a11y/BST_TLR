TASK: CLEAN UP ONLY THE TAILOR MANAGEMENT "PRODUCTS & SERVICES" MODULE AND CONVERT IT INTO A SIMPLE CATEGORY → STITCHING SERVICE → PRICE/RATE SYSTEM.

Repository:

https://github.com/noormuhammad2k20-a11y/BST\_TLR

IMPORTANT:

THIS IS A TARGETED CHANGE.

DO NOT redesign the application.

DO NOT change the application's theme.

DO NOT change unrelated functionality.

DO NOT refactor unrelated code.

DO NOT touch Cloth Store inventory.

DO NOT break existing orders, measurements, receipts, delivery, payments, ledgers, reports, staff, SMS, settings, or any other working module.

The existing Tailor Management design/theme is already approved.

FOLLOW THE EXACT SAME EXISTING UI DESIGN LANGUAGE:

\- same cards

\- same spacing

\- same typography

\- same borders

\- same buttons

\- same modal style

\- same colors/theme tokens

\- same sidebar

\- same light/dark/theme behavior

\- same responsive behavior

Do NOT introduce a new visual style.

\==================================================

CURRENT PROBLEM

\==================================================

Tailor Management currently has:

Products & Services

but this page has been incorrectly mixed with inventory/product-management functionality.

The Tailor Management Products & Services page currently contains fields/features such as:

\- SKU

\- Cost Price

\- Stock Quantity

\- Low Stock Alert

\- Unit

\- inventory-type logic

\- physical Product/Fabric logic

\- low-stock warnings

These concepts DO NOT belong on the Tailor Management stitching-rate page.

The project already has a completely separate Cloth Store module for physical fabric inventory.

Cloth Store already owns concepts such as:

\- SKU

\- Barcode

\- Cost Price

\- Stock Quantity

\- Reserved Stock

\- Incoming Stock

\- Low Stock

\- Fabric inventory

\- Meter stock

\- physical products

DO NOT duplicate those features inside Tailor Management.

\==================================================

VERY IMPORTANT SEPARATION

\==================================================

TAILOR MANAGEMENT:

Category

↓

Stitching Service / Subcategory

↓

Stitching Price

↓

Measurement Profile

↓

Requires Measurements

↓

Status

CLOTH STORE:

Fabric/Product

SKU

Barcode

Cost

Stock

Meters

Inventory

Low Stock

etc.

THE TWO SYSTEMS MUST REMAIN SEPARATE.

DO NOT modify any \`cs\_\*\` Cloth Store tables/models/controllers/views for this task.

DO NOT connect the Tailor Management stitching-rate page directly to \`cs\_products\`.

DO NOT convert Cloth Store categories into shared inventory data unless an existing intentional architecture already requires it.

\==================================================

FILES / CODE TO DEEPLY INSPECT FIRST

\==================================================

Before changing anything, inspect the complete flow around:

resources/views/products-services/index.blade.php

app/Http/Controllers/ProductServiceController.php

app/Models/ProductService.php

app/Http/Controllers/OrderController.php

app/Http/Requests/StoreOrderRequest.php

app/Http/Requests/UpdateOrderRequest.php

app/Services/OrderItemsService.php

app/Services/MeasurementProfiles.php

app/Services/CatalogueIdentity.php

resources/views/orders/index.blade.php

all migrations related to:

product\_services

orders

order\_items

measurements

and any tests involving:

ProductService

Products & Services

order creation

measurement profiles

order items

Also inspect all references to:

product\_services

product\_service\_id

canonical\_id

normalized\_name

measurement\_profile

requires\_measurements

sku

stock\_quantity

low\_stock\_threshold

cost\_price

duration\_days

unit

Do not make assumptions before tracing references.

\==================================================

DATABASE FACTS THAT MUST BE PRESERVED

\==================================================

The existing \`product\_services\` table currently contains fields such as:

id

name

sku

category

type

price

cost\_price

stock\_quantity

low\_stock\_threshold

unit

duration\_days

status

description

canonical\_id

normalized\_name

measurement\_profile

requires\_measurements

Historical Tailor orders already reference \`product\_services\`.

Both:

orders.product\_service\_id

and:

order\_items.product\_service\_id

have relationships with \`product\_services\`.

Therefore:

DO NOT DROP \`product\_services\`.

DO NOT DELETE EXISTING REFERENCED ROWS.

DO NOT DROP OLD INVENTORY COLUMNS in this task.

DO NOT rewrite historical order\_items.

DO NOT change old receipt/order history.

DO NOT break foreign keys.

Legacy database columns may remain for backwards compatibility even if the new Tailor UI no longer exposes them.

\==================================================

IMPORTANT LEGACY DATA

\==================================================

There are legacy physical items mixed into the Tailor \`product\_services\` catalogue, for example:

Italian Wool Navy

Signature Buttons Set

There are also old/duplicate/canonical service records such as multiple Shalwar Kameez / Sherwani records linked through \`canonical\_id\`.

Some historical orders already reference these records.

For example historical Tailor orders have used an Italian Wool Navy product\_service.

THEREFORE:

DO NOT HARD DELETE THESE ROWS.

Historical orders and old receipts must continue rendering exactly as before.

Instead:

\- exclude legacy physical products/fabrics from the ACTIVE Tailor stitching-rate management UI

\- exclude them from NEW Tailor order service selection

\- preserve them for historical relationships

\- safely mark/filter them as legacy/inactive if necessary

\- do not alter historical order snapshots

\==================================================

NEW CLIENT-REQUIRED CONCEPT

\==================================================

The Tailor Management page should now act as a:

STITCHING RATES / TAILORING SERVICES

manager.

The client wants a very simple structure such as:

Wash & Wear

Shalwar Kameez Stitching — Rs 2,500

Kurta Pajama Stitching — Rs 2,800

Cotton

Shalwar Kameez Stitching — Rs X

Kurta Pajama Stitching — Rs X

Boski

Shalwar Kameez Stitching — Rs X

etc.

The exact architecture must support:

CATEGORY

→ SERVICE / SUBCATEGORY

→ STITCHING PRICE

A service should still retain the measurement information required by the Tailor order workflow.

\==================================================

PAGE NAME

\==================================================

Keep existing route names/internal backend names where changing them would create unnecessary risk.

The visible page can be renamed from:

Products & Services

to preferably:

Stitching Rates

or:

Tailoring Services

Use whichever fits the existing sidebar/UI language best.

DO NOT rename internal routes/models/controllers just for cosmetic purposes if it may break existing links.

\==================================================

FIELDS THAT SHOULD BE VISIBLE

\==================================================

For the Tailor service/rate manager, the user should only deal with relevant fields:

Category

Service / Subcategory Name

Stitching Price

Measurement Profile

Requires Measurements

Status

Description (optional)

If turnaround/duration is genuinely used elsewhere in the existing working Tailor workflow, it MAY remain as an optional field.

But first prove it is used.

Do not keep a field just because it exists in the database.

\==================================================

REMOVE FROM TAILOR UI

\==================================================

Remove these from the Tailor Products & Services / Stitching Rates UI:

SKU

Stock Qty

Low Stock Alert

Cost Price

Unit

stock indicators

low-stock badges

physical inventory status

inventory warnings

"stocked item" language

"products" language where it refers to physical inventory

Do not show these fields in Add Service.

Do not show them in Edit Service.

Do not show them on service cards.

Do not send them from the Tailor page's JavaScript save payload.

Do not trigger low-stock notifications from a Tailor stitching service edit/create action.

IMPORTANT:

Do NOT remove these columns from the database because historical compatibility must remain intact.

\==================================================

CATEGORY + SERVICE/RATE DATA MODEL

\==================================================

We need a safe parent → child/rate relationship.

The requirement is that the SAME stitching service may have different prices under different categories.

Example:

Wash & Wear

Shalwar Kameez Stitching = Rs 2,500

Cotton

Shalwar Kameez Stitching = Rs 2,700

Therefore DO NOT create an implementation where global uniqueness of the service name prevents the same stitching service appearing under two categories with different prices.

Audit the current:

name unique validation

normalized\_name unique validation

canonical\_id logic

before implementing this.

Use the SMALLEST SAFE data-model extension necessary.

Preferred conceptual architecture:

Tailor Category

↓

Tailor Service Rate

Where a rate connects:

category

service definition

price

The existing \`product\_services\` service identity/measurement profile may continue to serve as the canonical stitching-service definition if that is the safest compatible design.

For example conceptually:

tailor\_service\_categories

\- id

\- name

\- status/order if needed

tailor\_service\_rates

\- id

\- category\_id

\- product\_service\_id

\- price

\- status

This is ONLY a conceptual recommendation.

Do not create tables blindly.

FIRST inspect the current architecture and choose the minimum non-destructive solution.

Critical requirement:

DO NOT use Cloth Store \`cs\_products\` as Tailor services.

DO NOT merge Cloth Store inventory and Tailor stitching rates.

\==================================================

CATEGORY MANAGEMENT

\==================================================

The Tailor page should allow the client to manage categories simply.

Example categories can include:

Wash & Wear

Cotton

Boski

Khaddar

Lawn

BUT:

Do not hardcode these as the only possible categories.

Client must be able to:

Add Category

Edit Category

Deactivate or safely remove unused Category

If a category is already referenced by rates/history, prevent destructive deletion or safely deactivate it.

Use the existing application modal/confirmation style.

NO new design.

\==================================================

SERVICE / SUBCATEGORY MANAGEMENT

\==================================================

Inside each Category, client should be able to add a stitching service/rate.

Example:

Category:

Wash & Wear

Service:

Shalwar Kameez Stitching

Stitching Price:

Rs 2,500

Measurement Profile:

Shalwar Kameez

Requires Measurements:

Yes

Status:

Active

The Add/Edit experience must remain simple.

NO SKU.

NO STOCK.

NO LOW STOCK.

NO COST PRICE.

NO BARCODE.

NO INVENTORY UNIT.

\==================================================

PAGE UI

\==================================================

FOLLOW THE CURRENT DESIGN EXACTLY.

Do NOT redesign the application.

Suggested interaction while preserving existing design:

Page header:

Stitching Rates

Small subtitle:

Manage tailoring categories, services and stitching prices

Existing-style button:

\+ Add Category

Then category sections/cards.

Example:

Wash & Wear Edit Category

Shalwar Kameez Stitching

Rs 2,500

Active

Edit

Kurta Pajama Stitching

Rs 2,800

Active

Edit

\+ Add Stitching Service

Cotton Edit Category

Shalwar Kameez Stitching

Rs 2,700

Active

Edit

etc.

This is conceptual.

Use the current application's exact:

card styles

radius

shadows

font sizes

spacing

button styles

badges

modal structure

responsive behavior.

Do NOT introduce:

new gradients

different branding

different colors

new design system

new sidebar styles.

\==================================================

ORDER CREATION INTEGRATION

\==================================================

THIS IS CRITICAL.

The current Tailor order creation workflow MUST continue working.

New Order should use the new rate structure safely.

Desired flow:

1\. Category

2\. Stitching Service

3\. Quantity

4\. Measurements

5\. price/total continues through existing order workflow

When Category is selected:

only Active services/rates belonging to that category should appear.

When Service is selected:

\- correct stitching price auto-fills

\- correct canonical \`product\_service\_id\` remains available

\- correct measurement profile loads

\- \`requires\_measurements\` continues working

\- saved measurements compatibility continues working

DO NOT rewrite the entire order wizard.

Modify only the minimum selectors/data needed to support category → service/rate.

All the existing order functionality must continue working:

customer selection

multiple garments

quantity

individual piece measurements

saved measurements

measurement profiles

staff/tailor assignment

priority

delivery date/time

advance/payment

status workflow

order creation

order editing

receipts

workshop copy

SMS

delivery

ledger

DO NOT change their business behavior.

\==================================================

PRICE BEHAVIOR

\==================================================

The stitching rate selected from:

Category + Service

should become the existing unit/service price used by the Tailor order.

Do not introduce a second competing price calculation system.

Reuse existing order pricing flow.

Existing historical orders must continue using their stored/snapshot order prices.

Changing a stitching rate today MUST NOT retroactively alter prices of old orders.

\==================================================

MEASUREMENT PROFILES

\==================================================

Do NOT remove or weaken measurement functionality.

Existing concepts such as:

measurement\_profile

requires\_measurements

MeasurementProfiles::forProduct(...)

saved measurement compatibility

piece-by-piece measurements

are important and must continue working.

A rate/category change must NOT destroy the service's measurement profile.

Example:

Shalwar Kameez Stitching

should still load the Shalwar Kameez measurement profile.

Kurta Pajama

should still use the correct profile.

Waistcoat

should still use its correct profile.

etc.

\==================================================

CANONICAL / DUPLICATE SERVICES

\==================================================

Deeply inspect:

canonical\_id

normalized\_name

CatalogueIdentity

There are legacy duplicate/alias service rows.

Do NOT show aliases/canonical duplicates as separate confusing service cards.

The active management UI should display the clean canonical service concept only.

However:

DO NOT delete aliases already referenced historically.

Preserve backward compatibility.

Make sure existing historical orders referencing alias IDs still display correctly.

\==================================================

PRODUCTSERVICE CONTROLLER CLEANUP

\==================================================

ProductServiceController currently contains inventory-related behavior.

For Tailor service management:

remove inventory fields from NEW Tailor service create/update validation/payload where safe.

Do not accept or create:

SKU

stock\_quantity

low\_stock\_threshold

cost\_price

unit

from the Tailor Stitching Rates UI.

Do not classify a new tailoring service as Product/Fabric based on stock.

Every newly managed Tailor stitching service should remain semantically a:

Service

Do not trigger low-stock notification logic from Tailor service updates.

BUT preserve compatibility for historical rows and any code paths that still need old records.

Do not broadly delete shared model functionality until all references have been audited.

\==================================================

CLOTH STORE MUST REMAIN UNTOUCHED

\==================================================

Absolutely DO NOT modify:

resources/views/cloth-store/\*

app/Http/Controllers/ClothStore/\*

app/Models/ClothStore/\*

cs\_products

cs\_categories

cs\_orders

cs\_order\_items

cs\_inventory\*

cs\_product\_locations

Cloth Store checkout

Cloth Store stock

Cloth Store receipts

This task is ONLY Tailor Management.

Cloth Store must continue working exactly as it works now.

\==================================================

NO DATABASE DATA LOSS

\==================================================

If database changes are required:

create a NEW migration.

DO NOT edit historical migrations.

DO NOT drop old columns.

DO NOT truncate any table.

DO NOT delete old services.

DO NOT change old order IDs.

DO NOT alter historical order\_item prices.

DO NOT destroy canonical relationships.

Migration must be safe against an existing production database containing real client data.

Migration must be reversible where reasonably possible.

\==================================================

DO NOT TOUCH THESE WORKING AREAS

\==================================================

Do NOT make unrelated changes to:

Customers

Customer Ledger

Orders workflow except category/rate selector integration

Measurements except required integration

Staff

Expenses

Payments

Delivery

SMS

WhatsApp

Reports

Dashboard

Settings

Receipt printing

Tailor thermal receipts

Cloth Store thermal receipts

Sidebar collapse

Authentication

Licensing

Activity logs except normal logging for category/rate changes if appropriate

\==================================================

DESIGN REQUIREMENT

\==================================================

This task is NOT a redesign.

The existing application design is approved.

New Category/Service UI must look like it was always part of the existing application.

Reuse existing:

Tailwind classes

theme variables

modal system

Atelier.confirm

Atelier.api

toast system

loading states

form inputs

badges

cards

buttons

empty states

Do not install any UI library.

Do not add external dependencies.

\==================================================

SECURITY / VALIDATION

\==================================================

Use backend validation.

Category:

\- required

\- sensible max length

\- no accidental duplicates

Service/rate:

\- category required

\- service required

\- price numeric >= 0

\- valid measurement profile

\- status Active/Inactive

Do not trust client-side values only.

Prevent destructive deletion of records referenced by orders.

\==================================================

ACTIVITY / HISTORY

\==================================================

If the current module logs changes through ActivityLogger, preserve that behavior.

Use appropriate messages such as:

Created Stitching Category

Updated Stitching Rate

Deactivated Stitching Rate

Do not flood activity logs with unrelated migration noise.

\==================================================

TESTS REQUIRED

\==================================================

Run existing tests first.

Then test at minimum:

\- Products/Services page opens

\- page uses existing theme

\- no SKU field

\- no stock field

\- no low-stock field

\- no cost-price field

\- category can be created

\- category can be edited

\- category can be safely deactivated

\- stitching service/rate can be created

\- rate can be edited

\- same service can have different rates under different categories if client needs it

\- measurement profile remains connected

\- inactive rates do not appear in New Order

\- selecting category filters services

\- selecting service sets correct price

\- existing order can still be viewed

\- existing order can still be edited safely

\- historical order referencing legacy product\_service still displays

\- existing receipt still prints

\- workshop receipt still prints

\- delivery still works

\- payments/ledger still work

\- Cloth Store still works

\- Cloth Store inventory unchanged

\==================================================

MINIMAL CHANGE / ZERO REGRESSION RULE

\==================================================

Do not use this task as an excuse to clean/refactor the whole repository.

No unrelated formatting changes.

No package upgrades.

No dependency updates.

No route renaming unless absolutely necessary.

No sidebar redesign.

No database cleanup by deleting old data.

No receipt modifications.

No unrelated controller refactors.

No mass changes.

Change ONLY what is necessary for:

Tailor Category

→ Stitching Service

→ Stitching Price

while preserving the rest of the system.

\==================================================

BEFORE YOU EDIT

\==================================================

Before writing code, first report:

1\. Current Products & Services architecture.

2\. Why SKU/Stock/Low Stock currently exist there.

3\. Every place product\_services is referenced.

4\. How canonical\_id / normalized\_name currently work.

5\. Which legacy physical product rows are referenced by historical Tailor orders.

6\. The exact safe schema/UI approach you will use.

7\. Exact files you intend to modify.

8\. Exact new migration/table(s), if any.

9\. Confirmation that no Cloth Store files/tables will be modified.

10\. Confirmation that existing historical data will not be deleted.

THEN implement.

\==================================================

AFTER IMPLEMENTATION

\==================================================

Report:

1\. Exact files changed.

2\. Exact migration(s) added.

3\. Exact database tables affected.

4\. New Category structure.

5\. New Stitching Rate structure.

6\. How legacy records were preserved.

7\. How new orders select category/service/rate.

8\. How measurement profiles were preserved.

9\. Confirmation SKU/Stock/Cost/Low Stock were removed ONLY from Tailor UI.

10\. Confirmation Cloth Store was untouched.

11\. Confirmation receipts were untouched.

12\. Tests run and results.

13\. Any backward-compatibility protection added.

FINAL SUCCESS CONDITION:

The Tailor Management page must now feel like a simple professional tailor-shop rate manager:

CATEGORY

→ STITCHING SERVICE

→ PRICE

with measurement support.

It must NOT look like an inventory/warehouse system.

The entire rest of the application must continue functioning exactly as before.

DO NOT CHANGE ANYTHING ELSE.