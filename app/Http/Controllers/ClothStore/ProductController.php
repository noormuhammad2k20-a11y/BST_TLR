<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use App\Models\ClothStore\Category;
use App\Models\ClothStore\Product;
use App\Models\ClothStore\OrderItem;
use App\Models\ClothStore\StockTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    /**
     * Sort keys are whitelisted rather than taken from the request, so a
     * crafted ?sort= cannot order by an arbitrary column.
     */
    private const SORTABLE = [
        'latest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'name'   => ['name', 'asc'],
        'price_low'  => ['price', 'asc'],
        'price_high' => ['price', 'desc'],
        'stock_low'  => ['stock_quantity', 'asc'],
        'stock_high' => ['stock_quantity', 'desc'],
    ];

    public function index(Request $request)
    {
        $query = Product::with('category');

        // --- Search: name, SKU or barcode -------------------------------
        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // --- Filters ----------------------------------------------------
        if ($request->filled('category')) {
            $query->where('cs_category_id', $request->category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('unit')) {
            $query->where('unit', $request->unit);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', (float) $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', (float) $request->max_price);
        }

        // Stock status is derived, not stored, so it is expressed as a
        // comparison against each product's own threshold rather than a
        // fixed number — a 500 m bolt and a 5 m remnant run out differently.
        if ($request->filled('stock_status')) {
            match ($request->stock_status) {
                'out'     => $query->where('stock_quantity', '<', 0.01),
                'low'     => $query->where('stock_quantity', '>=', 0.01)
                                   ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'),
                'healthy' => $query->whereColumn('stock_quantity', '>', 'low_stock_threshold'),
                default   => null,
            };
        }

        // --- Sorting ----------------------------------------------------
        [$column, $direction] = self::SORTABLE[$request->get('sort')] ?? self::SORTABLE['latest'];
        $query->orderBy($column, $direction);

        $products = $query->paginate(12)->withQueryString();

        // Headline figures are aggregated across the whole catalogue, not the
        // current page — otherwise filtering to one category would make it
        // look like the shop only owns that much stock.
        $stats = [
            'total'       => Product::count(),
            'active'      => Product::where('status', 'Active')->count(),
            'low_stock'   => Product::whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                                    ->where('status', 'Active')->count(),
            'stock_value' => (float) Product::selectRaw('COALESCE(SUM(stock_quantity * COALESCE(cost_price, 0)), 0) AS v')
                                    ->value('v'),
        ];

        $categories = Category::orderBy('name')->pluck('name', 'id');

        // Units actually in use, so the filter never offers an empty option.
        $units = Product::query()
            ->select('unit')
            ->distinct()
            ->orderBy('unit')
            ->pluck('unit')
            ->filter()
            ->values();

        return view('cloth-store.products.index', compact('products', 'categories', 'units', 'stats'));
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $product = DB::transaction(function () use ($validated) {
            $opening=(string)$validated['stock_quantity'];
            $validated['stock_quantity']='0.00';
            $product=Product::create($validated);
            app(\App\Services\ClothStore\InventoryService::class)->move($product->id,$opening,'Opening Stock',$product->sku);
            return $product->fresh();
        });

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product->load('category')
        ]);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validated = $this->validated($request, $product);

        DB::transaction(function () use (&$product,$validated) {
            $product=Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $delta=\App\Services\Decimal::sub((string)$validated['stock_quantity'],(string)$product->stock_quantity);
            unset($validated['stock_quantity']);
            $product->update($validated);
            app(\App\Services\ClothStore\InventoryService::class)->move($product->id,$delta,'Manual stock correction',$product->sku);
            $product=$product->fresh();
        });

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->load('category')
        ]);
    }

    /**
     * Shared rules for store() and update().
     *
     * stock_quantity and low_stock_threshold are 'numeric', not 'integer':
     * fabric is stocked in fractional metres, so an 'integer' rule rejected a
     * perfectly valid opening stock of 120.50 m outright. decimal:0,2 pins the
     * precision to the decimal(12,2) columns behind them, so a value like
     * 5.555 is refused at the edge instead of being silently rounded by MySQL.
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'cs_category_id' => 'required|exists:cs_categories,id',
            'name'           => 'required|string|max:255',
            'sku'            => [
                'nullable', 'string', 'max:255',
                Rule::unique('cs_products', 'sku')->ignore($product?->id),
            ],
            'barcode'        => [
                'nullable', 'string', 'max:255',
                Rule::unique('cs_products', 'barcode')->ignore($product?->id),
            ],
            'price'          => 'required|numeric|min:0|decimal:0,2',
            'cost_price'     => 'nullable|numeric|min:0|decimal:0,2',
            'stock_quantity' => 'required|numeric|min:0|decimal:0,2',
            'low_stock_threshold' => 'required|numeric|min:0|decimal:0,2',
            'unit'           => 'required|string|max:50',
            'status'         => 'required|in:Active,Inactive',
            'description'    => 'nullable|string',
        ], [
            'sku.unique'     => 'A product with this SKU already exists.',
            'barcode.unique' => 'A product with this barcode already exists.',
            'stock_quantity.decimal'      => 'Stock may have at most 2 decimal places (e.g. 120.50).',
            'low_stock_threshold.decimal' => 'The low-stock threshold may have at most 2 decimal places.',
        ]);
    }

    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // cs_order_items.cs_product_id is ON DELETE CASCADE, so deleting a
        // product that has ever been sold would silently delete those line
        // items too — quietly rewriting past invoices and every revenue and
        // profit report derived from them. Refuse, and offer archiving.
        if (OrderItem::where('cs_product_id', $product->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => "{$product->name} appears on existing sales and cannot be deleted. Set it to Inactive instead to hide it from the POS while keeping its history.",
            ], 422);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }
}
