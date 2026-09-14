# Offline licensing: owner and client guide

The application verifies RSA-3072 / SHA-256 signatures entirely in PHP using OpenSSL. There are no network calls, hosting requirements, domains, remote kill switches, licensing secrets in the database, or browser-side security decisions.

## Paths and signing authority

| Purpose | Location |
|---|---|
| Client public verification key (PEM) | `resources/license/public.key` |
| Installed license | `storage/app/license/license.dat` |
| Owner generator | `D:\TailorLicenseOwner\Generate-License.ps1` |
| Owner signing helper | `D:\TailorLicenseOwner\sign.php` |
| Owner encrypted private key | `D:\TailorLicenseOwner\private.key.dpapi` |
| Owner OpenSSL configuration | `D:\TailorLicenseOwner\openssl.cnf` |
| Owner instructions | `D:\TailorLicenseOwner\README.md` |
| Generated licenses | `D:\TailorLicenseOwner\issued\<unique-id>\license.dat` |

The owner directory is outside Laravel and XAMPP's web root. Its Windows ACL permits the owner Windows account and SYSTEM. Windows DPAPI encrypts the private key. The generator decrypts it in memory and passes it to PHP through standard input, never command-line arguments.

Back up the owner laptop and its Windows profile/DPAPI recovery material securely. Copying the encrypted key alone to another account or laptop does not make it decryptable. Losing the key requires a deliberate public-key migration on clients; no master recovery key is hidden in the app.

The installed license is Lifetime for **Owner development laptop**, bound to this laptop. It is not a transferable client activation. Nothing in that owner folder should be shipped with the app.

## Machine ID

On Windows, `MachineIdentity` reads the 64-bit registry value `HKLM\SOFTWARE\Microsoft\Cryptography\MachineGuid`. On Linux, it reads `/etc/machine-id`. It normalizes the identifier to lowercase and computes:

    uppercase(SHA-256("atelier-license-machine-v1\0" + OS-family + "\0" + identifier))

The resulting ID is 64 uppercase hexadecimal characters. It does not depend on a domain, IP address, MAC address, app path, browser, or app user. An unavailable/invalid identifier blocks activation; there is no shared fallback ID.

Reinstalling the OS or changing its identity may require a replacement license. Cloned OS images can share identifiers: prepare installations with unique OS identities. This is OS-device binding, not hardware attestation.

## Client flow and validation

1. Open the existing localhost app. Without a valid license, web requests redirect to activation; an unauthenticated request may first pass through the existing login redirect. JSON business requests are denied.
2. Copy the Machine ID and send it to the software owner.
3. Upload the signed `license.dat` supplied by the owner.
4. PHP validates size, envelope, RSA signature, signed schema, product, machine, type and applicable dates before installation.
5. A valid upload is written to a temporary file in the license directory and renamed into place. Invalid uploads retain the existing license.
6. Continue to the existing login/application. Authentication and business permissions still apply.

Activation retains sessions, CSRF protection and upload throttling. Its routes do not depend on shop-settings database reads. License errors never delete or modify business records.

Each subsequent web request re-reads and verifies the license. There is no durable browser/session activation flag and no production bypass setting. New web routes inherit enforcement. Public exceptions are activation GET/POST, logout, the PWA manifest, static assets, and Laravel's health endpoint. Operational Artisan commands are not blocked; enforcement protects the HTTP application.

Application and activation responses use `Cache-Control: no-store, private`. The existing service worker does not cache authenticated HTML. An already visible page is not erased on expiry, but subsequent requests are checked.

## Trial and Lifetime

**Trial:** The owner enters the last usable date in `YYYY-MM-DD`, interpreted in UTC. The signed expiry is the next UTC midnight, exclusive. For example, `2026-12-31` expires at `2027-01-01 00:00:00 UTC`. The UI displays the last usable date with `(UTC)`. A clock before issuance or at/after expiry is rejected.

**Lifetime:** The signed type is `Lifetime` and expiry is `null`. There is no date-expiry check. Settings displays **Never Expires**. Repeat activation is unnecessary while the file, public key and machine identity remain valid.

The exact signed payload includes version, product, client name, machine ID, type, issuance and expiry. Editing any field breaks the signature. The verifier fixes RSA-3072/SHA-256; it never accepts an algorithm chosen by the file.

## Generate a new client license

On the owner laptop, open PowerShell:

    & "D:\TailorLicenseOwner\Generate-License.ps1"

Enter Client Name, Machine ID, Trial/Lifetime, and the Trial expiry date when prompted. The generator prints the output path. Send only the generated `license.dat`, for example by USB drive.

Non-interactive Trial:

    & "D:\TailorLicenseOwner\Generate-License.ps1" -ClientName "Client Shop" -MachineId "<64-character ID>" -LicenseType Trial -ExpiryDate "2026-12-31"

Lifetime:

    & "D:\TailorLicenseOwner\Generate-License.ps1" -ClientName "Client Shop" -MachineId "<64-character ID>" -LicenseType Lifetime

The generator refuses to overwrite existing outputs. `-OutputPath` selects a different file; its parent directory must exist. `-Php` selects a different PHP executable.

Initialization is already complete. Do not initialize on client laptops or generate a new production key for each client. Initialization refuses to overwrite the existing owner key.

