<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Renames the shop's admin account and repairs the history it left behind.
 *
 * Why a seeder and not just an edit in Profile:
 *
 * `order_status_histories.actor_name` and `activity_logs.actor_name` are
 * denormalised snapshots — the actor's name is copied onto the row at the
 * moment the event happens, so a timeline still reads correctly years later
 * even if that staff member was renamed or deleted. That is the right design,
 * but it means renaming a user does nothing to rows already written: every
 * order created before the rename keeps saying "by Aarav Rao".
 *
 * This seeder does both halves — the account and the trail — so the two agree.
 *
 * Run it with:
 *   php artisan db:seed --class=RenameAdminSeeder
 *
 * Safe to run more than once: it matches on the old name, so a second run
 * simply finds nothing left to change.
 */
class RenameAdminSeeder extends Seeder
{
    /** The account being renamed. */
    private const ADMIN_EMAIL = 'admin@ateliercraft.com';

    /** Seeded placeholder that shipped with the demo data. */
    private const OLD_NAME = 'Aarav Rao';

    private const NEW_NAME    = 'Noor M Hingorjo';
    private const NEW_DISPLAY = 'Noor';

    public function run(): void
    {
        $admin = User::where('email', self::ADMIN_EMAIL)->first();

        if (!$admin) {
            $this->command?->warn('No account found for ' . self::ADMIN_EMAIL . ' — nothing renamed.');

            return;
        }

        $previous = $admin->name;

        $admin->forceFill([
            'name'         => self::NEW_NAME,
            'display_name' => self::NEW_DISPLAY,
        ])->save();

        $this->command?->info("Account renamed: {$previous} -> " . self::NEW_NAME);

        // Match on the account as well as the old string, so rows written under
        // the placeholder AND any written under a different earlier name are
        // both brought in line.
        $touched = 0;

        foreach (['order_status_histories', 'activity_logs'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'actor_name')) {
                continue;
            }

            $count = DB::table($table)
                ->where(function ($q) use ($admin, $previous) {
                    $q->where('user_id', $admin->id)
                      ->orWhere('actor_name', self::OLD_NAME)
                      ->orWhere('actor_name', $previous);
                })
                ->update(['actor_name' => self::NEW_NAME]);

            $this->command?->info("  {$table}: {$count} row(s) updated");
            $touched += $count;
        }

        $this->command?->info("History repaired — {$touched} row(s) now read \"" . self::NEW_NAME . '".');
    }
}
