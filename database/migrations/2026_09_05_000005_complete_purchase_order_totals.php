<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['total_amount', 'paid_amount', 'due_amount'] as $column) {
            if (!Schema::hasColumn('cs_purchase_orders', $column)) {
                Schema::table('cs_purchase_orders', function (Blueprint $table) use ($column) {
                    $table->decimal($column, 14, 2)->default(0);
                });
            }
        }

        // Only fill rows that have no totals yet; existing client totals remain authoritative.
        DB::table('cs_purchase_orders as po')
            ->where('po.total_amount', '0')
            ->orderBy('po.id')
            ->eachById(function ($order) {
                $total = DB::table('cs_purchase_order_items')
                    ->where('cs_purchase_order_id', $order->id)
                    ->selectRaw('COALESCE(SUM(quantity * unit_cost), 0) as total')
                    ->value('total');
                $due = bcsub((string) $total, (string) ($order->paid_amount ?? '0'), 2);
                DB::table('cs_purchase_orders')->where('id', $order->id)->update([
                    'total_amount' => $total,
                    'due_amount' => bccomp($due, '0', 2) > 0 ? $due : '0.00',
                ]);
            }, 100, 'po.id', 'id');
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only: purchase-order financial history must be retained.');
    }
};
