<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Services\ActivityLogger;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function sendSms(Request $request, Customer $customer): JsonResponse
    {
        $validated = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $result = \App\Services\SmsService::send($customer->phone, $validated['message'], customerId: $customer->id);

        return response()->json([
            'success' => $result['sent'],
            'message' => $result['sent'] ? 'SMS accepted for sending. Delivery is not yet confirmed.' : $result['error'],
        ], $result['sent'] ? 200 : 422);
    }

    public function index()
    {
        $customers = Customer::query()
            ->withCount('orders')
            ->withSum('orders as orders_total', 'total')
            ->withSum('orders as orders_balance', 'balance')
            ->withMax('orders as last_order_at', 'created_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $c) => $this->serialize($c));

        // Every order, pre-grouped by customer code for the 360° drawer.
        $orders = Order::query()
            ->with('customer:id,code')
            ->latest()
            ->get()
            ->map(fn (Order $o) => [
                'db_id'   => $o->id,
                'id'      => $o->display_number,
                'cid'     => $o->customer?->display_code,
                'status'  => $o->status,
                'amount'  => (float) $o->total,
                'balance' => (float) $o->balance,
                'due'     => $o->due_label,
                'garment' => $o->primary_item_name,
                'date'    => $o->created_at->format('M d, Y'),
            ]);

        $stats = $this->stats();

        return view('customers.index', compact('customers', 'orders', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $customer = Customer::create($validated);

        ActivityLogger::created(
            $customer,
            sprintf('%s added to the customer directory', $customer->name),
            'customers'
        );

        $this->flush();

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Customer added successfully!',
                'customer' => $this->serialize($customer->loadCount('orders')),
            ], 201);
        }

        return redirect()->route('customers.index')->with('success', 'Customer added successfully!');
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $this->validated($request, $customer);

        $customer->update($validated);

        ActivityLogger::updated($customer, sprintf('%s details updated', $customer->name), 'customers');

        $this->flush();

        if ($request->expectsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'Customer updated successfully!',
                'customer' => $this->serialize($customer->loadCount('orders')),
            ]);
        }

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully!');
    }

    public function destroy(Request $request, Customer $customer)
    {
        $name = $customer->name;
        $customer->delete();

        ActivityLogger::log('Deleted Customer', "{$name} was removed", 'customers', null, [], 'deleted');
        $this->flush();

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Customer archived; history retained.']);
        }

        return redirect()->route('customers.index')->with('success', 'Customer archived; history retained.');
    }

    /**
     * Full 360° payload: orders, measurements and payments in one round-trip.
     */
    public function summary(Customer $customer): JsonResponse
    {
        $customer->loadCount('orders')
            ->loadSum('orders as orders_total', 'total')
            ->loadSum('orders as orders_balance', 'balance');

        $orders = $customer->orders()->latest()->limit(50)->get();

        return response()->json([
            'success'  => true,
            'customer' => $this->serialize($customer),
            'orders'   => $orders->map(fn (Order $o) => [
                'db_id'   => $o->id,
                'id'      => $o->display_number,
                'garment' => $o->primary_item_name,
                'status'  => $o->status,
                'amount'  => (float) $o->total,
                'balance' => (float) $o->balance,
                'due'     => $o->due_label,
                'date'    => $o->created_at->format('M d, Y'),
            ]),
            'measurements' => $customer->measurements()->latest()->get()->map(fn ($m) => [
                'id'           => $m->id,
                'garment_type' => $m->garment_type,
                'unit'         => $m->unit,
                'tailor'       => $m->tailor,
                'completeness' => $m->completeness,
                'date'         => $m->created_at->format('M d, Y'),
            ]),
            'payments' => $customer->payments()->with('order:id,order_number')->latest('date')->limit(50)->get()
                ->map(fn ($p) => [
                    'amount'  => (float) $p->amount,
                    'method'  => $p->payment_method ?: '—',
                    'invoice' => $p->invoice_id,
                    'date'    => $p->date?->format('M d, Y'),
                    'status'  => $p->status,
                ]),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'phone'   => [
                'required', 'string', 'max:50',
                Rule::unique('customers')->ignore($customer?->id),
            ],
            'email'   => ['nullable', 'email', 'max:255'],
            'city'    => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'type'    => ['nullable', Rule::in(['Regular', 'Premium', 'VIP'])],
            'notes'   => ['nullable', 'string', 'max:2000'],
        ], [
            'phone.unique' => 'A customer with this phone number already exists.',
        ]);
    }

    private function serialize(Customer $c): array
    {
        $spent = (float) ($c->orders_total ?? 0);
        $count = (int) ($c->orders_count ?? 0);

        return [
            'db_id'     => $c->id,
            'id'        => $c->display_code,
            'name'      => $c->name,
            'phone'     => $c->phone,
            'email'     => $c->email,
            'type'      => $c->type ?: 'Regular',
            'city'      => $c->city,
            'address'   => $c->address,
            'orders'    => $count,
            'spent'     => $spent,
            'avg'       => $count > 0 ? round($spent / $count) : 0,
            'due'       => (float) ($c->orders_balance ?? 0),
            'lastVisit' => $c->last_visit_label,
            'since'     => $c->created_at?->format('M Y'),
            'loyalty'   => $c->loyalty,
            'behavior'  => $c->behavior_label,
            'notes'     => $c->notes ?? '',
        ];
    }

    /**
     * Directory-level counters, cached briefly.
     */
    private function stats(): array
    {
        return Cache::remember('customers.stats', 60, function () {
            $total = Customer::count();
            $vip   = Customer::where('type', 'VIP')->count();

            $orderCount = Order::count();
            $orderValue = (float) Order::sum('total');

            return [
                'total'      => $total,
                'vip'        => $vip,
                'vip_share'  => $total > 0 ? round($vip / $total * 100, 1) : 0.0,
                'new_month'  => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
                'avg_order'  => $orderCount > 0 ? round($orderValue / $orderCount) : 0,
                'avg_delta'  => StatsService::delta(
                    (float) Order::where('created_at', '>=', now()->startOfMonth())->avg('total'),
                    (float) Order::whereBetween('created_at', [
                        now()->subMonth()->startOfMonth(),
                        now()->subMonth()->endOfMonth(),
                    ])->avg('total'),
                ),
                'with_dues'  => Customer::whereHas('orders', fn ($q) => $q->where('balance', '>', 0))->count(),
                'by_type'    => [
                    'VIP'     => $vip,
                    'Premium' => Customer::where('type', 'Premium')->count(),
                    'Regular' => Customer::where('type', 'Regular')->count(),
                ],
            ];
        });
    }

    private function flush(): void
    {
        Cache::forget('customers.stats');
        Cache::forget('layout.counters');
        StatsService::flush();
    }
}
