<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClothStore\ReturnOrder;
use App\Models\ClothStore\ReturnItem;
use App\Models\ClothStore\Order;
use App\Models\ClothStore\Product;
use App\Models\ClothStore\Customer;
use App\Models\ClothStore\CustomerLedger;
use App\Models\ClothStore\StockTransaction;
use Illuminate\Support\Facades\DB;

class ReturnController extends Controller
{
    public function index(Request $request)
    {
        $all = ReturnOrder::all();
        $totalReturns = $all->count();
        $pending = $all->where('status', 'Pending')->count();
        $totalRefunded = $all->whereIn('status', ['Approved', 'Completed'])->sum('total_refund_amount');
        
        $exchangesCount = ReturnItem::where('action_type', 'Exchange')
            ->whereHas('returnOrder', function($q) {
                $q->whereIn('status', ['Approved', 'Completed']);
            })->count();

        $query = ReturnOrder::with(['customer', 'order', 'items.product'])->orderByDesc('created_at');
        
        if ($request->status) {
            $query->where('status', $request->status);
        }

        // Return number, originating invoice, or customer name/phone. The page
        // previously filtered rendered rows in the browser, so it could only
        // match returns already on the current page.
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhereHas('order', fn ($oq) => $oq->where('invoice_number', 'like', "%{$search}%"))
                  ->orWhereHas('customer', fn ($cq) => $cq
                      ->where('name', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%"));
            });
        }

        $returns = $query->paginate(20)->withQueryString();

        return view('cloth-store.returns.index', compact(
            'returns', 'totalReturns', 'pending', 'totalRefunded', 'exchangesCount'
        ));
    }

    public function searchOrder(Request $request)
    {
        $order = Order::with(['items.product', 'customer'])
            ->where('invoice_number', $request->invoice_number)
            ->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.']);
        }

        return response()->json([
            'success' => true,
            'order' => $order
        ]);
    }

    /** Registered by the returns resource route; was missing entirely. */
    public function show($id)
    {
        $return = ReturnOrder::with(['customer', 'order', 'items.product'])->findOrFail($id);

        return response()->json(['success' => true, 'return' => $return]);
    }

    /** Registered by the returns resource route; was missing entirely. */
    public function update(Request $request, $id)
    {
        $return = ReturnOrder::findOrFail($id);

        if (in_array($return->status, ['Approved', 'Completed'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'A processed return can no longer be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        $return->update($validated);

        return response()->json(['success' => true, 'message' => 'Return updated.']);
    }

    public function store(Request $request)
    {
        $data=$request->validate([
            'order_id'=>'required|exists:cs_orders,id','items'=>'required|array|min:1',
            'items.*.order_item_id'=>'required|distinct|exists:cs_order_items,id','items.*.quantity'=>'required|numeric|min:0.01|decimal:0,2',
            'items.*.reason'=>'required|string|max:255','items.*.action_type'=>'required|in:Refund,Exchange',
            'items.*.exchange_product_id'=>'nullable|exists:cs_products,id','notes'=>'nullable|string|max:1000',
        ]);
        $return=app(\App\Services\ClothStore\ReturnService::class)->create($data);
        return response()->json(['success'=>true,'message'=>'Return request created successfully.','refund'=>$return->total_refund_amount]);
    }

    public function updateStatus(Request $request, $id)
    {
        $data=$request->validate(['status'=>'required|in:Pending,Approved,Rejected,Completed']);
        app(\App\Services\ClothStore\ReturnService::class)->transition((int)$id,$data['status']);
        return $request->expectsJson() ? response()->json(['success'=>true,'message'=>'Return status updated successfully.'])
            : back()->with('success','Return status updated successfully.');
    }
}
