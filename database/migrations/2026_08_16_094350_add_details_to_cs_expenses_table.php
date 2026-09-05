<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_expenses', function (Blueprint $table) {
            $table->string('payment_method')->default('Cash')->after('amount');
            $table->string('paid_by')->nullable()->after('payment_method');
            $table->string('reference')->nullable()->after('paid_by');
            $table->string('attachment')->nullable()->after('reference');
            $table->text('notes')->nullable()->after('attachment');
            $table->string('status')->default('Approved')->after('notes');
            $table->string('created_by')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('cs_expenses', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method', 'paid_by', 'reference', 'attachment', 'notes', 'status', 'created_by'
            ]);
        });
    }
};
