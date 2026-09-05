<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_orders', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('cs_customer_id')->nullable()->constrained('cs_customers')->nullOnDelete();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('gross_profit', 10, 2)->default(0);
            $table->decimal('total_meters_sold', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->string('payment_method')->default('Cash'); // Cash, Card, Transfer
            $table->string('status')->default('Completed'); // Completed, Returned
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_orders');
    }
};
