# Customer archive and permanent deletion

The customer directory uses the existing `deleted_at` soft-delete column. Normal deletion archives a customer. The Archived Customers tab lists recoverable archives and provides Restore and Delete Permanently. Anonymized records remain archived and cannot be restored.

Permanent deletion requires `confirmation: DELETE`, enforced in the UI and API. The lifecycle service locks the customer and performs the operation in a transaction. Orders (including archived orders), payments of any status, measurements, notifications, messaging logs and customer integrity records count as history. Invoice identifiers are stored on orders/payments rather than in a separate invoice table. Customers without this history are hard-deleted; routine customer-directory audit events do not prevent that deletion.

Customers with history keep their primary key and relationships. Name and contact fields are anonymized, personal notes/contact copies in deliveries and messaging records are removed, and related audit metadata is redacted while retaining financial values. Orders, invoices, balances, payment amounts/statuses and report totals are preserved. Order and measurement customer relationships include archived customers so existing records remain readable.

`phone_key` normalizes Pakistani phone formatting and is unique for new records. Migration preserves pre-existing duplicate phone variants: the active record, or oldest archived record, reserves the key, while legacy duplicates retain a NULL key and are also checked by application validation. Restore is blocked when another record owns the same phone. Existing records are never merged by this migration. Imports skip archived matches with a Restore Customer message. Anonymization releases the phone for future use.

Routes remain under the existing authentication, active-user and owner-access middleware:

- `DELETE /customers/{customer}`: archive
- `POST /customers/{customer}/restore`: restore a recoverable archive
- `DELETE /customers/{customer}/permanent`: permanent deletion/anonymization
- Existing create/update routes return HTTP 409 with `archived_customer` and a field message for an archived phone match.

Validation uses isolated in-memory databases, with no live customer deletions:

```text
php vendor/phpunit/phpunit/phpunit tests/Feature/CustomerLifecycleTest.php
node tests/Browser/customer-lifecycle.cjs
php vendor/phpunit/phpunit/phpunit tests/Feature tests/Unit --process-isolation
```
