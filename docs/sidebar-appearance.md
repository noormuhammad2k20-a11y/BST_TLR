# Shared sidebar appearance

The sidebar appearance is global to the shop, shared by Tailor and Cloth Store.

- Tailor: **Settings → Theme & Display → Sidebar Appearance**.
- Cloth Store: **Settings → System & Backup → Sidebar Appearance**.
- Choose Light/Dark and one of eight colors. Changes save automatically, independently of the page's Save Changes button.
- Classic, Slate and Navy retain the existing palette families. Harbor, Evergreen, Aubergine, Bronze and Petrol add new options.
- The overall page color mode and brand accent remain independent.

## Persistence and synchronization

One JSON pair (`theme`, `color`) is stored in the existing `settings` table under `sidebar_appearance`. Both panels use the same authenticated, admin-only GET/PUT endpoint at `settings/sidebar-appearance`. Validation accepts only configured values. General settings updates cannot overwrite the pair; it is included in settings backups/restores.

The database state is rendered before first paint. The initiating page previews immediately and rolls back if saving cannot be confirmed. A BroadcastChannel invalidates other open tabs after a successful save; those tabs fetch the database value. Other visible devices poll every three seconds, and focus/reconnect triggers a refresh. No localStorage appearance state is used. Simultaneous saves use the last completed database write.

The migration `2026_09_14_000002_shared_sidebar_appearance` initializes the pair from the legacy sidebar palette without replacing an existing pair. Deploy with normal migrations and `npm run build`.

## Implementation and verification

Palette tokens live in `config/sidebar.php`; common sidebar styling/first-paint initialization lives in `resources/views/appearance/sidebar.blade.php`. Both Settings pages use the same `sidebar-appearance-settings` custom element from `resources/js/sidebar-appearance.js`, including after SPA navigation.

Focused checks:

```text
php artisan test --filter=SidebarAppearanceTest
node tests/Browser/sidebar-appearance.cjs
npm run build
php artisan view:cache
```

The PHP suite checks persistence, authorization, invalid payloads, legacy migration, and WCAG AA text contrast (4.5:1) plus focus contrast (3:1) across all 16 variants. The JS DOM suite checks save/rollback, synchronization, fresh-page persistence, SPA mounting and isolation from the page theme. Visual previews of both Settings layouts were inspected separately with a mocked transport; live authenticated HTTP behavior is covered by the feature tests.
