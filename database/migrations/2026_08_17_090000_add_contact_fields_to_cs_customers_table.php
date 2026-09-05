<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer contact details.
 *
 * cs_customers only ever had name / phone / balances, yet the quick-add
 * customer form in Smart Checkout posted a `city` and wrote it straight into
 * the model. Because the column did not exist, every quick-add failed with
 * "Unknown column 'city'" — so a cashier could never create a customer at the
 * till. The Customers listing also rendered $c->city, which silently resolved
 * to null.
 *
 * These are the fields the existing UI already expects, plus an index on phone
 * because that is what both customer search and the POS lookup filter on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cs_customers', function (Blueprint $table) {
            if (!Schema::hasColumn('cs_customers', 'city')) {
                $table->string('city')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('cs_customers', 'email')) {
                $table->string('email')->nullable()->after('city');
            }
            if (!Schema::hasColumn('cs_customers', 'address')) {
                $table->text('address')->nullable()->after('email');
            }
            if (!Schema::hasColumn('cs_customers', 'notes')) {
                $table->text('notes')->nullable()->after('address');
            }
        });

        Schema::table('cs_customers', function (Blueprint $table) {
            // Customer search and the POS lookup both filter on phone.
            $table->index('phone', 'cs_customers_phone_index');
        });
    }

    public function down(): void
    {
        Schema::table('cs_customers', function (Blueprint $table) {
            $table->dropIndex('cs_customers_phone_index');
            $table->dropColumn(['city', 'email', 'address', 'notes']);
        });
    }
};
