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
        Schema::table('measurements', function (Blueprint $table) {
            $table->dropColumn('losing');
            $table->decimal('chest_losing', 8, 2)->nullable()->after('chest');
            $table->decimal('waist_losing', 8, 2)->nullable()->after('waist');
            $table->decimal('hip_losing', 8, 2)->nullable()->after('hip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->decimal('losing', 8, 2)->nullable();
            $table->dropColumn(['chest_losing', 'waist_losing', 'hip_losing']);
        });
    }
};
