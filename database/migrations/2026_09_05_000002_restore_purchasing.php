<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('cs_purchase_orders')) Schema::create('cs_purchase_orders',function(Blueprint $t) {
            $t->id(); $t->foreignId('cs_supplier_id')->constrained('cs_suppliers')->restrictOnDelete();
            $t->string('po_number')->unique(); $t->string('status')->default('Draft'); $t->date('order_date');
            $t->decimal('total_amount',14,2)->default(0); $t->decimal('paid_amount',14,2)->default(0);
            $t->decimal('due_amount',14,2)->default(0); $t->date('expected_date')->nullable();
            $t->text('notes')->nullable(); $t->timestamps();
        });
        if (!Schema::hasTable('cs_purchase_order_items')) Schema::create('cs_purchase_order_items',function(Blueprint $t) {
            $t->id(); $t->foreignId('cs_purchase_order_id')->constrained('cs_purchase_orders')->restrictOnDelete();
            $t->foreignId('cs_product_id')->constrained('cs_products')->restrictOnDelete();
            $t->decimal('quantity',14,2); $t->decimal('received_quantity',14,2)->default(0); $t->decimal('unit_cost',14,2); $t->timestamps();
        });
        // Some client databases contain the original PO tables even though the
        // historical migration was later replaced by a no-op. Keep their
        // original fields and add the canonical names used by the restored flow.
        if (!Schema::hasColumn('cs_purchase_order_items','quantity')) {
            Schema::table('cs_purchase_order_items',fn(Blueprint $t)=>$t->decimal('quantity',14,2)->nullable()->after('cs_product_id'));
            DB::table('cs_purchase_order_items')->update(['quantity'=>DB::raw('ordered_quantity')]);
            Schema::table('cs_purchase_order_items',fn(Blueprint $t)=>$t->decimal('quantity',14,2)->nullable(false)->change());
        }
        if (!Schema::hasColumn('cs_purchase_order_items','unit_cost')) {
            Schema::table('cs_purchase_order_items',fn(Blueprint $t)=>$t->decimal('unit_cost',14,2)->nullable()->after('received_quantity'));
            DB::table('cs_purchase_order_items')->update(['unit_cost'=>DB::raw('rate')]);
            Schema::table('cs_purchase_order_items',fn(Blueprint $t)=>$t->decimal('unit_cost',14,2)->nullable(false)->change());
        }
        Schema::create('cs_purchase_receipts',function(Blueprint $t) {
            $t->id(); $t->foreignId('purchase_order_id')->nullable()->constrained('cs_purchase_orders')->restrictOnDelete();
            $t->foreignId('purchase_id')->constrained('cs_purchases')->restrictOnDelete();
            $t->foreignId('location_id')->constrained('cs_locations')->restrictOnDelete();
            $t->string('operation_key',100)->unique(); $t->unsignedBigInteger('actor_id')->nullable(); $t->timestamps();
        });
        Schema::table('cs_supplier_payments',function(Blueprint $t) {
            $t->string('operation_key',100)->nullable()->unique(); $t->timestamp('reversed_at')->nullable();
        });
        Schema::table('cs_purchase_items',function(Blueprint $t) {
            $t->decimal('quantity',14,2)->change();
        });
    }
    public function down(): void { throw new RuntimeException('Forward-only: purchasing history must be retained.'); }
};
