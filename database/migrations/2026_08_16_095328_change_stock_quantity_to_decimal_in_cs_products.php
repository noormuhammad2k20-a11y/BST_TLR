<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_products', function (Blueprint $table) {
            $table->decimal('stock_quantity', 12, 2)->default(0)->change();
            $table->decimal('low_stock_threshold', 12, 2)->default(10)->change();
        });
    }

    public function down(): void
    {
        Schema::table('cs_products', function (Blueprint $table) {
            $table->integer('stock_quantity')->default(0)->change();
            $table->integer('low_stock_threshold')->default(10)->change();
        });
    }
};
