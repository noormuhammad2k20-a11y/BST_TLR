<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_services', function (Blueprint $table) {
            if (!Schema::hasColumn('product_services', 'cost_price')) {
                $table->decimal('cost_price', 10, 2)->nullable()->after('price');
            }
            if (!Schema::hasColumn('product_services', 'stock_quantity')) {
                $table->integer('stock_quantity')->nullable()->after('cost_price');
            }
            if (!Schema::hasColumn('product_services', 'low_stock_threshold')) {
                $table->integer('low_stock_threshold')->default(10)->after('stock_quantity');
            }
            if (!Schema::hasColumn('product_services', 'unit')) {
                $table->string('unit')->nullable()->after('low_stock_threshold');
            }
            if (!Schema::hasColumn('product_services', 'duration_days')) {
                $table->unsignedSmallInteger('duration_days')->nullable()->after('unit');
            }
        });

        Schema::table('product_services', function (Blueprint $table) {
            $table->index('status', 'product_services_status_idx');
            $table->index('category', 'product_services_category_idx');
            $table->index('type', 'product_services_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_services', function (Blueprint $table) {
            $table->dropIndex('product_services_status_idx');
            $table->dropIndex('product_services_category_idx');
            $table->dropIndex('product_services_type_idx');
            $table->dropColumn([
                'cost_price', 'stock_quantity', 'low_stock_threshold', 'unit', 'duration_days',
            ]);
        });
    }
};
