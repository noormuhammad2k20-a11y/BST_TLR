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
    private const STATUSES = [
        'Pending', 'Processing', 'Ready', 'Completed', 'Cancelled', 'Returned',
    ];

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
        $sold = Order::where('status', '!=', 'Cancelled');

        $stats = [
            'today_sales'  => (float) (clone $sold)->whereDate('created_at', today())->sum('total_amount'),
            'today_count'  => (clone $sold)->whereDate('created_at', today())->count(),
            'today_meters' => (float) (clone $sold)->whereDate('created_at', today())->sum('total_meters_sold'),
            'outstanding'  => (float) (clone $sold)->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) AS d')->value('d'),
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
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
        ]);

        $oldStatus = $order->status;
        $newStatus = $validated['status'];

        if ($oldStatus === $newStatus) {
            return response()->json([
                'success' => true,
                'status'  => $newStatus,
                'message' => "Order {$order->invoice_number} is already {$newStatus}.",
            ]);
        }

        $isCancelling  = $newStatus === 'Cancelled' && $oldStatus !== 'Cancelled';
        $isRestoring   = $oldStatus === 'Cancelled' && $newStatus !== 'Cancelled';

        DB::beginTransaction();
        try {
            $order->load('items');

            if ($isCancelling || $isRestoring) {
                foreach ($order->items as $item) {
                    $product = Product::whereKey($item->cs_product_id)->lockForUpdate()->first();
                    if (!$product) {
                        continue;
                    }

                    $qty = round((float) $item->quantity, 2);
                    $previousQty = round((float) $product->stock_quantity, 2);

                    if ($isCancelling) {
                        $newQty = round($previousQty + $qty, 2);
                        $type = 'in';
                        $reason = 'Sale Cancelled';
                    } else {
                        // Re-activating a cancelled order must not invent stock
                        // that has since been sold to someone else.
                        if ($previousQty < $qty) {
                            throw new \Exception(
                                "Cannot reinstate this order — only {$previousQty} {$product->unit} of {$product->name} remains, but the order needs {$qty}."
                            );
                        }

                        $newQty = round($previousQty - $qty, 2);
                        $type = 'out';
                        $reason = 'Sale Reinstated';
                    }

                    $product->stock_quantity = $newQty;
                    $product->save();

                    StockTransaction::create([
                        'cs_product_id' => $product->id,
                        'user_id'       => auth()->id(),
                        'type'          => $type,
                        'quantity'      => $qty,
                        'reason'        => $reason,
                        'previous_qty'  => $previousQty,
                        'new_qty'       => $newQty,
                        'reference'     => $order->invoice_number,
                        'notes'         => "Order status {$oldStatus} → {$newStatus}",
                    ]);
                }
            }

            $order->update(['status' => $newStatus]);

            DB::commit();

            return response()->json([
                'success' => true,
                'status'  => $newStatus,
                'message' => "Order {$order->invoice_number} updated to {$newStatus}.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function recordPayment(Request $request, Order $order)
    {
        // Handled via Customer Ledger mostly, but stubbed if needed
        return back()->with('info', 'Use the Customer Ledger to record post-sale payments.');
    }
}
