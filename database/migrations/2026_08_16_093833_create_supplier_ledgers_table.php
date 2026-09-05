<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_supplier_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cs_supplier_id')->constrained('cs_suppliers')->onDelete('cascade');
            $table->date('date');
            $table->string('type'); // Purchase, Payment, Return, Opening Balance
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->decimal('debit', 10, 2)->default(0); // Add to balance (Purchase)
            $table->decimal('credit', 10, 2)->default(0); // Subtract from balance (Payment)
            $table->decimal('balance', 10, 2); // Running balance
            $table->unsignedBigInteger('related_id')->nullable(); // ID of Purchase/Payment
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_supplier_ledgers');
    }
};
