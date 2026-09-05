<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_customer_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cs_customer_id')->constrained('cs_customers')->cascadeOnDelete();
            $table->unsignedBigInteger('cs_order_id')->nullable(); // For strict mapping if needed later
            $table->decimal('amount', 12, 2);
            $table->string('payment_type')->default('Due Payment'); // Sale Payment, Due Payment, Advance, Refund
            $table->string('payment_method')->default('Cash'); // Cash, Card, EasyPaisa, JazzCash, Bank Transfer, Cheque
            $table->string('reference')->nullable();
            $table->date('payment_date');
            $table->string('received_by')->nullable();
            $table->string('status')->default('Completed'); // Completed, Reversed
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('cs_customer_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cs_customer_id')->constrained('cs_customers')->cascadeOnDelete();
            $table->date('date');
            $table->string('type'); // Opening Balance, Purchase, Payment, Refund, Adjustment
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->decimal('debit', 12, 2)->default(0);  // Increases due balance
            $table->decimal('credit', 12, 2)->default(0); // Decreases due balance
            $table->decimal('balance', 12, 2)->default(0); // Running balance
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_customer_ledgers');
        Schema::dropIfExists('cs_customer_payments');
    }
};
