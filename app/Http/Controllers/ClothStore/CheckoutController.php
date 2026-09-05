<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClothStore\Product;
use App\Models\ClothStore\Category;
use App\Models\ClothStore\Customer;
use App\Models\ClothStore\Order;
use App\Models\ClothStore\OrderItem;
use App\Models\ClothStore\CustomerLedger;
use App\Models\ClothStore\CustomerPayment;
use App\Models\ClothStore\StockTransaction;
use App\Models\ClothStore\ProductLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\Decimal as D;
use App\Services\ClothStore\InventoryService;
use App\Services\ClothStore\FinanceService;

class CheckoutController extends Controller
{
    /** Products shown per POS load / search. */
    private const POS_PAGE_SIZE = 24;

    /**
     * Accepted tender types. Whitelisted so a crafted request cannot invent a
     * payment method that then breaks the Payments page filters and reports.
     */
    private const PAYMENT_METHODS = FinanceService::METHODS;

    public function index()
    {
        // Only the first screenful. The POS used to load every active product
        // and every customer into the page and filter them by hiding DOM
        // nodes — which stops being usable at a few hundred products and can
        // never match a product that was not in the initial dump.
        //
        // The limit had been dropped here while POS_PAGE_SIZE and this comment
        // stayed, so the till was silently back to serialising the whole
        // catalogue into the HTML on every load. The client tops its in-memory
        // product map up from every search response, and the scanner injects
        // its own product before adding to the cart, so a bounded seed is all
        // this needs.
        $products = $this->productQuery()->limit(self::POS_PAGE_SIZE)->get();

        $categories = Category::orderBy('name')->get();

        // A short recent list seeds the customer picker; the rest arrive
        // through searchCustomers() as the cashier types.
        $customers = Customer::orderBy('name')->limit(50)->get();

        $discounts = \App\Models\ClothStore\Discount::where('is_active', true)->get()->filter(function ($d) {
            return $d->calculated_status === 'Active';
        });

        return view('cloth-store.checkout.index', compact('products', 'categories', 'customers', 'discounts'));
    }

    /**
     * Live product lookup for the till — name, SKU or barcode.
     *
     * Returns rendered HTML rather than JSON so the grid markup lives in
     * exactly one place (the card partial) instead of being duplicated as a
     * JS template that can drift out of step with the server-rendered view.
     */
    public function searchProducts(Request $request)
    {
        $query = $this->productQuery();

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('cs_category_id', $request->category);
        }

        if ($request->filled('unit') && $request->unit !== 'all') {
            $query->where('unit', $request->unit);
        }

        // Same bound as the initial render — clearing the search box must not
        // turn into a full-catalogue fetch on every keystroke's trailing edge.
        $products = $query->limit(self::POS_PAGE_SIZE)->get();

