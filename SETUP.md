# Atelier — Setup & What Changed

## Run these once

```bash
php artisan migrate
php artisan db:seed          # optional: demo data + user accounts

npm install                  # Tailwind is now compiled, not CDN
npm run build

php artisan optimize:clear
```

Re-run `npm run build` after editing any Blade file that introduces **new** Tailwind classes. During active design work `npm run dev` rebuilds on save.

### Everything is self-hosted — no CDN

Tailwind, **Font Awesome 6.5.1**, **Inter** and **Chart.js** are all bundled by Vite. Nothing is fetched from `cdnjs`, `cdn.jsdelivr.net` or Google Fonts at runtime, so the app renders identically with no internet connection.

This matters: while Tailwind itself came from a CDN, an unreachable CDN broke the whole page and was obvious. Once Tailwind was compiled locally, the page still looked correct and only the remaining CDN assets went missing — **icons disappearing while the layout looked fine is the classic symptom.** Self-hosting removes that failure mode entirely.

`resources/js/app.js` assigns `window.Chart`, which the dashboard, reports and expenses charts use. If the build hasn't been run, both layouts fall back to the CDN versions.

### Why Tailwind v3 and not v4

The design was authored against `cdn.tailwindcss.com`, which serves **Tailwind v3**. Tailwind v4 renames `shadow-sm` → `shadow-xs`, changes the default border colour from `gray-200` to `currentColor` and the default ring width from 3px to 1px. Compiling with v4 would have quietly shifted shadows, borders and focus rings across every card in the app, so `package.json` pins **v3.4** — the same engine the CDN was running. Output is identical to what you had.

If the build hasn't been run, both layouts fall back to the CDN automatically, so the app never renders unstyled.

### Dynamic class names

Tailwind only ships classes it can find as literal text. One place builds them at runtime — the Quick Action tiles in `dashboard.blade.php` use `bg-${o.c}-50 text-${o.c}-600`. Those are covered by the `safelist` patterns in `tailwind.config.js`. **If you add another runtime-assembled class, add it to that safelist or it will not be generated.**

Optional, keeps order statuses current even when nobody has the app open:

```bash
php artisan schedule:work
```

Without the scheduler nothing breaks — the same sweep runs on page load, rate-limited to once every 5 minutes.

## Login

| Email | Role |
|---|---|
| `admin@ateliercraft.com` | admin |
| `staff@ateliercraft.com` | staff |
| `ahmed@ateliercraft.com` | tailor |

Password for all seeded accounts: `password`. **Change the admin password after first login** (Profile → Change Password).

## Architecture

```
app/
  Services/
    OrderService.php        every order write path: status, balance, history, delivery sync
    StatsService.php        all analytics; short-TTL cache, flushed on write
    NotificationService.php in-app notifications
    ActivityLogger.php      audit trail
    Money.php               currency formatting from Settings
  View/Composers/
    LayoutComposer.php      sidebar badges + bell, one cached pass per request
```

Front-end shares one runtime, `window.Atelier` (defined in `layouts/app.blade.php`): `api.get/post/put/patch/delete`, `ajaxForm`, `reportError`, `poll`, `emptyState`, `setBusy`, `money`, `confirmAction`.

## No page reloads

Every create, update and delete is AJAX. Nothing calls `window.location.reload()`.

- `Atelier.ajaxForm('#form-id', { onSuccess })` converts any normal `<form>` into an AJAX submit and paints Laravel validation errors onto the offending inputs.
- After a save the affected row is inserted/replaced in place and the summary cards recompute locally — Customers, Expenses, Payments, Measurements, Delivery, Orders and Profile all behave this way.
- The only remaining `location.href` calls are genuine navigations to another page or file downloads.

## Desktop app (PWA)

Atelier installs to the desktop and launches in its own window — no address bar, no tabs, its own taskbar/dock icon.

### Setup

```bash
php artisan atelier:icons     # renders the app icons into public/icons
php artisan optimize:clear
```

`atelier:icons` draws the icons with PHP's GD extension, so no design tool is needed. Re-run it any time you want to regenerate them.

### Installing

