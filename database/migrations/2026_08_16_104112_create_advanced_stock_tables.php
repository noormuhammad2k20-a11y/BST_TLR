<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Storage Locations
        Schema::create('cs_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. Main Store, Warehouse A
            $table->string('type')->default('Store'); // Store, Warehouse
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Product Quantities per Location
        Schema::create('cs_product_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cs_product_id')->constrained('cs_products')->onDelete('cascade');
            $table->foreignId('cs_location_id')->constrained('cs_locations')->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->timestamps();
            
            $table->unique(['cs_product_id', 'cs_location_id']);
        });

        // 3. Extend Products Table
        Schema::table('cs_products', function (Blueprint $table) {
            $table->integer('reserved_quantity')->default(0)->after('stock_quantity');
            $table->integer('incoming_quantity')->default(0)->after('reserved_quantity');
        });

        // 4. Extend Stock Transactions Table
        // First modify the enum
        DB::statement("ALTER TABLE cs_stock_transactions MODIFY COLUMN type ENUM('in', 'out', 'adjustment', 'transfer') NOT NULL");
        
        Schema::table('cs_stock_transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null')->after('cs_product_id');
            $table->string('reason')->nullable()->after('quantity'); // Damaged, Lost, Sale, etc.
            $table->integer('previous_qty')->default(0)->after('reason');
            $table->integer('new_qty')->default(0)->after('previous_qty');
            $table->foreignId('from_location_id')->nullable()->constrained('cs_locations')->onDelete('set null')->after('new_qty');
            $table->foreignId('to_location_id')->nullable()->constrained('cs_locations')->onDelete('set null')->after('from_location_id');
        });

        // Insert Default Location
        DB::table('cs_locations')->insert([
            'name' => 'Main Store',
            'type' => 'Store',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Sync existing products to Main Store
        $products = DB::table('cs_products')->get();
        foreach ($products as $product) {
            DB::table('cs_product_locations')->insert([
                'cs_product_id' => $product->id,
                'cs_location_id' => 1,
                'quantity' => $product->stock_quantity,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('cs_stock_transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['from_location_id']);
            $table->dropForeign(['to_location_id']);
            $table->dropColumn(['user_id', 'reason', 'previous_qty', 'new_qty', 'from_location_id', 'to_location_id']);
        });

        DB::statement("ALTER TABLE cs_stock_transactions MODIFY COLUMN type ENUM('in', 'out', 'adjustment') NOT NULL");

        Schema::table('cs_products', function (Blueprint $table) {
            $table->dropColumn(['reserved_quantity', 'incoming_quantity']);
        });

        Schema::dropIfExists('cs_product_locations');
        Schema::dropIfExists('cs_locations');
    }
};
