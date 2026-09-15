<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Staff;
use App\Models\StaffPayment;
use App\Models\StaffWorkLog;
use App\Services\ActivityLogger;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $staff = $this->collection();
        $stats = $this->stats();

        $roles       = Staff::ROLES;
        $salaryTypes = Staff::SALARY_TYPES;
        $methods     = StaffPayment::METHODS;
        
        $garments = \App\Models\ProductService::where('type', 'Garment')->where('status', 'Active')->get(['id', 'name']);

        // Same payload as JSON, so the page refreshes itself without a reload.
        if ($request->boolean('json') || $request->expectsJson()) {
            return response()->json(compact('staff', 'stats'));
        }

        return view('staff.index', compact('staff', 'stats', 'roles', 'salaryTypes', 'methods', 'garments'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $specialRates = $data['special_rates'] ?? [];
        unset($data['special_rates']);

        $member = Staff::create($data);
        foreach ($specialRates as $rate) {
            if (isset($rate['product_service_id']) && isset($rate['rate'])) {
                $member->serviceRates()->create([
                    'product_service_id' => $rate['product_service_id'],
                    'rate' => $rate['rate']
                ]);
            }
        }

        ActivityLogger::created($member, "{$member->name} added as a tailor", 'staff');
        StatsService::flush();

        return response()->json([
            'success' => true,
            'message' => "{$member->name} added.",
            'staff'   => $this->serialize($this->fresh($member->id)),
        ]);
    }

    public function update(Request $request, Staff $staff): JsonResponse
    {
        $data = $this->validated($request, $staff);
        $specialRates = $data['special_rates'] ?? [];
        unset($data['special_rates']);

        $staff->update($data);

        $staff->serviceRates()->delete();
        foreach ($specialRates as $rate) {
            if (isset($rate['product_service_id']) && isset($rate['rate'])) {
                $staff->serviceRates()->create([
                    'product_service_id' => $rate['product_service_id'],
                    'rate' => $rate['rate']
                ]);
            }
        }

        ActivityLogger::updated($staff, "{$staff->name} updated", 'staff');
        StatsService::flush();

        return response()->json([
            'success' => true,
            'message' => "{$staff->name} updated.",
            'staff'   => $this->serialize($this->fresh($staff->id)),
        ]);
    }

    /**
     * Turn a staff member on or off without touching their history.
     *
     * Deactivating is the normal case: someone leaves, and the shop still needs
     * last year's payslips and completed work to add up.
     */
    public function toggleActive(Staff $staff): JsonResponse
    {
        $staff->forceFill(['is_active' => !$staff->is_active])->save();

        ActivityLogger::updated(
            $staff,
            sprintf('%s marked %s', $staff->name, $staff->is_active ? 'active' : 'inactive'),
            'staff'
        );
        StatsService::flush();

        return response()->json([
            'success' => true,
            'message' => "{$staff->name} is now " . ($staff->is_active ? 'active' : 'inactive') . '.',
            'staff'   => $this->serialize($this->fresh($staff->id)),
        ]);
    }

    /**
     * Delete outright — refused once there is anything worth keeping.
     *
     * A staff member with payments or completed work is part of the shop's
     * books. Removing them would leave orders pointing at nobody and last
     * month's wage total quietly wrong, so the answer is "deactivate instead".
     */
    public function destroy(Staff $staff): JsonResponse
    {
        $payments = $staff->paymentHistory()->count();
        $work     = $staff->workLogs()->count();

        if ($payments > 0 || $work > 0) {
            return response()->json([
                'success' => false,
                'message' => sprintf(
                    '%s has %d payment(s) and %d completed job(s) on record. Mark them inactive instead so the history stays intact.',
                    $staff->name,
                    $payments,
                    $work
                ),
            ], 422);
        }

        $name = $staff->name;
        $staff->delete();

        ActivityLogger::log('Deleted Tailor', "{$name} removed from the tailor directory", 'staff', null, [], 'deleted');
        StatsService::flush();

        return response()->json(['success' => true, 'message' => "{$name} removed."]);
    }

    /**
     * Everything the profile drawer shows, in one request.
     */
    public function show(Request $request, Staff $staff): JsonResponse
    {
        $period = $request->string('period')->toString() ?: \App\Services\StaffPayPeriod::current($staff->payment_period ?? 'Monthly');

        $staff->load([
            'paymentHistory.recorder:id,name',
            'advanceHistory.recorder:id,name',
            'advanceHistory.reverser:id,name',
            'workLogs.order:id,order_number,garment',
            'serviceRates',
        ]);

        $orders = Order::query()
            ->where('staff_id', $staff->id)
            ->with(['customer:id,name', 'lineItems'])
            ->latest()
            ->limit(50)
            ->get();

        $summary = $this->serialize($this->fresh($staff->id));
        return response()->json([
            'success' => true,
            'staff'   => $summary,
            'due'     => $period === $summary['due']['period'] ? $summary['due'] : $staff->dueFor($period),
            'payments' => $staff->paymentHistory->map(fn (StaffPayment $p) => [
                'id'     => $p->id,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'period' => $p->period,
                'date'   => $p->paid_on?->format('d M Y'),
                'status' => $p->reversed_at ? 'Reversed' : $p->status,
                'can_reverse' => !$p->reversed_at && !$p->reverses_payment_id,
                'summary' => $p->earnings_snapshot,
                'notes'  => $p->notes,
                'by'     => $p->recorder?->name,
            ]),
            'advances' => $staff->advanceHistory->map(fn (\App\Models\StaffAdvance $a) => [
                'id'     => $a->id,
                'amount' => (float) $a->amount,
                'method' => $a->method,
                'date'   => $a->given_on?->format('d M Y'),
                'status' => $a->reversed_at ? 'Reversed' : 'Active',
                'can_reverse' => !$a->reversed_at,
                'notes'  => $a->notes,
                'by'     => $a->recorder?->name,
                'reversed_by' => $a->reverser?->name,
            ]),
            'work' => $staff->workLogs->map(fn (StaffWorkLog $w) => [
                'id'       => $w->id,
                'order'    => $w->order?->display_number,
                'garment'  => $w->garment ?: $w->order?->garment,
                'quantity' => (float) $w->quantity,
                'rate'     => (float) $w->rate,
                'amount'   => (float) $w->amount,
                'date'     => $w->completed_on?->format('d M Y'),
                'notes'    => $w->notes,
            ]),
            'orders' => $orders->map(fn (Order $o) => [
                'id'       => $o->display_number,
                'customer' => $o->customer?->name ?? 'Unknown',
                'garment'  => $o->primary_item_name,
                'status'   => $o->status,
                'due'      => $o->due_label,
                'amount'   => (float) $o->total,
            ]),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Salary                                                              */
    /* ------------------------------------------------------------------ */

    public function storePayment(Request $request, Staff $staff): JsonResponse
    {
        $validated = $request->validate([
            'amount'  => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'method'  => ['required', Rule::in(StaffPayment::METHODS)],
            'period'  => ['nullable', 'string', 'max:10'],
            'paid_on' => ['nullable', 'date'],
            'notes'   => ['nullable', 'string', 'max:500'],
            'operation_key' => ['required', 'string', 'max:100'],
        ]);

        $period = $validated['period'] ?? \App\Services\StaffPayPeriod::current($staff->payment_period ?? 'Monthly');
        \App\Services\StaffPayPeriod::bounds($period);

        $payment = app(\App\Services\StaffPayroll::class)->pay($staff, $validated, $period);

        ActivityLogger::log(
            'Salary paid',
            sprintf('%s paid to %s for %s', \App\Services\Money::format((float) $payment->amount), $staff->name, $period),
            'staff',
            $staff,
            ['amount' => (float) $payment->amount, 'period' => $period],
            'payment'
        );
        StatsService::flush();

        return response()->json([
            'success' => true,
            'message' => sprintf('%s recorded for %s.', \App\Services\Money::format((float) $payment->amount), $staff->name),
            'due'     => $staff->dueFor($period),
            'staff'   => $this->serialize($this->fresh($staff->id)),
        ]);
    }

    public function destroyPayment(StaffPayment $payment): JsonResponse
    {
        $staff = $payment->staff;
        DB::transaction(function () use ($payment) {
            Staff::whereKey($payment->staff_id)->lockForUpdate()->firstOrFail();
            $payment=StaffPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_if($payment->reversed_at || $payment->reverses_payment_id,422,'This transaction cannot be reversed.');
            $payment->update(['reversed_at'=>now(),'reversed_by'=>Auth::id()]);
            StaffPayment::create([
                'staff_id'=>$payment->staff_id,
                'amount'=>$payment->amount,
                'method'=>$payment->method,
                'period'=>$payment->period,
                'earnings_snapshot'=>$payment->earnings_snapshot,
                'paid_on'=>now()->toDateString(),
                'status'=>'Reversal',
                'notes'=>'Reversal of staff payment #'.$payment->id,
                'recorded_by'=>Auth::id(),
                'reverses_payment_id'=>$payment->id,
                'operation_key'=>'staff-payment-reversal-'.$payment->id,
            ]);
        });

        ActivityLogger::log('Salary payment reversed', "Payment reversed for {$staff?->name}", 'staff', $staff, ['payment_id'=>$payment->id], 'reversed');
        StatsService::flush();

        return response()->json([
            'success' => true,
            'message' => 'Payment reversed; the original entry was retained.',
            'staff'   => $staff ? $this->serialize($this->fresh($staff->id)) : null,
        ]);
    }

    public function storeAdvance(Request $request, Staff $staff): JsonResponse
    {
        $validated = $request->validate([
            'amount'  => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'method'  => ['required', Rule::in(StaffPayment::METHODS)],
            'given_on' => ['nullable', 'date'],
            'notes'   => ['nullable', 'string', 'max:500'],
        ]);

        $advance = DB::transaction(function () use ($staff, $validated) {
            $staff = Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            return $staff->advances()->create([
                'amount' => $validated['amount'],
                'method' => $validated['method'],
                'given_on' => $validated['given_on'] ?? now()->toDateString(),
                'notes' => $validated['notes'] ?? null,
                'recorded_by' => Auth::id(),
            ]);
        });

        ActivityLogger::log(
            'Advance given',
            sprintf('%s advance given to %s', \App\Services\Money::format((float) $advance->amount), $staff->name),
            'staff',
            $staff,
            ['amount' => (float) $advance->amount],
            'payment'
        );
        StatsService::flush();

        return response()->json([
            'success' => true,
            'message' => sprintf('Advance of %s recorded for %s.', \App\Services\Money::format((float) $advance->amount), $staff->name),
            'staff'   => $this->serialize($this->fresh($staff->id)),
        ]);
    }

    public function destroyAdvance(\App\Models\StaffAdvance $advance): JsonResponse
    {
        $staff = $advance->staff;
        DB::transaction(function () use ($advance) {
            Staff::whereKey($advance->staff_id)->lockForUpdate()->firstOrFail();
            $advance = \App\Models\StaffAdvance::whereKey($advance->id)->lockForUpdate()->firstOrFail();
            abort_if($advance->reversed_at, 422, 'This advance is already reversed.');
            $advance->update(['reversed_at' => now(), 'reversed_by' => Auth::id()]);
        });

        \App\Services\ActivityLogger::log('Advance reversed', "Advance reversed for {$staff?->name}", 'staff', $staff, ['advance_id'=>$advance->id], 'reversed');
        \App\Services\StatsService::flush();

        return response()->json([
            'success' => true,
            'message' => 'Advance reversed successfully.',
            'staff'   => $staff ? $this->serialize($this->fresh($staff->id)) : null,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Stitching work                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Record finished stitching by hand, for work that did not come through an
     * order — alterations, repairs, a rush job taken at the counter.
     */
    public function storeWork(Request $request, Staff $staff): JsonResponse
    {
        $validated = $request->validate([
            'garment'      => ['nullable', 'string', 'max:255'],
            'quantity'     => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999.99'],
            'rate'         => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'completed_on' => ['nullable', 'date'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $log = DB::transaction(function () use ($staff, $validated) {
            // Falls back to this tailor's own rate, never a shop-wide default.
            $staff = Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $rate = (float) ($validated['rate'] ?? $staff->per_suit_rate);
            $qty  = (float) $validated['quantity'];

            if (round($qty * $rate, 2) > 99999999.99) {
                throw \Illuminate\Validation\ValidationException::withMessages(['quantity' => 'The work total exceeds the supported amount.']);
            }
            return StaffWorkLog::create([
                'staff_id'     => $staff->id,
                'garment'      => $validated['garment'] ?? null,
                'quantity'     => $qty,
                'rate'         => $rate,
                'amount'       => round($qty * $rate, 2),
                'completed_on' => $validated['completed_on'] ?? now()->toDateString(),
                'notes'        => $validated['notes'] ?? null,
            ]);
        });
        $qty = (float) $log->quantity;

        ActivityLogger::log(
            'Stitching recorded',
            sprintf('%s × %s for %s', $qty, $validated['garment'] ?? 'garment', $staff->name),
            'staff',
            $staff,
            ['amount' => (float) $log->amount],
            'created'
        );
        StatsService::flush();

        return response()->json([
            'success' => true,
            'message' => sprintf('%s recorded for %s.', \App\Services\Money::format((float) $log->amount), $staff->name),
            'staff'   => $this->serialize($this->fresh($staff->id)),
        ]);
    }

    public function destroyWork(StaffWorkLog $work): JsonResponse
    {
        $staff = $work->staff;
        DB::transaction(function () use ($staff, $work) {
            Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            abort_if($work->order_id, 422, 'Order completion records must be retained.');
            abort_if($staff->paymentHistory()->exists(), 422, 'Paid work history must be retained.');
            $work->delete();
        });

        StatsService::flush();

        return response()->json([
            'success' => true,
            'message' => 'Work entry removed.',
            'staff'   => $staff ? $this->serialize($this->fresh($staff->id)) : null,
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Staff $staff = null): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'phone'          => ['nullable', 'string', 'max:50'],
            'address'        => ['nullable', 'string', 'max:500'],
            'joining_date'   => ['nullable', 'date'],
            'role'           => ['required', Rule::in(Staff::ROLES)],
            'salary_type'    => ['required', Rule::in(Staff::SALARY_TYPES)],
            'payment_period' => ['sometimes', 'required', Rule::in(['Daily', 'Weekly', 'Monthly'])],
            'monthly_salary' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'per_suit_rate'  => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'is_active'      => ['nullable', 'boolean'],
            'notes'          => ['nullable', 'string', 'max:1000'],
            'special_rates'  => ['nullable', 'array'],
            'special_rates.*.product_service_id' => ['required_with:special_rates', 'exists:product_services,id'],
            'special_rates.*.rate' => ['required_with:special_rates', 'numeric', 'min:0'],
        ]);
    }

    private function fresh(int $id): Staff
    {
        return $this->baseQuery()->findOrFail($id);
    }

    private function baseQuery()
    {
        return Staff::query()
            ->with('serviceRates')
            ->withSum('workLogs as work_logs_sum_amount', 'amount')
            ->withSum('workLogs as work_logs_sum_quantity', 'quantity')
            ->withSum(['workLogs as week_pieces' => fn ($q) => $q->whereBetween('completed_on', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])], 'quantity')
            ->withSum(['workLogs as month_pieces' => fn ($q) => $q->whereYear('completed_on', now()->year)->whereMonth('completed_on', now()->month)], 'quantity')
            ->withSum('payments as payments_sum_amount', 'amount')
            ->withCount([
                'orders as assigned_count' => fn ($q) => $q->whereNotIn('status', ['Ready', 'Delivered', 'Completed', 'Cancelled']),
                'orders as completed_count' => fn ($q) => $q->whereIn('status', ['Ready', 'Delivered', 'Completed']),
            ]);
    }

    private function collection()
    {
        return $this->baseQuery()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Staff $s) => $this->serialize($s));
    }

    /** @return array<string, mixed> */
    private function serialize(Staff $s): array
    {
        $due = $s->dueFor();

        return [
            'db_id'        => $s->id,
            'name'         => $s->name,
            'initials'     => $s->initials,
            'phone'        => $s->phone ?? '',
            'address'      => $s->address ?? '',
            'joining_date' => $s->joining_date?->toDateString(),
            'joined'       => $s->joining_date ? $s->joining_date->format('d M Y') : '—',
            'role'         => $s->role,
            'salary_type'  => $s->salary_type,
            'payment_period' => $s->payment_period ?? 'Monthly',
            'monthly_salary' => (float) $s->monthly_salary,
            'per_suit_rate'  => (float) $s->per_suit_rate,
            'is_active'    => (bool) $s->is_active,
            'notes'        => $s->notes ?? '',
            'special_rates' => $s->serviceRates ? $s->serviceRates->map(fn ($r) => [
                'product_service_id' => $r->product_service_id,
                'rate' => (float) $r->rate,
            ])->toArray() : [],

            'pieces'       => (float) ($s->work_logs_sum_quantity ?? 0),
            'week_pieces'  => (float) ($s->week_pieces ?? 0),
            'month_pieces' => (float) ($s->month_pieces ?? 0),
            'work_amount'  => (float) ($s->work_logs_sum_amount ?? 0),
            'paid_total'   => (float) ($s->payments_sum_amount ?? 0),
            'assigned'     => (int) ($s->assigned_count ?? 0),
            'completed'    => (int) ($s->completed_count ?? 0),

            'due'          => $due,
        ];
    }

    /** @return array<string, mixed> */
    private function stats(): array
    {
        $all    = Staff::query()->get();
        $active = $all->where('is_active', true);

        // Monthly commitment counts only the people actually on a monthly wage.
        $monthlyBill = $active
            ->whereIn('salary_type', ['Monthly', 'Both'])
            ->sum(fn (Staff $s) => (float) $s->monthly_salary);

        $pending = $active->sum(fn (Staff $s) => $s->dueFor()['remaining']);

        return [
            'total'        => $all->count(),
            'active'       => $active->count(),
            'inactive'     => $all->count() - $active->count(),
            'monthly_bill' => round((float) $monthlyBill, 2),
            'pending'      => round((float) $pending, 2),
            'week_pieces'  => (float) StaffWorkLog::whereBetween('completed_on', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->sum('quantity'),
            'month_pieces' => (float) StaffWorkLog::whereYear('completed_on', now()->year)->whereMonth('completed_on', now()->month)->sum('quantity'),
            'pieces'       => (float) StaffWorkLog::sum('quantity'),
            'work_amount'  => (float) StaffWorkLog::sum('amount'),
        ];
    }
}
