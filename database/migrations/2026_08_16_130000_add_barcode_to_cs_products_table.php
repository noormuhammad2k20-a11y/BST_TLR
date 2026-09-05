<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Barcode lookup for products and the POS.
 *
 * Product search is specified as Name / SKU / Barcode, but there was no
 * barcode column to search against. Nullable and unique: most fabric rolls
 * are tagged in-house and may never get a barcode, but where one exists it
 * must resolve to exactly one product or a scan at the till is ambiguous.
 *
 * MySQL treats NULLs as distinct in a unique index, so any number of
 * un-barcoded products can coexist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_products', function (Blueprint $table) {
            $table->string('barcode')->nullable()->unique()->after('sku');
        });
    }

    public function down(): void
    {
        Schema::table('cs_products', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
