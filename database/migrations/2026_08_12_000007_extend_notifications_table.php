<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'title')) {
                $table->string('title')->nullable()->after('customer_id');
            }
            if (!Schema::hasColumn('notifications', 'category')) {
                $table->string('category')->default('system')->after('type');
            }
            if (!Schema::hasColumn('notifications', 'icon')) {
                $table->string('icon')->nullable()->after('category');
            }
            if (!Schema::hasColumn('notifications', 'color')) {
                $table->string('color')->default('info')->after('icon');
            }
            if (!Schema::hasColumn('notifications', 'action_url')) {
                $table->string('action_url')->nullable()->after('color');
            }
            if (!Schema::hasColumn('notifications', 'order_id')) {
                $table->foreignId('order_id')->nullable()->after('customer_id')
                    ->constrained('orders')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('notifications', 'read_at')) {
                $table->timestamp('read_at')->nullable()->after('is_read');
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index('is_read', 'notifications_is_read_idx');
            $table->index('category', 'notifications_category_idx');
            $table->index('created_at', 'notifications_created_at_idx');
            $table->index(['is_read', 'created_at'], 'notifications_read_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_is_read_idx');
            $table->dropIndex('notifications_category_idx');
            $table->dropIndex('notifications_created_at_idx');
            $table->dropIndex('notifications_read_created_idx');
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['title', 'category', 'icon', 'color', 'action_url', 'read_at']);
        });
    }
};
