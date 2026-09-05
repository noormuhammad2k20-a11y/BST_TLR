<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One order can now carry several garments, and each garment gets its own
 * measurement sheet.
 *
 * Until now a measurement row belonged to a customer and an order pointed at
 * exactly one of them (`orders.measurement_id`). That is fine for a single
 * suit, but a customer who drops off three suits needs three sheets that all
 * belong to the same order — the cutter has to know which numbers go with
 * which piece.
 *
 * `orders.measurement_id` is deliberately left in place and keeps pointing at
 * the first sheet, so every existing query, receipt and report keeps working
 * untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            if (!Schema::hasColumn('measurements', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('customer_id')
                    ->constrained('orders')->nullOnDelete();
            }

            if (!Schema::hasColumn('measurements', 'piece_no')) {
                $table->unsignedSmallInteger('piece_no')->default(1)->after('order_id');
            }
        });

        Schema::table('measurements', function (Blueprint $table) {
            $table->index(['order_id', 'piece_no'], 'measurements_order_piece_idx');
        });
    }

    public function down(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->dropIndex('measurements_order_piece_idx');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn('piece_no');
        });
    }
};
