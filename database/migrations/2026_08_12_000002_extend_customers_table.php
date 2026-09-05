<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'code')) {
                $table->string('code')->nullable()->unique()->after('id');
            }
            if (!Schema::hasColumn('customers', 'address')) {
                $table->text('address')->nullable()->after('city');
            }
            if (!Schema::hasColumn('customers', 'behavior')) {
                $table->string('behavior')->nullable()->after('type');
            }
            if (!Schema::hasColumn('customers', 'loyalty_score')) {
                $table->decimal('loyalty_score', 3, 1)->nullable()->after('behavior');
            }
            if (!Schema::hasColumn('customers', 'last_visit_at')) {
                $table->timestamp('last_visit_at')->nullable()->after('loyalty_score');
            }
            if (!Schema::hasColumn('customers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('last_visit_at');
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index('name', 'customers_name_idx');
            $table->index('phone', 'customers_phone_idx');
            $table->index('type', 'customers_type_idx');
            $table->index('created_at', 'customers_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_name_idx');
            $table->dropIndex('customers_phone_idx');
            $table->dropIndex('customers_type_idx');
            $table->dropIndex('customers_created_at_idx');
            $table->dropUnique(['code']);
            $table->dropColumn([
                'code', 'address', 'behavior', 'loyalty_score', 'last_visit_at', 'is_active',
            ]);
        });
    }
};
