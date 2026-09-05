<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cs_customer_id')->constrained('cs_customers')->onDelete('cascade');
            $table->decimal('points', 10, 2);
            $table->string('type'); // Earned, Redeemed, Adjustment
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_loyalty_transactions');
    }
};
