<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tailor_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        Schema::create('tailor_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tailor_category_id')->constrained('tailor_categories')->onDelete('cascade');
            $table->foreignId('product_service_id')->constrained('product_services')->onDelete('restrict');
            $table->decimal('price', 10, 2);
            $table->string('status')->default('Active');
            $table->timestamps();
        });

        // Seed data from existing ProductService
        $services = DB::table('product_services')
            ->where(function ($query) {
                $query->where('type', 'Service')
                      ->orWhereNull('stock_quantity');
            })
            ->whereNull('canonical_id')
            ->where('status', 'Active')
            ->get();

        foreach ($services as $service) {
            $categoryName = $service->category ?: 'General';
            
            $category = DB::table('tailor_categories')->where('name', $categoryName)->first();
            if (!$category) {
                $categoryId = DB::table('tailor_categories')->insertGetId([
                    'name' => $categoryName,
                    'status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $categoryId = $category->id;
            }

            DB::table('tailor_rates')->insert([
                'tailor_category_id' => $categoryId,
                'product_service_id' => $service->id,
                'price' => $service->price,
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tailor_rates');
        Schema::dropIfExists('tailor_categories');
    }
};
