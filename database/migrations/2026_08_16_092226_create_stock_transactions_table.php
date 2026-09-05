<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cs_product_id')->constrained('cs_products')->onDelete('cascade');
            $table->enum('type', ['in', 'out', 'adjustment']);
            $table->integer('quantity'); // Positive for 'in' and 'adjustment', positive for 'out'
            $table->string('reference')->nullable(); // e.g. PO-1234, Order #5678, Manual
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_stock_transactions');
    }
};