        return response()->json([
            'count' => $products->count(),
            'html'  => view('cloth-store.checkout.partials.product-cards', compact('products'))->render(),
            'products' => $products->map(fn (Product $p) => [
                'id'    => $p->id,
                'name'  => $p->name,
                'price' => (float) $p->price,
                'stock' => (float) $p->stock_quantity,
                'unit'  => $p->unit,
                'sku'   => $p->sku,
            ])->keyBy('id'),
        ]);
    }

    /**
     * Exact barcode/SKU resolution for a scanner.
     *
     * A scanner types the whole code then presses Enter; that must add one
     * specific product, never a fuzzy match.
     */
    public function scan(Request $request)
    {
        $code = trim((string) $request->get('code'));

        if ($code === '') {
            return response()->json(['success' => false, 'message' => 'No code supplied.'], 422);
        }

        $product = $this->productQuery()
            ->where(fn ($q) => $q->where('barcode', $code)->orWhere('sku', $code))
            ->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => "No in-stock product matches \"{$code}\".",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'product' => [
                'id'    => $product->id,
                'name'  => $product->name,
                'price' => (float) $product->price,
                'stock' => (float) $product->stock_quantity,
                'unit'  => $product->unit,
                'sku'   => $product->sku,
            ],
        ]);
    }

    /** Live customer lookup for the till — name or phone. */
    public function searchCustomers(Request $request)
    {
        $query = Customer::query()->orderBy('name');

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return response()->json($query->limit(30)->get()->map(fn (Customer $c) => [
            'id'    => $c->id,
            'name'  => $c->name,
            'phone' => $c->phone,
            'due'   => (float) $c->due_balance,
        ]));
    }

    /**
     * Base POS product query: sellable stock only, newest first.
     * Shared by the initial render, the search endpoint and the scanner so
     * all three agree on what "available at the till" means.
     */
    private function productQuery()
    {
        return Product::with('category')->where('status', 'Active')->whereColumn('stock_quantity','>','reserved_quantity')
            ->orderBy('name');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cs_customer_id' => 'required|exists:cs_customers,id',
            'items' => 'required|array|min:1',
            'items.*.cs_product_id' => 'required|exists:cs_products,id',
            // Metres, to 2 dp — 5.50 m must survive validation untouched.
            'items.*.quantity' => 'required|numeric|min:0.01|decimal:0,2',
            'discount' => 'nullable|numeric|min:0',
            'paid_amount' => 'required|numeric|min:0',
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
        ]);

        DB::beginTransaction();
        try {
            $customer = Customer::whereKey($validated['cs_customer_id'])->lockForUpdate()->firstOrFail();

            $totalMeters = '0.00';
            $grossProfit = '0.00';
            $subtotal = '0.00';

            /*
             * Money is computed here, never accepted from the browser.
             *
             * The request used to carry unit_price, subtotal and total_amount
             * and they were written straight to the order. A crafted POST
             * could therefore buy a Rs 6,500 product for Rs 1, or record a
             * Rs 0 total against real stock. Prices now come from the product
             * row and the totals are derived from them.
             */
            $lines = [];
            $aggregated=[];
            foreach ($validated['items'] as $item) {
                $id=$item['cs_product_id'];
                $aggregated[$id]=['cs_product_id'=>$id,'quantity'=>D::add($aggregated[$id]['quantity']??'0.00',(string)$item['quantity'])];
            }
            $validated['items']=array_values($aggregated);

            foreach (collect($validated['items'])->sortBy('cs_product_id') as $item) {
                $product = Product::lockForUpdate()->findOrFail($item['cs_product_id']);

                if ($product->status !== 'Active') {
                    throw new \Exception("{$product->name} is no longer available for sale.");
                }

                // round() at every step: these are metres, and un-rounded
                // float drift would otherwise leave 0.0000001 m ghosts in
                // stock that make a product look permanently in-stock.
                $qty = D::value((string)$item['quantity']);
                $unitPrice = D::value((string)$product->price);
                $unitCost = D::value((string)($product->cost_price ?? '0'));

                $previousQty = D::value((string)$product->stock_quantity);

                if (D::cmp(D::sub($previousQty,(string)$product->reserved_quantity),$qty)<0) {
                    throw new \Exception("Insufficient stock for {$product->name}. Only {$previousQty} {$product->unit} available.");
                }

                $itemTotal = D::mul($qty,$unitPrice);
                $itemCost = D::mul($qty,$unitCost);
                $itemProfit = D::sub($itemTotal,$itemCost);

                $subtotal = D::add($subtotal,$itemTotal);
                $lines[] = [$product, $qty, $unitPrice, $unitCost, $itemTotal, $previousQty];
            }

            $subtotal = D::value($subtotal);

            // A discount can never exceed the sale, or the total goes negative
            // and the customer ends up with a credit they never paid for.
            $discount = D::min(D::value((string)($validated['discount'] ?? '0')),$subtotal);
            $totalAmount = D::sub($subtotal,$discount);

            // Overpayment is change, not a credit — cap what is recorded.
            $paidAmount = D::min(D::value((string)$validated['paid_amount']),$totalAmount);

            $order = Order::create([
                // Placeholder — replaced with a readable sequential number
                // below, once the row has an id. uniqid() was producing
                // invoices like INV-6A81BAF1A2397, which nobody can read out
                // over a counter or quote on the phone.
                'invoice_number' => 'TMP-' . uniqid(),
                'cs_customer_id' => $customer->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total_amount' => $totalAmount,
                'gross_profit' => 0,
                'total_meters_sold' => 0,
                'paid_amount' => $paidAmount,
                'remaining_amount' => D::sub($totalAmount,$paidAmount),
                'payment_status' => D::cmp($paidAmount,$totalAmount)===0?'Paid':(D::cmp($paidAmount,'0')>0?'Partial':'Unpaid'),
                'payment_method' => $validated['payment_method'],
                'status' => 'Completed',
            ]);

            // Derived from the primary key, so it is unique by construction
            // and reads as a running invoice sequence: INV-000042.
            $order->invoice_number = 'INV-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
            $order->save();

            foreach ($lines as [$product, $qty, $unitPrice, $unitCost, $itemTotal, $previousQty]) {
                $itemProfit = D::sub($itemTotal,D::mul($qty,$unitCost));

                $orderItem = OrderItem::create([
                    'cs_order_id' => $order->id,
                    'cs_product_id' => $product->id,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'total' => $itemTotal
                ]);

                app(InventoryService::class)->move($product->id,D::sub('0',$qty),'Sale',$order->invoice_number,null,$orderItem->id);

                $totalMeters = D::add($totalMeters,$qty);
                $grossProfit = D::add($grossProfit,$itemProfit);
            }

            $totalMeters = D::value($totalMeters);

            // The discount comes off the shop's margin, not the customer's.
            $grossProfit = D::sub($grossProfit,$discount);

            $order->update([
                'total_meters_sold' => $totalMeters,
                'gross_profit' => $grossProfit,
            ]);

            /*
             * Ledger.
             *
             * NOTE: the column is `reference`, not `reference_number` —
             * cs_customer_ledgers and cs_customer_payments both define
             * `reference` is the canonical customer-payment reference field.
             * Writing the wrong name meant these inserts referenced a column
             * that does not exist in the migrations at all.
             */
            // 1. Debit the customer for the total sale amount
            $customer->due_balance = D::add((string)$customer->due_balance,$totalAmount);
            CustomerLedger::create([
                'cs_customer_id' => $customer->id,
                'date' => now(),
                'type' => 'Sale',
                'reference' => $order->invoice_number,
                'description' => 'Purchase of ' . $totalMeters . ' meters',
                'debit' => $totalAmount,
                'credit' => 0,
                'balance' => $customer->due_balance,
            ]);

            // 2. If they paid anything, credit it back immediately
            if ($paidAmount > 0) {
                CustomerPayment::create([
                    'cs_customer_id' => $customer->id,
                    // Links the payment to the sale it settles, so the
                    // Payments page and the order can be reconciled.
                    'cs_order_id' => $order->id,
                    'amount' => $paidAmount,
                    'payment_type' => 'Sale Payment',
                    'payment_method' => $validated['payment_method'],
                    'reference' => $order->invoice_number,
                    'payment_date' => now(),
                    'received_by' => auth()->user()?->name,
                    'status' => 'Completed',
                    'notes' => 'Payment taken at checkout',
                ]);

                $customer->due_balance = D::sub((string)$customer->due_balance,$paidAmount);
                CustomerLedger::create([
                    'cs_customer_id' => $customer->id,
                    'date' => now(),
                    'type' => 'Payment',
                    'reference' => $order->invoice_number,
                    'description' => 'Payment via ' . $validated['payment_method'],
                    'debit' => 0,
                    'credit' => $paidAmount,
                    'balance' => $customer->due_balance,
                ]);
            }

            // Lifetime figures the Customers page and reports read from.
            $customer->total_purchases = D::add((string)$customer->total_purchases,$totalAmount);
            $customer->last_purchase_date = now();
            $customer->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'order' => $order->load('customer', 'items.product'),
                'message' => 'Sale completed successfully!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }
}
