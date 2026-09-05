<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['cs_purchase_receipts', 'cs_purchase_order_items', 'cs_purchase_orders',
                  'cs_purchase_items', 'cs_purchases', 'cs_supplier_ledgers', 'cs_supplier_payments'] as $table) {
            Schema::dropIfExists($table);
        }

        if (Schema::hasTable('cs_products') && Schema::hasColumn('cs_products', 'cs_supplier_id')) {
            Schema::table('cs_products', function (Blueprint $table) {
                $table->dropForeign(['cs_supplier_id']);
                $table->dropColumn('cs_supplier_id');
            });
        }

        Schema::dropIfExists('cs_suppliers');

        if (Schema::hasTable('cs_permissions')) {
            $ids = DB::table('cs_permissions')
                ->whereIn('module', ['Suppliers', 'Purchases', 'Users'])
                ->orWhere('name', 'Stock - Transfer')
                ->pluck('id');
            if ($ids->isNotEmpty() && Schema::hasTable('cs_role_permissions')) {
                DB::table('cs_role_permissions')->whereIn('permission_id', $ids)->delete();
            }
            DB::table('cs_permissions')->whereIn('id', $ids)->delete();
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only: the owner removed supplier and purchasing data permanently.');
    }
};
