<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (!Schema::hasColumn('expenses', 'payment_method')) {
                $table->string('payment_method')->default('Cash')->after('category');
            }
            if (!Schema::hasColumn('expenses', 'vendor')) {
                $table->string('vendor')->nullable()->after('payment_method');
            }
            if (!Schema::hasColumn('expenses', 'reference')) {
                $table->string('reference')->nullable()->after('vendor');
            }
            if (!Schema::hasColumn('expenses', 'notes')) {
                $table->text('notes')->nullable()->after('reference');
            }
            if (!Schema::hasColumn('expenses', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('notes')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index('date', 'expenses_date_idx');
            $table->index('category', 'expenses_category_idx');
            $table->index(['category', 'date'], 'expenses_category_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('expenses_date_idx');
            $table->dropIndex('expenses_category_idx');
            $table->dropIndex('expenses_category_date_idx');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['payment_method', 'vendor', 'reference', 'notes']);
        });
    }
};
