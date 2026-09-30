<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
            $table->foreignId('parent_customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('relationship', 30)->nullable();
        });
    }

    public function down(): void
    {
        // Refuse a destructive rollback rather than discard family links or invent phones.
        if (DB::table('customers')->whereNotNull('parent_customer_id')->orWhereNull('phone')->exists()) {
            throw new RuntimeException('Resolve linked customers and missing phones before rolling back family support.');
        }
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_customer_id');
            $table->dropColumn('relationship');
            $table->string('phone')->nullable(false)->change();
        });
    }
};
