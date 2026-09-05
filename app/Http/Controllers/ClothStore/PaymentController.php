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
            'cs_customer_id'=>'required|exists:cs_customers,id', 'amount'=>'required|numeric|min:0.01|decimal:0,2',
            'payment_method'=>['required', \Illuminate\Validation\Rule::in(\App\Services\ClothStore\FinanceService::METHODS)],
            'reference'=>'nullable|string|max:100', 'notes'=>'nullable|string|max:1000', 'operation_key'=>'nullable|string|max:100',
        ]);
        app(\App\Services\ClothStore\FinanceService::class)->collect((int)$data['cs_customer_id'],$data);
        return response()->json(['success'=>true,'message'=>'Payment recorded successfully!',
            'due_balance'=>Customer::findOrFail($data['cs_customer_id'])->due_balance]);
    }

    public function reverse(Request $request, CustomerPayment $payment)
    {
        app(\App\Services\ClothStore\FinanceService::class)->reverse($payment->id);
        return response()->json(['success'=>true,'message'=>'Transaction successfully reversed.']);
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
