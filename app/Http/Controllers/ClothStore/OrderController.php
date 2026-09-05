<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClothStore\Order;
use App\Models\ClothStore\Product;
use App\Models\ClothStore\StockTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /** Statuses an order may be moved to, whitelisted for validation. */


    public function index(Request $request) 
    {
        $query = Order::with(['customer', 'items'])->orderBy('created_at', 'desc');

        // Filtering
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        if ($request->filled('date')) {
            if ($request->date == 'today') {
                $query->whereDate('created_at', today());
            } elseif ($request->date == 'week') {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            } elseif ($request->date == 'month') {
                $query->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year);
            }
        }

        $orders = $query->paginate(15)->withQueryString();

        // Aggregated over the whole table rather than the current page, so the
        // headline numbers don't shrink when a filter is applied.
        $sold = \App\Services\ClothStore\SalesAnalytics::orders();

        $stats = [
            'today_sales'  => (float) (clone $sold)->whereDate('created_at', today())->sum('total_amount'),
            'today_count'  => (clone $sold)->whereDate('created_at', today())->sum('sale_count'),
            'today_meters' => (float) (clone $sold)->whereDate('created_at', today())->sum('total_meters_sold'),
            'outstanding'  => (float) Order::withTrashed()->sum('remaining_amount'),
            'total_orders' => Order::count(),
        ];

        return view('cloth-store.orders.index', compact('orders', 'stats'));
    }

    public function show(Order $order)
    {
        $order->load(['customer', 'items.product']);
        return response()->json($order);
    }

    /**
     * Change an order's status, moving stock with it.
     *
     * Cancelling returns the fabric to inventory; un-cancelling takes it back
     * out. Both directions are real stock movements, so they run inside one
     * transaction and leave an audit row — previously they were bare saves in
     * a loop, which could half-apply if any product failed mid-way and left
     * nothing in Stock History to explain the change.
     */
    public function updateStatus(Request $request, Order $order)
    {
        $data=$request->validate(['status'=>['required', Rule::in(\App\Enums\ClothStoreOrderStatus::values())]]);
        app(\App\Services\ClothStore\OrderWorkflow::class)->transition($order->id,$data['status']);
        return response()->json(['success'=>true,'status'=>$data['status'],'message'=>'Order updated.']);
    }

    public function recordPayment(Request $request, Order $order)
    {
        $data=$request->validate(['amount'=>'required|numeric|min:0.01|decimal:0,2',
            'payment_method'=>['required',Rule::in(\App\Services\ClothStore\FinanceService::METHODS)],
            'reference'=>'nullable|string|max:100','notes'=>'nullable|string|max:1000','operation_key'=>'nullable|string|max:100']);
        app(\App\Services\ClothStore\FinanceService::class)->collect($order->cs_customer_id,$data,$order->id);
        return response()->json(['success'=>true,'order'=>$order->fresh(),'message'=>'Payment recorded.']);
    }
}
