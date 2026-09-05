<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cs_supplier_id')->constrained('cs_suppliers')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('payment_method')->default('Cash'); // Cash, Bank Transfer, Cheque, Other
            $table->string('reference_number')->nullable();
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_supplier_payments');
    }
};
