# Staff payroll

Configure the rate and cycle in the existing staff form. The salary-type selector includes Monthly, Weekly and Daily cycles without adding form rows. Existing records default to Monthly.

Completed Ready/Delivered/Completed orders retain the existing one-credit-per-order workflow. Each work record stores its quantity, completion date, rate and earned amount. Changing the configured rate only affects subsequent work. Manual stitching remains supported, including fractional quantities.

Monthly periods use calendar months; weekly periods use ISO Monday-Sunday weeks; daily periods use the shop date. Select an earlier period in Record Payment to settle its outstanding balance. Balances are per period, without silently carrying earlier debts into a different period.

Per Suit earnings sum the stored completed-work amounts. Monthly retainers remain supported; daily/weekly retainer amounts are apportioned by calendar day, with rounding that reconciles to the monthly amount. Both adds the retainer and completed-work earnings.

Payment writes lock the staff record, reject amounts above the earned balance and preserve an operation key against duplicate submission. Each new payment stores the period, completed quantity, actual rate breakdown, earned amount, paid amount and remaining balance. Reversal transactions remain visible alongside their originals and do not count as paid. Existing transactions are retained; historical snapshots are not invented for payments made before this migration.

To prevent paying the same dates twice, payments cannot use a different cycle that overlaps an existing active payment. Keep using the original cycle for those dates, or reverse the original payment before correcting its cycle.

Validation:
- `php vendor/bin/phpunit tests/Feature/StaffPayrollTest.php tests/Feature/TailorReadyCreditTest.php`
- `php vendor/bin/phpunit tests/Feature/OrderAutoProgressTest.php` (run separately because older minimal-schema tests cache model column lists)
- `php storage/app/staff-modal-qa.php`
- `node tests/Browser/staff-payroll.cjs`
- `node storage/app/staff-modal-qa.cjs`

Migration: `2026_09_11_000002_staff_payment_periods.php`. No existing work or payment rows are deleted or rewritten.
