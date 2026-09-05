<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_suppliers', function (Blueprint $table) {
            $table->string('company_name')->nullable()->after('name');
            $table->string('contact_person')->nullable()->after('company_name');
            $table->string('whatsapp')->nullable()->after('phone');
            $table->string('email')->nullable()->after('whatsapp');
            $table->string('city')->nullable()->after('email');
            $table->text('address')->nullable()->after('city');
            $table->string('supplier_type')->default('Wholesale Supplier')->after('address');
            $table->string('tax_ntn')->nullable()->after('supplier_type');
            $table->string('payment_terms')->nullable()->after('tax_ntn');
            $table->text('notes')->nullable()->after('payment_terms');
            $table->string('status')->default('Active')->after('total_purchases');
        });
    }

    public function down(): void
    {
        Schema::table('cs_suppliers', function (Blueprint $table) {
            $table->dropColumn([
                'company_name', 'contact_person', 'whatsapp', 'email', 'city',
                'address', 'supplier_type', 'tax_ntn', 'payment_terms', 'notes', 'status'
            ]);
        });
    }
};
