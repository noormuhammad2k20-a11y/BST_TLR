<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staff_service_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->foreignId('product_service_id')->constrained('product_services')->cascadeOnDelete();
            $table->decimal('rate', 10, 2);
            $table->timestamps();
            
            $table->unique(['staff_id', 'product_service_id']);
        });

        Schema::create('staff_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('method', 50);
            $table->date('given_on');
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('staff_work_logs', function (Blueprint $table) {
            $table->json('rate_breakdown')->nullable()->after('amount');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('tailor_rate_override', 10, 2)->nullable()->after('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('tailor_rate_override');
        });

        Schema::table('staff_work_logs', function (Blueprint $table) {
            $table->dropColumn('rate_breakdown');
        });

        Schema::dropIfExists('staff_advances');
        Schema::dropIfExists('staff_service_rates');
    }
};
