<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('legacy_items')->nullable();
            $table->json('billing_snapshot')->nullable();
            $table->unsignedInteger('edit_version')->default(0);
            $table->timestamp('items_migrated_at')->nullable();
        });
        Schema::table('product_services', function (Blueprint $table) {
            $table->foreignId('canonical_id')->nullable()->constrained('product_services')->restrictOnDelete();
            $table->string('normalized_name')->nullable()->unique();
            $table->string('measurement_profile', 40)->nullable();
            $table->boolean('requires_measurements')->nullable();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_service_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->string('fabric')->nullable();
            $table->text('style_notes')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['order_id', 'position']);
        });
        Schema::create('order_item_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('piece_no');
            $table->string('unit', 2)->default('in');
            $table->json('profile');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['order_item_id', 'piece_no']);
        });
        Schema::table('measurements', function (Blueprint $table) {
            $table->foreignId('order_item_piece_id')->nullable()->unique()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This migration retains order history and is forward-only. Restore a verified backup for rollback.');
    }
};
