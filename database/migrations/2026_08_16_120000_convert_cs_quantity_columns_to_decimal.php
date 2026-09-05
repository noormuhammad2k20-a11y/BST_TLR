<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meter-based inventory integrity.
 *
 * A cloth store sells fabric in fractional metres (5.50 m, 0.75 m, 120.25 m).
 * Several quantity columns were still INTEGER, so MySQL silently rounded every
 * value written to them — a 5.50 m sale was recorded as 6 m, and the stock
 * movement audit trail drifted away from the product's own stock_quantity
 * (which was already decimal).
 *
 * This migration brings every remaining quantity column onto decimal(12,2) so
 * the database preserves fractional metres end to end.
 *
 * NOTE: from Laravel 11 onward, ->change() drops any modifier that is not
 * restated, so each column below re-declares its default/nullable state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_stock_transactions', function (Blueprint $table) {
            // The movement amount itself — the most damaging of the three.
            $table->decimal('quantity', 12, 2)->change();
            $table->decimal('previous_qty', 12, 2)->default(0)->change();
            $table->decimal('new_qty', 12, 2)->default(0)->change();
        });

        Schema::table('cs_products', function (Blueprint $table) {
            $table->decimal('reserved_quantity', 12, 2)->default(0)->change();
            $table->decimal('incoming_quantity', 12, 2)->default(0)->change();
            $table->decimal('suggested_reorder_qty', 12, 2)->default(0)->change();
        });

        Schema::table('cs_product_locations', function (Blueprint $table) {
            $table->decimal('quantity', 12, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        // Reverting truncates fractional metres. Kept only for migrate:rollback
        // parity — do not run this against production data.
        Schema::table('cs_product_locations', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
        });

        Schema::table('cs_products', function (Blueprint $table) {
            $table->integer('reserved_quantity')->default(0)->change();
            $table->integer('incoming_quantity')->default(0)->change();
            $table->integer('suggested_reorder_qty')->default(0)->change();
        });

        Schema::table('cs_stock_transactions', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->integer('previous_qty')->default(0)->change();
            $table->integer('new_qty')->default(0)->change();
        });
    }
};