Open the app in Chrome or Edge and either:

- click **Install** in the address bar, or
- use **Install App** in the sidebar user menu (appears only when installable), or
- accept the prompt the app offers on first visit

Once installed it opens standalone from the Start menu, desktop or dock.

### Requirements

| Requirement | Why |
|---|---|
| **Chrome or Edge** | Firefox and Safari on desktop don't support installing PWAs |
| **`localhost` or `127.0.0.1`, or HTTPS** | Browsers only allow installation from a secure context. `127.0.0.1:8000` qualifies; a LAN IP like `192.168.1.5` does **not** — that needs HTTPS |
| **The server must be running** | See below |

### The server is still required

This is important. Atelier is a server-rendered Laravel app reading from MySQL, so **the installed app is a window onto your server, not a self-contained program.** XAMPP (or `php artisan serve`) has to be running, otherwise the app opens to a branded offline screen with a "Try again" button.

If you need something that runs with no server at all, that's a fundamentally different build — Electron with PHP and MySQL bundled inside it — not a PWA.

### What the service worker does

`public/sw.js` is deliberately conservative:

- **Static assets** (`/build/assets`, `/icons`, fonts) — cache-first. Vite content-hashes them, so a cached copy is always safe.
- **Page navigations** — network only, falling back to `public/offline.html`. **Authenticated HTML is never cached**, so no customer or order data can leak to the next person who opens the app on a shared machine.
- **`/live/*` polling and every non-GET request** — passed straight through, never touched.

Bump `VERSION` in `sw.js` if you ever need to force every installed client to drop its cache.

### Installed-mode polish

An `.app-standalone` class is added to `<html>` only when launched from the desktop icon, never in a browser tab. It disables overscroll bounce, turns off text selection on chrome (while keeping it on inputs, tables and receipts) and hides mouse-click focus rings — the small things that separate an app from a webpage. **In a browser tab the design is completely unchanged.**

The manifest also registers right-click shortcuts on the taskbar icon: New Order, Customers, Payments & Billing.

## Alerts & confirmations

Your `alert-and-notification.html` design is now the single system for the whole app, defined once in `layouts/app.blade.php`.

### Toasts

```js
toast('Order created successfully', 'success');   // #047857
toast('Reset link sent to email!', 'info');       // #4338ca
toast('Please review your input', 'warning');     // #b45309
toast('Invalid credentials', 'error');            // #be123c
```

Solid coloured pill, white text, 4px translucent left edge, slides in from the right at `top-8 right-8`, auto-dismisses after 3s. One toast element is reused, so a new message replaces the current one rather than stacking.

`'danger'` is accepted as an alias for `'error'`, since older calls used it.

### Confirmations

```js
Atelier.confirm({
  variant: 'delete',                       // delete | approve | logout | info
  title: 'Delete Order ORD-1042?',
  message: 'This will permanently erase all data related to this order.',
  confirmLabel: 'Confirm Delete',
  onConfirm: async () => { /* runs after the dialog closes */ },
});
```

Blurred backdrop, centred icon circle, Sora-set title, Cancel + coloured Confirm. Variants carry the design's exact colours — rose for delete, emerald for approve, amber for logout, indigo for info. Closes on backdrop click and Escape.

`onConfirm` may be async; errors are routed through `Atelier.reportError`, so a failed request shows an error toast instead of failing silently.

### What this replaced

- The old white toast with the coloured left border, and its stacking `#toast-container`
- The `atelier-confirm` modal built on `.modal-backdrop`
- The Orders page's private copy — `#custom-toast`, `#custom-confirmation-modal`, `showCustomToast`, `showCustomConfirmation` and ~90 lines of duplicated CSS. `showCustomToast` remains as an alias so existing calls still work.
- Per-page delete dialogs in Products & Services, Expenses, Measurements and Delivery
- Sign Out now uses the **logout** variant instead of submitting immediately

Content modals (order wizard, customer 360°, receipts) still use the original `.modal-backdrop` / `.modal` styling — those weren't part of this design and are untouched.

