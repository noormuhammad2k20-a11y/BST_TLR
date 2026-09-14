<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('customer_ledger_charges', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('type'); $t->string('description'); $t->decimal('amount',14,2);
            $t->dateTime('date'); $t->string('operation_key',100)->unique();
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete(); $t->timestamps();
        });
        Schema::table('payments', fn(Blueprint $t) => $t->foreignId('ledger_charge_id')->nullable()->constrained('customer_ledger_charges')->restrictOnDelete());
    }
    public function down(): void {
        Schema::table('payments', fn(Blueprint $t) => $t->dropConstrainedForeignId('ledger_charge_id'));
        Schema::dropIfExists('customer_ledger_charges');
    }
};
