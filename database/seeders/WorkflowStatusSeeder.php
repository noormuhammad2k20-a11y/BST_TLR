<?php

namespace Database\Seeders;

use App\Models\Order;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Brings existing order rows onto the new workflow.
 *
 *   Pending → Stitching → Ready for Verification → Ready → Delivered
 *
 * Two things changed and both left rows behind:
 *
 * 1. "Trial" was removed. It occupied the same position the verification step
 *    now occupies, so those orders map straight across.
 *
 * 2. "Overdue" stopped being a status. The old sweep overwrote the real status
 *    with it, which destroyed the only fact the workshop needed — whether the
 *    garment was still being stitched or sitting on the shelf. That fact is not
 *    gone, though: the sweep recorded the transition, so `from_status` on the
 *    order's history still holds what the order was before it was flagged.
 *    This restores from there, and only falls back to a guess when there is no
 *    history to read.
 *
 * Being late is now derived from `delivery_date` on the fly, so nothing is lost
 * by putting the real status back.
 *
 * Run it with:
 *   php artisan db:seed --class=WorkflowStatusSeeder
 *
 * Safe to run more than once — a second run finds nothing left to migrate.
 */
class WorkflowStatusSeeder extends Seeder
{
    public function run(): void
    {
        $this->migrateTrials();
        $this->restoreOverdue();
        $this->resyncProgress();

        $this->command?->info('Workflow migration complete.');
    }

    /** Trial occupied the verification slot; move those orders across. */
    private function migrateTrials(): void
    {
        $count = DB::table('orders')
            ->where('status', 'Trial')
            ->update(['status' => 'Ready for Verification']);

        DB::table('order_status_histories')->where('to_status', 'Trial')
            ->update(['to_status' => 'Ready for Verification']);
        DB::table('order_status_histories')->where('from_status', 'Trial')
            ->update(['from_status' => 'Ready for Verification']);

        $this->command?->info("Trial → Ready for Verification: {$count} order(s)");
    }

    /**
     * Put the real status back on orders the old sweep overwrote with Overdue.
     */
    private function restoreOverdue(): void
    {
        $orders = DB::table('orders')->where('status', 'Overdue')->pluck('id');

        if ($orders->isEmpty()) {
            $this->command?->info('Overdue → real status: nothing to restore');

            return;
        }

        $restored = 0;
        $guessed  = 0;

        foreach ($orders as $orderId) {
            // The row the sweep itself wrote: "moved from X to Overdue".
            $previous = DB::table('order_status_histories')
                ->where('order_id', $orderId)
                ->where('to_status', 'Overdue')
                ->whereNotNull('from_status')
                ->orderByDesc('id')
                ->value('from_status');

            if ($previous && in_array($previous, Order::ALL_STATUSES, true)) {
                $restored++;
            } else {
                // No usable history. "Stitching" is the honest guess: the
                // order was open and past its date, so work had started but was
                // not finished. Staff can correct it on the kanban board.
                $previous = 'Stitching';
                $guessed++;
            }

            DB::table('orders')->where('id', $orderId)->update([
                'status'   => $previous,
                'progress' => Order::progressFor($previous),
            ]);
        }

        $this->command?->info("Overdue → real status: {$restored} restored from history, {$guessed} defaulted to Stitching");
    }

    /** Percentages shifted with the new stage list; recompute them all. */
    private function resyncProgress(): void
    {
        $touched = 0;

        foreach (Order::PROGRESS_MAP as $status => $progress) {
            if ($status === 'Overdue') {
                continue;   // no longer a status; handled above
            }

            $touched += DB::table('orders')
                ->where('status', $status)
                ->where('progress', '!=', $progress)
                ->update(['progress' => $progress]);
        }

        $this->command?->info("Progress recalculated on {$touched} order(s)");
    }
}