**Note:** the old `'confirm-delete'` / `'confirm-complete'` entries in each page's `window.modals` are now unreachable dead code. Harmless, but safe to delete.

## Cross-page hand-offs

The Orders page accepts intent via query string, so any screen can start a flow there:

| URL | Result |
|---|---|
| `/orders?action=create` | Opens the order wizard at step 1 |
| `/orders?action=create&customer=12` | Opens the wizard with customer 12 already selected, skipping to step 2 |
| `/orders?action=create&customer=12&new_customer=1` | As above, plus a "customer added" confirmation |
| `/orders?highlight=45` | Opens order 45's details modal |

The URL is cleaned with `history.replaceState` afterwards, so refreshing or going back never repeats the action.

**Add-customer flow:** saving a customer (from either the Customers page or the dashboard drawer) stores the record, shows the confirmation, then forwards to Orders with that customer pre-selected — the wizard opens on "Garment & Fabric Details" with the customer step already ticked.

Same-page equivalents: `/measurements?action=create` and `/expenses?action=create`.

## Activity Logs page — removed

The page, its route and its sidebar entry are gone. **Activity logging itself is still running**, because the dashboard's Live Activity Feed reads from it and it is the audit trail behind every order, payment and login.

Three files are now orphaned and can be deleted whenever convenient (nothing references them):

```
app/Http/Controllers/ActivityLogController.php
resources/views/activity-logs/index.blade.php
resources/views/activity-logs/partials/timeline.blade.php
```

The `activity_logs` table and `App\Services\ActivityLogger` must stay.

## Navigation speed

- **Tailwind compiled** instead of compiled-in-the-browser. This was the single biggest cost: the CDN parses your Blade output and generates CSS on every page load, typically 300–800 ms before first paint. Now it's one cached stylesheet.
- **Hover prefetch.** Moving the pointer onto any in-app link starts downloading that page immediately, so by the time the click lands the HTML is usually already in cache. Downloads, exports, receipts and logout are excluded. Bound during idle time so it never delays first paint.
- Sidebar badge counts are cached for 30 s, so the chrome around each page costs one cached lookup rather than three `COUNT(*)` queries.

## SPA navigation

`window.SpaRouter` (in `layouts/app.blade.php`) intercepts in-app link clicks, fetches the target page, and swaps `<main>`, the drawers, page `<style>` blocks, the `<title>` and the breadcrumb — no full reload. Back/forward work via `popstate`.

### How a page opts in

```blade
@extends('layouts.app')
@section('title', 'Orders')
@section('spaPage', 'orders')     {{-- this line opts the page in --}}
```

**A page without `@section('spaPage')` falls back to a normal browser navigation.** The router checks for the marker after fetching and hands off to the browser if it's missing, so a half-migrated app still works everywhere.

### The two rules for page scripts

1. **Top-level bindings must use `var`, never `const`/`let`.**
   Page scripts are re-evaluated at global scope on every navigation, because their inline `onclick=` handlers need the functions to stay global. `var` and `function` may legally be redeclared; `const`/`let` throw `Identifier 'x' has already been declared`. Orders declares `customers`, Customers declares `customers` — that collision is exactly what this rule prevents. Inside functions, `const`/`let` are fine and unchanged.

2. **Init goes in `Atelier.onPageReady()`, not `DOMContentLoaded`.**
   `DOMContentLoaded` only fires on a hard load, so it would never run on an SPA navigation. `onPageReady` fires on both.

```js
Atelier.onPageReady(() => {
  renderPage();
  Atelier.poll(refresh, 45000);
});
```

### Why navigation is instant

Three things were costing seconds. All three are fixed:

1. **`<link rel="prefetch">` did nothing.** Laravel sends `Cache-Control: private, no-cache` on session-backed responses, so the browser discarded every prefetch and each click still paid a full round-trip. The router now fetches the HTML itself and keeps it in an in-memory `Map`.

2. **The work now happens before the click.** Sidebar links are warmed during idle time on page load, and anything else warms on hover or `pointerdown`. By the time a click lands the HTML is already in memory, so the swap happens in the same frame — the server time still exists, it just no longer sits on the critical path.

