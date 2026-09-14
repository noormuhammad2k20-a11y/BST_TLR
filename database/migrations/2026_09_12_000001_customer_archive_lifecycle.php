<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use App\Services\CustomerLifecycle;

return new class extends Migration {
    public function up(): void
    {
        // Reserve each normalized phone once, preferring the existing active record.
        // Legacy duplicates retain their data and a NULL key; application validation
        // also checks those records. Never merge or delete customer data here.
        $keys = [];
        foreach (DB::table('customers')->orderByRaw('deleted_at IS NOT NULL')->orderBy('id')->get(['id','phone']) as $customer) {
            $key = CustomerLifecycle::phoneKey($customer->phone);
            $keys[$key] ??= $customer->id;
        }
        Schema::table('customers', function (Blueprint $t) {
            $t->timestamp('anonymized_at')->nullable();
            $t->string('phone_key', 64)->nullable()->unique();
        });
        foreach ($keys as $key => $id) DB::table('customers')->where('id',$id)->update(['phone_key'=>(string)$key]);
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropUnique(['phone_key']);
            $t->dropColumn(['phone_key','anonymized_at']);
        });
    }
};
