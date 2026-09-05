<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'reference')) {
                $table->string('reference')->nullable()->after('invoice_id');
            }
            if (!Schema::hasColumn('payments', 'notes')) {
                $table->text('notes')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('payments', 'recorded_by')) {
                $table->foreignId('recorded_by')->nullable()->after('notes')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('date', 'payments_date_idx');
            $table->index('status', 'payments_status_idx');
            $table->index('payment_method', 'payments_method_idx');
            $table->index(['order_id', 'date'], 'payments_order_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_date_idx');
            $table->dropIndex('payments_status_idx');
            $table->dropIndex('payments_method_idx');
            $table->dropIndex('payments_order_date_idx');
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['reference', 'notes']);
        });
    }
};