3. **The Delivery page ran a backfill on every request** — a `whereDoesntHave` scan plus up to 200 inserts, every single load. It's now rate-limited to once every 6 hours, which is all it was ever meant to do.

Cache entries live 30 seconds and **every non-GET request wipes the whole cache**, so a page can never show figures that a save has already invalidated.

The loading dim is also delayed by 250ms — a warmed page swaps instantly, and flashing a loading state for one frame looks worse than showing nothing.

### Why modals are instant

They no longer wait on the network:

- **Receipts and invoice previews** are composed entirely client-side, from the order row already in memory plus `Atelier.shop` (store name, address, tax rate, footer) which the layout embeds. **Zero requests.**
- **Order details** opens immediately from the in-memory order; the timeline fills in afterwards behind a small skeleton. The old version showed a full-screen spinner until the request returned.
- **Customer 360°** opens seeded with the orders already on the page, then fills in measurements and payments. Results are cached per customer, so reopening a profile is instant.

The rule to follow when adding modals: **render from data you already have, then enrich.** Never block the open on a request.

### Cleanup between pages

Before each swap the router calls `Atelier.resetPageScope()`, which:

- stops every interval started with `Atelier.poll()`
- aborts every listener registered with `{ signal: Atelier.pageSignal() }`
- closes any open modal or drawer

If a page adds a `document`/`window` listener, pass the signal or it will stack up on every visit:

```js
document.addEventListener('keydown', handler, { signal: Atelier.pageSignal() });
```

### Converted pages

Dashboard, Orders, Customers, Measurements, Products & Services, Payments & Billing, Expenses, Delivery, Notifications, Reports, Settings, Profile, Printing Center.

### Known limitation

Laravel flash messages (`session('success')`) render as a `<script>` inside `<main>`. Scripts injected via `innerHTML` don't execute, so a flash toast won't appear after an SPA navigation. In practice every mutation is already AJAX and shows its own toast, so this only affects the few non-JSON redirect paths.

## Speed notes

- Chart.js was being loaded twice on Reports and Expenses (once in the layout, once per page). Removed; the layout copy is now `defer`red.
- Added `preconnect` hints for the three CDNs.
- Background status sweeps are rate-limited to once per 5 minutes instead of running on every page load.
- **Remaining bottleneck:** `https://cdn.tailwindcss.com` compiles Tailwind in the browser on every page load, which typically costs 300–800 ms. Moving to a built stylesheet (`npm install && npm run build`) is the single biggest remaining win, but it needs a visual pass afterwards, so it was left alone.

## Live data

Polling endpoints under `/live`:

- `live.counters` — sidebar badges (all pages, 45s)
- `live.dashboard` — stats, activity feed, revenue chart (30s)
- `live.orders` — order list + status counts (45s)
- `live.notifications` — notification centre (30s)

Polling pauses when the tab is hidden and refreshes immediately on focus.

## Settings that drive behaviour

| Key | Effect |
|---|---|
| `currency` | Every money value app-wide |
| `order_prefix` / `invoice_prefix` | Generated document numbers |
| `tax_rate` | Invoice tax breakdown |
| `auto_status_enabled` + `auto_status_pending_hours` | Pending → In Progress countdown (the one shown in the orders table) |
| `auto_delivery_update` | Auto-close orders when balance hits zero |
| `allow_partial` | Whether partial payments are accepted |
| `low_stock_alert` | Default low-stock threshold |
| `delivery_slots` / `extension_reasons` | Pipe-separated dropdown options |

## Two bugs fixed along the way

1. **Stray backslashes broke the JS on 8 pages.** `\`` and `\${` were literal characters in the `<script>` blocks of Delivery, Expenses, Payments, Notifications, Printing, Settings and Reports — a syntax error that stopped those scripts dead. That is why several pages sat on "Loading…" permanently. Removed.
2. **`notifications` table had no `title` column** but `NotificationController` read `$n->title`. Column added.

## One deliberate addition to the UI

The Profile page had no submit control, so the form could not save. A "Save Changes" button was added to that card header, styled to match the existing button language. Everything else is the original markup.