## Install on a client laptop

- Deploy the app with `resources/license/public.key`, runtime dependencies and built `public/build` assets. Exclude the entire owner folder, private key, signing helper, owner backups and development license.
- Use PHP 8.2+ with OpenSSL. Windows PHP must be able to run `reg.exe` and read MachineGuid.
- Serve locally with the document root pointing to `public`, bound to loopback. No domain/hosting is required. The existing root `.htaccess` additionally blocks source, `resources`, and `storage` paths.
- Keep `storage/app/license` writable by PHP and outside public storage/symlinks. Restrict application-code/public-key writes through deployment permissions where possible.
- Keep the clock correct. Use `APP_DEBUG=false` and the normal per-installation Laravel APP_KEY, database and session setup.
- Run `php artisan optimize:clear` after deployment. Build assets on the owner/development machine with `npm run build`; clients can receive the built assets without npm or internet access.
- Open localhost, get the Machine ID, generate its license, and upload it on the activation screen.
- Manual alternative: stop the app, copy the signed file to `storage/app/license/license.dat`, then restart. Never put it in `public` or `storage/app/public`.

Vite uses relative asset URLs so existing fonts and assets also load under a XAMPP subfolder URL.

## Security boundary

Signatures prevent file editing and forgery without the owner's key. An administrator controlling PHP source, the verification key, OS identity or complete VM snapshots can bypass purely local checks. A fully offline Trial cannot obtain trusted external time: rolling the clock back within the signed trial interval can extend use. The checker rejects clocks before issuance but does not claim tamper-proof elapsed time. No online service or hidden secret was introduced to imply otherwise.

## Files added

- `config/license.php`
- `app/Services/Licensing/MachineIdentity.php`
- `app/Services/Licensing/LicenseResult.php`
- `app/Services/Licensing/LicenseChecker.php`
- `app/Services/Licensing/LicenseInstaller.php`
- `app/Http/Middleware/EnsureLicensed.php`
- `app/Http/Controllers/LicenseController.php`
- `resources/license/public.key`
- `resources/views/license/activate.blade.php`
- `resources/views/license/status.blade.php`
- `storage/app/license/.gitignore`
- `storage/app/license/license.dat` (local, intentionally Git-ignored)
- `tests/Feature/LicenseTest.php`
- `tests/Fixtures/licenses/public.key`
- `tests/Fixtures/licenses/trial.dat`
- `tests/Fixtures/licenses/lifetime.dat`
- `tests/Fixtures/licenses/wrong-machine.dat`
- `tests/Fixtures/licenses/bad-type.dat`
- `tests/Fixtures/licenses/bad-expiry.dat`
- `tests/Fixtures/licenses/bad-product.dat`
- `docs/offline-licensing.md`
- Owner files listed above, outside the project.

Test fixtures use a separate ephemeral signing key that was discarded. Only static signed files and their public key remain. They cannot activate the shipping app. No signing implementation or private key was added to the client project.

## Files changed

- `bootstrap/app.php`: registers web enforcement.
- `routes/web.php`: adds activation and throttled upload routes, independent of shop-settings reads.
- `resources/views/settings/index.blade.php`: includes the license status card.
- `resources/views/cloth-store/settings/index.blade.php`: includes the same status card.
- `tests/TestCase.php`: gives business tests a valid signed fixture and deterministic machine; no production bypass.
- `vite.config.js`: relative asset URLs for offline XAMPP subfolder installations.
- Built assets/manifest in `public/build` were regenerated.

Unrelated working-tree changes were preserved. No business migration was added. The temporary Apache Sodium preload change was undone; final verification uses OpenSSL and needs no Apache configuration change.

## Verification results

- **Licensing: 19 passed, 223 assertions.** Covers valid/expired Trial and exact boundary; Lifetime far in the future; wrong machine; field editing; missing/corrupted/oversized files; invalid signed schema; missing key/machine; clock before issuance; normal access/authentication; middleware coverage; authenticated POST/JSON blocking; upload preservation; revalidation; CSRF; and test-key separation.
- **Owner generator:** generated a real Trial and verified its signature/machine against the shipping key; the installed owner Lifetime also verified.
- **Final standard suite:** 73 passed / 3 failed / 102 skipped, 579 assertions. Existing suite excluding licensing: 54 passed / 3 failed / 102 skipped. Disabling licensing only in a temporary test harness produced the **same 54/3/102 and 356 assertions**.
- The three failures are existing `OrderAutoProgressTest` suite-order failures. Alone it passes 3 tests / 17 assertions; combined with the initial 16 license tests, all 19 passed / 231 assertions.
- **Isolated MySQL integration:** 84 passed / 18 failed / 610 assertions after resetting only its test fixtures. Disabling licensing in the temporary test harness produced **identical results**. Failures concern duplicate-phone fixtures and a staff-payment integer/float assertion. The business database was not reset.
- **Assets:** `npm run build` passed with local fonts/icons/assets.
- **Blade:** view compilation passed.
- **Browser:** verified the real Apache activation page, Machine ID, Lifetime / Never Expires, existing typography/icons, and continuation to the existing sign-in page.
- Logs: `storage/logs/license-*.txt`. Broader failures are reported, not represented as a clean pass.
