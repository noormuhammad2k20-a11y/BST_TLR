<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->unique()->after('order_number');
            }
            if (!Schema::hasColumn('orders', 'garment')) {
                $table->string('garment')->nullable()->after('invoice_number');
            }
            if (!Schema::hasColumn('orders', 'fabric')) {
                $table->string('fabric')->nullable()->after('garment');
            }
            if (!Schema::hasColumn('orders', 'style_notes')) {
                $table->text('style_notes')->nullable()->after('fabric');
            }
            if (!Schema::hasColumn('orders', 'priority')) {
                $table->string('priority')->default('Normal')->after('status');
            }
            if (!Schema::hasColumn('orders', 'progress')) {
                $table->unsignedTinyInteger('progress')->default(0)->after('priority');
            }
            if (!Schema::hasColumn('orders', 'time_slot')) {
                $table->string('time_slot')->nullable()->after('delivery_date');
            }
            if (!Schema::hasColumn('orders', 'trial_status')) {
                $table->string('trial_status')->nullable()->after('time_slot');
            }
            if (!Schema::hasColumn('orders', 'notes')) {
                $table->text('notes')->nullable()->after('trial_status');
            }
            if (!Schema::hasColumn('orders', 'notified_at')) {
                $table->timestamp('notified_at')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('orders', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('notified_at');
            }
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('completed_at');
            }
            if (!Schema::hasColumn('orders', 'product_service_id')) {
                $table->foreignId('product_service_id')->nullable()->after('customer_id')
                    ->constrained('product_services')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'measurement_id')) {
                $table->foreignId('measurement_id')->nullable()->after('product_service_id')
                    ->constrained('measurements')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'tailor_id')) {
                $table->foreignId('tailor_id')->nullable()->after('measurement_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('orders', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('tailor_id')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('status', 'orders_status_idx');
            $table->index('priority', 'orders_priority_idx');
            $table->index('delivery_date', 'orders_delivery_date_idx');
            $table->index('created_at', 'orders_created_at_idx');
            $table->index(['status', 'delivery_date'], 'orders_status_delivery_idx');
            $table->index(['customer_id', 'status'], 'orders_customer_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_idx');
            $table->dropIndex('orders_priority_idx');
            $table->dropIndex('orders_delivery_date_idx');
            $table->dropIndex('orders_created_at_idx');
            $table->dropIndex('orders_status_delivery_idx');
            $table->dropIndex('orders_customer_status_idx');

            $table->dropConstrainedForeignId('product_service_id');
            $table->dropConstrainedForeignId('measurement_id');
            $table->dropConstrainedForeignId('tailor_id');
            $table->dropConstrainedForeignId('created_by');

            $table->dropUnique(['invoice_number']);
            $table->dropColumn([
                'invoice_number', 'garment', 'fabric', 'style_notes', 'priority', 'progress',
                'time_slot', 'trial_status', 'notes', 'notified_at', 'completed_at', 'delivered_at',
            ]);
        });
    }
};
