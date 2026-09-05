<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClothStore\Customer;
use App\Models\ClothStore\CustomerLedger;
use App\Models\ClothStore\Order;
use App\Models\ClothStore\CustomerPayment;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        // Name or phone — the two things a shopkeeper has to hand.
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Customers who owe money — the list most often needed.
        if ($request->boolean('with_dues')) {
            $query->where('due_balance', '>', 0);
        }

        $customers = $query->orderBy('name')->paginate(20)->withQueryString();

        // Aggregated across the whole directory, so filtering the list does
        // not make the shop's total receivables appear to shrink.
        $stats = [
            'total'      => Customer::count(),
            'with_dues'  => Customer::where('due_balance', '>', 0)->count(),
            'receivable' => (float) Customer::where('due_balance', '>', 0)->sum('due_balance'),
            'new_month'  => Customer::where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return view('cloth-store.customers.index', compact('customers', 'stats'));
    }

    public function ledger(Request $request, $id = null)
    {
        $customers = Customer::orderBy('name')->get();
        $selectedCustomer = null;
        $ledgers = collect();
        $summary = [
            'total_purchases' => 0,
            'total_paid' => 0,
            'total_due' => 0,
            'total_returns' => 0,
        ];

        // If a customer is searched via form
        if ($request->has('cs_customer_id') && $request->cs_customer_id) {
            $id = $request->cs_customer_id;
        }

        if ($id) {
            $selectedCustomer = Customer::findOrFail($id);
            
            // Build Summary manually or via aggregations if they exist
            // Using existing cs_orders if available, else sum ledgers
            if (\Schema::hasTable('cs_orders')) {
                $summary['total_purchases'] = Order::where('cs_customer_id', $id)->sum('total_amount');
                $summary['total_returns'] = Order::where('cs_customer_id', $id)->where('status', 'Returned')->sum('total_amount');
            }
            
            $summary['total_paid'] = CustomerPayment::where('cs_customer_id', $id)->where('status', 'Completed')->sum('amount');
            $summary['total_due'] = $selectedCustomer->due_balance;

            // Date filtering
            $query = CustomerLedger::where('cs_customer_id', $id);
            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('date', [$request->start_date, $request->end_date]);
            }

            $ledgers = $query->orderBy('date', 'asc')->orderBy('id', 'asc')->get();
        }

        return view('cloth-store.customers.ledger', compact('customers', 'selectedCustomer', 'ledgers', 'summary'));
    }
    /**
     * Shared rules for store(), update() and storeQuick().
     *
     * Phone is unique because it is the field the POS looks a customer up by —
     * two records with the same number make the till ambiguous and split one
     * person's ledger across two accounts.
     */
    private function rules(?Customer $customer = null): array
    {
        return [
            'name'  => 'required|string|max:255',
            'phone' => [
                'nullable', 'string', 'max:30',
                Rule::unique('cs_customers', 'phone')->ignore($customer?->id),
            ],
            'city'    => 'nullable|string|max:100',
            'notes'   => 'nullable|string|max:1000',
            'customer_level' => 'nullable|in:New,Regular,VIP,Wholesale',
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate(
            $this->rules(),
            ['phone.unique' => 'A customer with this phone number already exists.']
        );

        $customer = Customer::create($validated + ['due_balance' => 0]);

        return response()->json([
            'success'  => true,
            'customer' => $customer,
            'message'  => 'Customer created successfully.',
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate(
            $this->rules($customer),
            ['phone.unique' => 'A customer with this phone number already exists.']
        );

        // due_balance is derived from sales and payments — never accept it
        // from a form, or editing a customer would silently rewrite their debt.
        $customer->update($validated);

        return response()->json([
            'success'  => true,
            'customer' => $customer->fresh(),
            'message'  => 'Customer updated successfully.',
        ]);
    }

    public function destroy(Customer $customer)
    {
        // Deleting a customer with history would cascade away their orders,
        // payments and ledger, silently rewriting past revenue.
        if ($customer->orders()->exists() || $customer->payments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => "{$customer->name} has sales or payment history and cannot be deleted.",
            ], 422);
        }

        if ((float) $customer->due_balance > 0) {
            return response()->json([
                'success' => false,
                'message' => "{$customer->name} still owes Rs " . number_format((float) $customer->due_balance) . ' and cannot be deleted.',
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully.',
        ]);
    }

    /** Quick-add from the till — same rules, fewer fields on screen. */
    public function storeQuick(Request $request)
    {
        $validated = $request->validate(
            $this->rules(),
            ['phone.unique' => 'A customer with this phone number already exists.']
        );

        $customer = Customer::create($validated + ['due_balance' => 0]);

        return response()->json([
            'success'  => true,
            'customer' => $customer,
            'message'  => 'Customer created successfully.',
        ]);
    }
}
