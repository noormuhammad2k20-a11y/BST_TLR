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
            $table->string('garment_type')->nullable();
            $table->string('tailor')->nullable();
            $table->string('unit')->default('cm');
            $table->decimal('length', 8, 2)->nullable();
            $table->decimal('shoulder_width', 8, 2)->nullable();
            $table->decimal('sleeve_length', 8, 2)->nullable();
            $table->decimal('chest', 8, 2)->nullable();
            $table->decimal('waist', 8, 2)->nullable();
            $table->decimal('hip', 8, 2)->nullable();
            $table->decimal('collar', 8, 2)->nullable();
            $table->decimal('ghera', 8, 2)->nullable();
            $table->decimal('patti', 8, 2)->nullable();
            $table->decimal('button', 8, 2)->nullable();
            $table->decimal('cuff', 8, 2)->nullable();
            $table->decimal('koni', 8, 2)->nullable();
            $table->decimal('elbow', 8, 2)->nullable();
            $table->decimal('armhole', 8, 2)->nullable();
            $table->decimal('takai', 8, 2)->nullable();
            $table->decimal('salwar_length', 8, 2)->nullable();
            $table->decimal('pancho', 8, 2)->nullable();
            $table->text('notes')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->dropColumn([
                'garment_type', 'tailor', 'unit',
                'length', 'shoulder_width', 'sleeve_length', 'chest', 'waist',
                'hip', 'collar', 'ghera', 'patti', 'button', 'cuff', 'koni',
                'elbow', 'armhole', 'takai', 'salwar_length', 'pancho', 'notes'
            ]);
        });
    }
};
