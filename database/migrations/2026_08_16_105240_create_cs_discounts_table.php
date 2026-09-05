<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cs_discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('type'); // percentage, fixed, product_specific, category, customer_specific, buy_x_get_y, min_order, seasonal, coupon
            $table->decimal('value', 10, 2)->default(0); // The discount amount/percentage
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->decimal('min_purchase', 10, 2)->default(0);
            $table->decimal('max_discount', 10, 2)->nullable();
            $table->integer('usage_limit')->nullable();
            $table->integer('customer_limit')->nullable(); // limit per customer
            $table->integer('usage_count')->default(0);
            $table->json('applicable_products')->nullable();
            $table->json('applicable_categories')->nullable();
            $table->json('applicable_customers')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cs_discounts');
    }
};
