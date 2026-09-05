<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClothStore\CustomerPayment;
use App\Models\ClothStore\CustomerLedger;
use App\Models\ClothStore\Customer;
use App\Models\ClothStore\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = CustomerPayment::with('customer');

        // Filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($c) use ($search) {
                      $c->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('payment_date', [$request->start_date, $request->end_date]);
        }
        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $query->where('payment_method', $request->payment_method);
        }

        $payments = $query->orderByDesc('payment_date')->orderByDesc('id')->paginate(25)->withQueryString();
        $customers = Customer::orderBy('name')->get();

        // Analytics
        $today = Carbon::today();
        
        $kpis = [
            'today_cash' => CustomerPayment::where('payment_date', $today)->where('status', 'Completed')->where('payment_method', 'Cash')->sum('amount'),
            'today_digital' => CustomerPayment::where('payment_date', $today)->where('status', 'Completed')->where('payment_method', '!=', 'Cash')->sum('amount'),
            'today_total' => CustomerPayment::where('payment_date', $today)->where('status', 'Completed')->sum('amount'),
            'today_refunds' => CustomerPayment::where('payment_date', $today)->where('status', 'Completed')->where('payment_type', 'Refund')->sum('amount'),
            'outstanding_dues' => Customer::sum('due_balance')
        ];

        return view('cloth-store.payments.index', compact('payments', 'kpis', 'customers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cs_customer_id' => 'required|exists:cs_customers,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // lockForUpdate: two cashiers collecting from the same customer at
            // once would otherwise both read the old balance and one payment
            // would be lost from the running total.
            $customer = Customer::whereKey($data['cs_customer_id'])->lockForUpdate()->firstOrFail();
            $date = Carbon::now();

            $amount = round((float) $data['amount'], 2);
            $due = round((float) $customer->due_balance, 2);

            // Collecting more than is owed used to push due_balance negative,
            // silently turning an over-payment into store credit nobody
            // recorded. Refuse it and say what is actually outstanding.
            if ($amount > $due) {
                throw new \Exception(
                    'Payment exceeds the outstanding balance of Rs ' . number_format($due, 2) . '.'
                );
            }

            // 1. Create Payment Record
            $payment = CustomerPayment::create([
                'cs_customer_id' => $customer->id,
                'amount' => $amount,
                'payment_type' => 'Due Payment',
                'payment_method' => $data['payment_method'],
                'reference' => $data['reference'],
                'notes' => $data['notes'],
                'payment_date' => $date,
                'received_by' => auth()->user()?->name ?? 'Admin',
                'status' => 'Completed'
            ]);

            // 2. Reduce Customer Due Balance
            $customer->due_balance = round($due - $amount, 2);
            $customer->save();

            // 3. Log to Ledger (Credit decreases due balance)
            CustomerLedger::create([
                'cs_customer_id' => $customer->id,
                'date' => $date,
                'type' => 'Payment',
                'reference' => $payment->reference ?? 'PAY-' . $payment->id,
                'description' => 'Due Payment via ' . $data['payment_method'],
                'debit' => 0,
                'credit' => $amount,
                'balance' => $customer->due_balance
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment recorded successfully!',
                'due_balance' => $customer->due_balance,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // 422 so the client's error handler surfaces the message instead
            // of treating a rejected payment as a success.
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function reverse(Request $request, CustomerPayment $payment)
    {
        if ($payment->status === 'Reversed') {
            return response()->json(['success' => false, 'message' => 'Transaction is already reversed.']);
        }

        try {
            DB::beginTransaction();

            $customer = $payment->customer;
            
            // 1. Mark as Reversed
            $payment->update(['status' => 'Reversed', 'notes' => $payment->notes . ' | Reversed by Admin']);

            // 2. Add Amount back to Due Balance
            $customer->due_balance += $payment->amount;
            $customer->save();

            // 3. Log Reversal in Ledger (Debit increases due balance)
            CustomerLedger::create([
                'cs_customer_id' => $customer->id,
                'date' => Carbon::now(),
                'type' => 'Payment Reversal',
                'reference' => 'REV-' . $payment->id,
                'description' => 'Reversal of Payment: ' . ($payment->reference ?? $payment->id),
                'debit' => $payment->amount,
                'credit' => 0,
                'balance' => $customer->due_balance
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Transaction successfully reversed.']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getCustomerDetails($id)
    {
        $customer = Customer::findOrFail($id);
        
        // Fetch recent unpaid or partially paid orders if order tracking exists
        $invoices = [];
        if (class_exists(Order::class)) {
            try {
                $invoices = Order::where('cs_customer_id', $id)
                                 ->where('payment_status', '!=', 'Paid')
                                 ->orderByDesc('id')
                                 ->take(5)
                                 ->get(['id', 'invoice_number', 'total_amount', 'paid_amount', 'payment_status', 'created_at']);
            } catch (\Exception $e) {
                $invoices = [];
            }
        }

        return response()->json([
            'success' => true, 
            'customer' => $customer,
            'recent_invoices' => $invoices
        ]);
    }
}
