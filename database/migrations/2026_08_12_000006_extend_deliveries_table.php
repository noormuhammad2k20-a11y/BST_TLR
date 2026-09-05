<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            if (!Schema::hasColumn('deliveries', 'courier_name')) {
                $table->string('courier_name')->nullable()->after('status');
            }
            if (!Schema::hasColumn('deliveries', 'courier_phone')) {
                $table->string('courier_phone')->nullable()->after('courier_name');
            }
            if (!Schema::hasColumn('deliveries', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('courier_phone');
            }
            if (!Schema::hasColumn('deliveries', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('delivery_date');
            }
            if (!Schema::hasColumn('deliveries', 'notes')) {
                $table->text('notes')->nullable()->after('tracking_info');
            }
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->index('status', 'deliveries_status_idx');
            $table->index('delivery_date', 'deliveries_delivery_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('deliveries_status_idx');
            $table->dropIndex('deliveries_delivery_date_idx');
            $table->dropColumn([
                'courier_name', 'courier_phone', 'recipient_name', 'delivered_at', 'notes',
            ]);
        });
    }
};
