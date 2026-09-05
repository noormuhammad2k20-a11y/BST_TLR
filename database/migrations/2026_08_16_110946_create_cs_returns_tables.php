<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('cs_order_id')->constrained('cs_orders')->onDelete('cascade');
            $table->foreignId('cs_customer_id')->nullable()->constrained('cs_customers')->nullOnDelete();
            $table->string('status')->default('Pending'); // Pending, Approved, Rejected, Completed
            $table->decimal('total_refund_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cs_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cs_return_id')->constrained('cs_returns')->onDelete('cascade');
            $table->foreignId('cs_order_item_id')->constrained('cs_order_items')->onDelete('cascade');
            $table->foreignId('cs_product_id')->constrained('cs_products')->onDelete('cascade');
            $table->decimal('quantity', 10, 2);
            $table->string('reason'); // Wrong Product, Wrong Size, Defective, Damaged, Color Issue, Customer Changed Mind, Other
            $table->string('action_type'); // Refund, Exchange
            $table->foreignId('exchange_product_id')->nullable()->constrained('cs_products')->nullOnDelete();
            $table->decimal('refund_amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_return_items');
        Schema::dropIfExists('cs_returns');
    }
};
