<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $t) {
            $t->string('payment_period', 10)->default('Monthly');
        });
        Schema::table('staff_payments', function (Blueprint $t) {
            $t->string('period', 10)->nullable()->change();
            $t->json('earnings_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_payments', fn (Blueprint $t) => $t->dropColumn('earnings_snapshot'));
        Schema::table('staff', fn (Blueprint $t) => $t->dropColumn('payment_period'));
        // Keep the wider period column so existing daily/weekly history is never truncated.
    }
};
