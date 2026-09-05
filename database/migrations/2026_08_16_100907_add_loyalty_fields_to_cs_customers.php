<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_customers', function (Blueprint $table) {
            $table->string('customer_level')->default('New'); // New, Regular, VIP, Wholesale
            $table->decimal('loyalty_points', 10, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('cs_customers', function (Blueprint $table) {
            $table->dropColumn(['customer_level', 'loyalty_points']);
        });
    }
};
