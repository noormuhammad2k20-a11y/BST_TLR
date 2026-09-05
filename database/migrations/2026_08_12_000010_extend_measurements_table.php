<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            if (!Schema::hasColumn('measurements', 'is_template')) {
                $table->boolean('is_template')->default(false)->after('garment_type');
            }
            if (!Schema::hasColumn('measurements', 'template_name')) {
                $table->string('template_name')->nullable()->after('is_template');
            }
            if (!Schema::hasColumn('measurements', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('customer_id')
                    ->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('measurements', function (Blueprint $table) {
            $table->index('garment_type', 'measurements_garment_type_idx');
            $table->index('created_at', 'measurements_created_at_idx');
            $table->index(['customer_id', 'garment_type'], 'measurements_customer_garment_idx');
            $table->index('is_template', 'measurements_is_template_idx');
        });
    }

    public function down(): void
    {
        Schema::table('measurements', function (Blueprint $table) {
            $table->dropIndex('measurements_garment_type_idx');
            $table->dropIndex('measurements_created_at_idx');
            $table->dropIndex('measurements_customer_garment_idx');
            $table->dropIndex('measurements_is_template_idx');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['is_template', 'template_name']);
        });
    }
};
