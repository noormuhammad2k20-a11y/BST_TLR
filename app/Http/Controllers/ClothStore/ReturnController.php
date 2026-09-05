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
        $validated = $request->validate([
            'order_id' => 'required|exists:cs_orders,id',
            'items'    => 'required|array|min:1',
            'items.*.order_item_id' => 'required|exists:cs_order_items,id',
            'items.*.quantity'      => 'required|numeric|min:0.01|decimal:0,2',
            'items.*.reason'        => 'required|string|max:255',
            'items.*.action_type'   => 'required|in:Refund,Exchange',
            'items.*.exchange_product_id' => 'nullable|exists:cs_products,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        // items.product is eager-loaded because the guard messages below name
        // the product; without it this would fire a query per line (and trip
        // the app's preventLazyLoading warning).
        $order = Order::with('items.product')->findOrFail($validated['order_id']);

        DB::beginTransaction();
        try {
            $totalRefund = 0;

            $returnOrder = ReturnOrder::create([
                'cs_order_id' => $order->id,
                'cs_customer_id' => $order->cs_customer_id,
                'status' => 'Pending',
                'notes' => $validated['notes'] ?? null,
                'total_refund_amount' => 0,
            ]);

            foreach ($validated['items'] as $item) {
                $qty = round((float) $item['quantity'], 2);
                if ($qty <= 0) {
                    continue;
                }

                // The line must belong to THIS order — otherwise a crafted
                // request could return an item from someone else's invoice.
                $orderItem = $order->items->firstWhere('id', (int) $item['order_item_id']);

                if (!$orderItem) {
                    throw new \Exception('One of the returned items does not belong to this order.');
                }

                // Cannot return more than was sold, less anything already
                // returned on a previous approved/pending return.
                $alreadyReturned = (float) ReturnItem::where('cs_order_item_id', $orderItem->id)
                    ->whereHas('returnOrder', fn ($q) => $q->whereIn('status', ['Pending', 'Approved', 'Completed']))
                    ->sum('quantity');

                $returnable = round((float) $orderItem->quantity - $alreadyReturned, 2);

                if ($qty > $returnable) {
                    $label = $orderItem->product->name ?? 'this item';
                    throw new \Exception(
                        "Cannot return {$qty} of {$label} — only {$returnable} remains returnable on this invoice."
                    );
                }

                // Refund is derived from the price actually charged, never from
                // the client. A posted refund_amount could otherwise be any
                // number the browser chose to send.
                $actionType = $item['action_type'];
                $refundAmt = $actionType === 'Refund'
                    ? round($qty * (float) $orderItem->unit_price, 2)
                    : 0;

                $totalRefund += $refundAmt;

                ReturnItem::create([
                    'cs_return_id'     => $returnOrder->id,
                    'cs_order_item_id' => $orderItem->id,
                    'cs_product_id'    => $orderItem->cs_product_id,
                    'quantity'         => $qty,
                    'reason'           => $item['reason'],
                    'action_type'      => $actionType,
                    'exchange_product_id' => $actionType === 'Exchange' ? ($item['exchange_product_id'] ?? null) : null,
                    'refund_amount'    => $refundAmt,
                ]);
            }

            if ($returnOrder->items()->count() === 0) {
                throw new \Exception('No valid items were supplied for this return.');
            }

            $returnOrder->update(['total_refund_amount' => round($totalRefund, 2)]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Return request created successfully.',
                'refund'  => round($totalRefund, 2),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:Pending,Approved,Rejected,Completed',
        ]);

        $returnOrder = ReturnOrder::with('items')->findOrFail($id);
        $newStatus = $validated['status'];

        // Approve/Reject post over AJAX so the list is not reloaded. Every exit
        // from this method answers JSON when the caller asked for it, and keeps
        // its back() redirect as the no-JS fallback for the plain form.
        if (in_array($returnOrder->status, ['Approved', 'Completed'], true)) {
            if ($request->expectsJson()) {
                return response()->json(
                    ['success' => false, 'message' => 'This return has already been processed.'],
                    422
                );
            }

            return back()->with('error', 'This return has already been processed.');
        }

        DB::beginTransaction();
        try {
            $returnOrder->update(['status' => $newStatus]);

            if (in_array($newStatus, ['Approved', 'Completed'], true)) {

                foreach ($returnOrder->items as $item) {
                    $qty = round((float) $item->quantity, 2);

                    // --- Returned goods come back into stock ---
                    $product = Product::whereKey($item->cs_product_id)->lockForUpdate()->first();

                    if ($product) {
                        $prevQty = round((float) $product->stock_quantity, 2);
                        $newQty  = round($prevQty + $qty, 2);

                        $product->stock_quantity = $newQty;
                        $product->save();

                        StockTransaction::create([
                            'cs_product_id' => $product->id,
                            // cs_stock_transactions.type is an ENUM of
                            // in/out/adjustment/transfer. This used to write
                            // "Stock In" / "Stock Out", which are not valid
                            // enum members — the insert failed and took the
                            // whole approval down with it.
                            'type'         => 'in',
                            'quantity'     => $qty,
                            'previous_qty' => $prevQty,
                            'new_qty'      => $newQty,
                            'reason'       => 'Customer Return',
                            'reference'    => $returnOrder->return_number,
                            'user_id'      => auth()->id(),
                        ]);
                    }

                    // --- Exchange: the replacement leaves stock ---
                    if ($item->action_type === 'Exchange' && $item->exchange_product_id) {
                        $exProduct = Product::whereKey($item->exchange_product_id)->lockForUpdate()->first();

                        if ($exProduct) {
                            $exPrev = round((float) $exProduct->stock_quantity, 2);

                            // Never hand out stock the shop does not have.
                            if ($exPrev < $qty) {
                                throw new \Exception(
                                    "Cannot exchange for {$exProduct->name} — only {$exPrev} {$exProduct->unit} in stock."
                                );
                            }

                            $exNew = round($exPrev - $qty, 2);
                            $exProduct->stock_quantity = $exNew;
                            $exProduct->save();

                            StockTransaction::create([
                                'cs_product_id' => $exProduct->id,
                                'type'         => 'out',
                                'quantity'     => $qty,
                                'previous_qty' => $exPrev,
                                'new_qty'      => $exNew,
                                'reason'       => 'Exchange Issued',
                                'reference'    => $returnOrder->return_number,
                                'user_id'      => auth()->id(),
                            ]);
                        }
                    }
                }

                // --- Refund: credit the customer and move their balance ---
                $refund = round((float) $returnOrder->total_refund_amount, 2);

                if ($refund > 0 && $returnOrder->cs_customer_id) {
                    $customer = Customer::whereKey($returnOrder->cs_customer_id)->lockForUpdate()->first();

                    if ($customer) {
                        // A refund reduces what the customer owes. The old code
                        // wrote a ledger row with columns that do not exist
                        // (transaction_type/amount) and never touched
                        // due_balance at all, so refunds changed nothing.
                        $customer->due_balance = round((float) $customer->due_balance - $refund, 2);
                        $customer->total_purchases = round(max(0, (float) $customer->total_purchases - $refund), 2);
                        $customer->save();

                        CustomerLedger::create([
                            'cs_customer_id' => $customer->id,
                            'date' => now(),
                            'type' => 'Refund',
                            'reference' => $returnOrder->return_number,
                            'description' => 'Refund for return ' . $returnOrder->return_number,
                            'debit' => 0,
                            'credit' => $refund,
                            'balance' => $customer->due_balance,
                        ]);
                    }
                }

                // --- Order status reflects partial vs full return ---
                $order = Order::with('items')->find($returnOrder->cs_order_id);

                if ($order) {
                    $soldQty = (float) $order->items->sum('quantity');

                    $returnedQty = (float) ReturnItem::whereHas(
                        'returnOrder',
                        fn ($q) => $q->where('cs_order_id', $order->id)
                                     ->whereIn('status', ['Approved', 'Completed'])
                    )->sum('quantity');

                    // Marking a partially returned order as "Returned" made
                    // the dashboard treat the whole invoice as refunded.
                    $order->update([
                        'status' => $returnedQty >= $soldQty ? 'Returned' : 'Partially Returned',
                    ]);
                }
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => "Return {$newStatus} successfully.",
                ]);
            }

            return back()->with('success', 'Return status updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json(
                    ['success' => false, 'message' => 'Error processing return: ' . $e->getMessage()],
                    422
                );
            }

            return back()->with('error', 'Error processing return: ' . $e->getMessage());
        }
    }
}
