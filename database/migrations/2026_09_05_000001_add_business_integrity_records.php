<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integrity_audits', function (Blueprint $t) {
            $t->id(); $t->string('kind'); $t->string('source_table'); $t->unsignedBigInteger('source_id');
            $t->json('evidence'); $t->timestamp('created_at')->useCurrent();
        });
        foreach (['customers', 'orders', 'cs_customers', 'cs_orders', 'cs_suppliers', 'cs_products'] as $table) {
            if (!Schema::hasColumn($table, 'deleted_at')) Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
        }
        Schema::table('users', fn (Blueprint $t) => $t->unsignedInteger('session_version')->default(0));
        foreach (['payments', 'cs_customer_payments', 'staff_payments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->timestamp('reversed_at')->nullable();
                $t->unsignedBigInteger('reversed_by')->nullable();
                $t->unsignedBigInteger('reverses_payment_id')->nullable()->unique();
                $t->string('operation_key', 100)->nullable()->unique();
            });
        }
        Schema::table('cs_orders', function (Blueprint $t) {
            $t->decimal('remaining_amount', 14, 2)->nullable();
            $t->decimal('refund_due', 14, 2)->default(0);
            $t->string('payment_status')->default('Unpaid');
        });
        Schema::create('cs_payment_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('payment_id')->constrained('cs_customer_payments')->restrictOnDelete();
            $t->foreignId('order_id')->nullable()->constrained('cs_orders')->restrictOnDelete();
            $t->decimal('amount', 14, 2); $t->timestamps();
        });
        Schema::create('cs_financial_adjustments', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_id')->constrained('cs_orders')->restrictOnDelete();
            $t->string('kind'); $t->decimal('amount', 14, 2); $t->decimal('quantity', 14, 2)->default(0);
            $t->decimal('cost', 14, 2)->default(0); $t->string('operation_key', 100)->unique();
            $t->unsignedBigInteger('actor_id')->nullable(); $t->string('reference')->nullable(); $t->timestamps();
        });
        Schema::create('cs_inventory_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('order_item_id')->constrained('cs_order_items')->restrictOnDelete();
            $t->foreignId('location_id')->constrained('cs_locations')->restrictOnDelete();
            $t->decimal('quantity', 14, 2); $t->decimal('restored_quantity', 14, 2)->default(0); $t->timestamps();
        });
        Schema::table('cs_returns', function (Blueprint $t) {
            $t->timestamp('processed_at')->nullable(); $t->timestamp('settled_at')->nullable();
        });
        // Preserve the original orphan identifier before setting the broken link to NULL.
        DB::table('cs_customer_payments')->whereNotNull('cs_order_id')->whereNotIn('cs_order_id', DB::table('cs_orders')->select('id'))
            ->orderBy('id')->each(function ($row) {
                DB::table('integrity_audits')->insert(['kind'=>'orphan_order_link','source_table'=>'cs_customer_payments',
                    'source_id'=>$row->id,'evidence'=>json_encode(['cs_order_id'=>$row->cs_order_id])]);
                DB::table('cs_customer_payments')->where('id', $row->id)->update(['cs_order_id'=>null]);
            });
        Schema::table('cs_customer_payments', fn (Blueprint $t) => $t->foreign('cs_order_id')->references('id')->on('cs_orders')->nullOnDelete());
        // Financial history must survive account and catalogue deletion.
        foreach ([['cs_customer_payments','cs_customer_id','cs_customers'], ['cs_customer_ledgers','cs_customer_id','cs_customers'],
            ['orders','customer_id','customers'], ['measurements','customer_id','customers'],
            ['payments','order_id','orders'], ['cs_returns','cs_order_id','cs_orders'],
            ['cs_order_items','cs_order_id','cs_orders'], ['cs_order_items','cs_product_id','cs_products'],
            ['cs_return_items','cs_order_item_id','cs_order_items'], ['cs_return_items','cs_product_id','cs_products'],
            ['cs_purchases','cs_supplier_id','cs_suppliers'], ['cs_supplier_payments','cs_supplier_id','cs_suppliers'],
            ['cs_supplier_ledgers','cs_supplier_id','cs_suppliers'], ['cs_purchase_items','cs_product_id','cs_products'],
            ['cs_stock_transactions','cs_product_id','cs_products']] as [$table,$column,$parent]) {
            Schema::table($table, function (Blueprint $t) use ($column,$parent) {
                $t->dropForeign([$column]); $t->foreign($column)->references('id')->on($parent)->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Forward-only financial migration: restore a verified backup instead of dropping audit history.');
    }
};
