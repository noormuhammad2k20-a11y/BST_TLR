<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff: the people who actually stitch, and what the shop owes them.
 *
 * Kept apart from `users` on purpose. A user is a login; a staff member is a
 * person on the payroll. Most tailors never sign in to the panel, and payroll
 * has no business living on an authentication record. The optional `user_id`
 * links the two where a tailor does happen to have an account.
 *
 * Orders gain `staff_id`. The old `tailor_id` column is left exactly as it is
 * so nothing that reads it breaks; the new column is backfilled from it below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->id();

            // Set when this person also has a panel login. Null for the many
            // who do not.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->date('joining_date')->nullable();

            // Master Tailor, Tailor, Cutter, Helper, Finisher — free text so a
            // shop can use its own words without a migration.
            $table->string('role')->default('Tailor');

            // Monthly | Per Suit | Both. A shop that pays a retainer plus a
            // piece rate is common enough to deserve first-class support.
            $table->string('salary_type')->default('Monthly');
            $table->decimal('monthly_salary', 10, 2)->default(0);

            // Each tailor sets their own rate — never assume one shop rate.
            $table->decimal('per_suit_rate', 10, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('is_active', 'staff_is_active_idx');
            $table->index('role', 'staff_role_idx');
        });

        /* ---------------------------------------------------------------
         | Salary payments
         |----------------------------------------------------------------
         | Deliberately its own table. Customer money lives in `payments`
         | and drives revenue; wages are the opposite direction and must
         | never be summed into the same figure by accident.
         */
        Schema::create('staff_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 10, 2);
            $table->string('method')->default('Cash');

            // The month this payment settles, as YYYY-MM. Lets a shop pay a
            // salary late without the history losing which month it was for.
            $table->string('period', 7)->nullable();

            $table->date('paid_on');

            // Paid | Partial | Pending — what this payment leaves the period at.
            $table->string('status')->default('Paid');

            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['staff_id', 'paid_on'], 'staff_payments_staff_date_idx');
            $table->index('period', 'staff_payments_period_idx');
        });

        /* ---------------------------------------------------------------
         | Completed stitching
         |----------------------------------------------------------------
         | One row per piece of finished work. `rate` and `amount` are
         | stored rather than derived: a tailor's rate can change, and last
         | month's completed work must keep the rate it was earned at.
         */
        Schema::create('staff_work_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->string('garment')->nullable();
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('rate', 10, 2)->default(0);
            $table->decimal('amount', 10, 2)->default(0);

            $table->date('completed_on');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'completed_on'], 'staff_work_staff_date_idx');

            // One log per order: the completion hook must never double-count if
            // an order is re-saved or moved back and forth.
            $table->unique('order_id', 'staff_work_order_unique');
        });

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'staff_id')) {
                $table->foreignId('staff_id')->nullable()->after('tailor_id')
                      ->constrained('staff')->nullOnDelete();
            }
        });

        $this->seedFromExistingTailors();
    }

    /**
     * Turn the tailors that already exist as users into staff records, and
     * point existing orders at them, so the module opens with the shop's real
     * people in it rather than an empty table.
     */
    private function seedFromExistingTailors(): void
    {
        $tailors = DB::table('users')->where('role', 'tailor')->get();

        foreach ($tailors as $user) {
            $staffId = DB::table('staff')->insertGetId([
                'user_id'      => $user->id,
                'name'         => $user->name,
                'phone'        => $user->phone ?? null,
                'joining_date' => $user->created_at ? substr((string) $user->created_at, 0, 10) : null,
                'role'         => $user->title ?: 'Tailor',
                'salary_type'  => 'Per Suit',
                'is_active'    => (bool) ($user->is_active ?? true),
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            DB::table('orders')->where('tailor_id', $user->id)->update(['staff_id' => $staffId]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'staff_id')) {
                $table->dropConstrainedForeignId('staff_id');
            }
        });

        Schema::dropIfExists('staff_work_logs');
        Schema::dropIfExists('staff_payments');
        Schema::dropIfExists('staff');
    }
};
