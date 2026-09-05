<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_products', function (Blueprint $table) {
            $table->foreignId('cs_supplier_id')->nullable()->constrained('cs_suppliers')->nullOnDelete()->after('cs_category_id');
            $table->integer('suggested_reorder_qty')->default(0)->after('low_stock_threshold');
            $table->boolean('ignore_stock_alerts')->default(false)->after('suggested_reorder_qty');
        });
    }

    public function down(): void
    {
        Schema::table('cs_products', function (Blueprint $table) {
            $table->dropForeign(['cs_supplier_id']);
            $table->dropColumn(['cs_supplier_id', 'suggested_reorder_qty', 'ignore_stock_alerts']);
        });
    }
};
