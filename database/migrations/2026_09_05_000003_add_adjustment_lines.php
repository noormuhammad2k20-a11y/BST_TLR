<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cs_adjustment_lines',function(Blueprint $t) {
            $t->id(); $t->foreignId('adjustment_id')->constrained('cs_financial_adjustments')->restrictOnDelete();
            $t->foreignId('order_item_id')->constrained('cs_order_items')->restrictOnDelete();
            $t->decimal('amount',14,2); $t->decimal('quantity',14,2); $t->decimal('cost',14,2);
        });
    }
    public function down(): void { throw new RuntimeException('Forward-only audit history.'); }
};
