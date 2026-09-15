<?php
namespace App\Services;

use App\Models\{Staff, StaffPayment};
use Illuminate\Support\Facades\{DB, Auth};

final class StaffPayroll
{
    public function pay(Staff $staff, array $validated, string $period): StaffPayment
    {
        return DB::transaction(function () use ($staff, $validated, $period) {
            $staff = Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            abort_if(StaffPayment::where('operation_key',$validated['operation_key'])->exists(),409,'This payment was already submitted.');
            $due = $staff->dueFor($period);
            if ($period !== 'Running Balance') {
                foreach ($staff->payments()->where('period', '!=', $period)->pluck('period')->unique() as $other) {
                    if ($other === 'Running Balance') continue;
                    [$a, $b] = \App\Services\StaffPayPeriod::bounds($other);
                    if ($a->toDateString() <= $due['period_end'] && $b->toDateString() >= $due['period_start']) {
                        throw \Illuminate\Validation\ValidationException::withMessages(['period' => "This overlaps payments for {$other}. Use that original period for these dates, or reverse its payments before changing the cycle."]);
                    }
                }
            }
            if ((int) round((float) $validated['amount'] * 100) > (int) round($due['remaining'] * 100)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'The payment cannot exceed the remaining earned balance.']);
            }
            $payment = StaffPayment::create([
                'staff_id'    => $staff->id,
                'earnings_snapshot' => $due,
                'amount'      => $validated['amount'],
                'method'      => $validated['method'],
                'period'      => $period,
                'paid_on'     => $validated['paid_on'] ?? now()->toDateString(),
                'notes'       => $validated['notes'] ?? null,
                'recorded_by' => Auth::id(),
                'operation_key' => $validated['operation_key'],
                // Provisional; corrected below once the period's totals are known.
                'status'      => 'Partial',
            ]);

            // The status describes where the *period* stands after this payment,
            // which is only knowable once the row exists.
            $after = $staff->dueFor($period);
            $payment->forceFill(['status' => $after['status'], 'earnings_snapshot' => $after])->save();

            return $payment;
        });
    }
}
