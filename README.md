# Tailor and Cloth Store Management

Laravel 12 application for tailoring orders, measurements, delivery, payments,
tailors, reporting, POS sales, single-shop inventory, direct market stock intake,
returns, and customer ledgers.

## Requirements

- PHP 8.2 or newer with BCMath, cURL, DOM, Fileinfo, Intl, Mbstring, OpenSSL,
  PDO MySQL, Sodium, XML, and Zip
- MySQL 8 or a compatible MariaDB release
- Composer 2, Node.js 20 or newer, and npm
- A web server whose document root is this project's `public/` directory

SQLite is suitable for lightweight unit tests only. Production and business
integration tests require MySQL/MariaDB because transaction locking is part of
the accounting and inventory design.

## Installation

```text
composer install --no-dev --optimize-autoloader
npm ci
npm run build
copy .env.example .env
php artisan key:generate
```

Configure the database, `APP_URL`, mail, queue, session, and cache values in
`.env`. Then run:

```text
php artisan migrate --force
php artisan production:setup
php artisan storage:link
php artisan optimize
```

`production:setup` installs the permission catalog and required settings. It
retains existing users, roles, grants, and passwords. If no active administrator
exists, it interactively creates one without printing or storing a plaintext
password. Do not use `DatabaseSeeder` in production; it contains demonstration
records.

## Required background processes

Run `php artisan queue:work --tries=3` when the queue connection is not `sync`.
Run `php artisan schedule:run` every minute from Task Scheduler or cron. The
scheduler advances configured order stages and uses overlap protection.

## Upgrade and data reconciliation

Back up the database and uploaded files before upgrading. Never run
`migrate:fresh` against client data.

```text
php artisan migrate:status
php artisan migrate --force
php artisan settings:encrypt-secrets
php artisan settings:encrypt-secrets --apply
php artisan integrity:reconcile
```

The secret and integrity commands default to dry-run. `integrity:reconcile`
reports negative balances, stock/location mismatches, unallocated legacy
payments, historical processed returns, and users without cloth-store roles. It
does not guess whether a historical refund was paid or overwrite stock totals.
An evidence-backed invoice can be recalculated explicitly with
`--apply --order=<id>` after its history has been reviewed.

Before opening POS after this upgrade, resolve every reported stock mismatch.
New stock operations reject mismatched products so they cannot deepen existing
corruption. Review negative customer balances and historical returns against
receipts, bank/cash evidence, payments, and ledger entries.

Existing Manager, Cashier, Inventory Staff, and Accountant roles are not
automatically elevated. Assign and review their grants in user management after
running `ProductionPermissionsSeeder`. Newly created default roles receive
conservative grants.

## Customer notifications

Automated WhatsApp uses Meta WhatsApp Cloud API directly. SMS supports Veevo Tech / SPEXT (recommended) and SendPK. See [Notification setup](NOTIFICATION-SETUP.md) for credentials, approved templates, upgrade steps, and provider limitations.

## Testing

```text
php artisan test
npm run build
```

Business tests use the fixed, isolated `atelier_integrity_test` database:

```text
php dev/tools/test-database.php
php dev/tools/test-database.php --reset-data
set INTEGRITY_MYSQL=1
php artisan test --testsuite=Integration
php dev/tools/concurrency.php
```

On PowerShell use `$env:INTEGRITY_MYSQL='1'` and remove the environment variable
afterward. The concurrency helper runs payment, reversal, checkout, and return
races through independent MySQL processes. Test helpers refuse
to target the configured working database.

## Backups and deployment

- Back up the database and `storage/app/public`; encrypt backups at rest.
- Exclude `.env`, SQL dumps, credentials and logs from
  source control. Never place backups under the public web root.
- Put the app into maintenance mode, deploy, migrate, build assets, rebuild
  caches, restart queue workers, run reconciliation in dry-run mode, then restore
  service.
- Set `APP_ENV=production`, `APP_DEBUG=false`, secure cookies under HTTPS, and a
  production log level such as `warning`.
- Test login, POS stock movement, payments/refunds, printing, queue processing,
  scheduling, backups, and notification provider connectivity on staging before cutover.

The repository-root `.htaccess` blocks sensitive source and development paths
for accidental XAMPP root deployments, but it is defense in depth. The correct
web document root remains `public/`.
