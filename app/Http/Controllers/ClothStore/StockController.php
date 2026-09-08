<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClothStore\Product;
use App\Models\ClothStore\StockTransaction;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    public function index()
    {
        // KPI metrics — one aggregate query.
        //
        // These six figures used to be derived in PHP from a full
        // Product::where('status','Active')->get(), which hydrated every active
        // product into an Eloquent model on every page load — and again on every
        // SPA refresh after a stock operation — purely to produce six scalars.
        // The database does all six in a single pass.
        //
        // The CASE expressions mirror the model accessors exactly:
        //   available_stock = ROUND(stock_quantity - reserved_quantity, 2)
        //   is_low_stock    = available_stock <= low_stock_threshold
        // Low stock keeps the original "still has stock on hand" guard
        // (stock_quantity > 0) so a depleted product is counted once, under Out
        // of Stock. Out of stock deliberately tests raw stock_quantity rather
        // than available stock — that is the pre-existing behaviour, preserved.
        $kpis = Product::where('status', 'Active')
            ->selectRaw('COALESCE(SUM(stock_quantity), 0) AS total_stock')
            ->selectRaw('COALESCE(SUM(stock_quantity * cost_price), 0) AS stock_value')
            ->selectRaw('COALESCE(SUM(reserved_quantity), 0) AS reserved_stock')
            ->selectRaw('COALESCE(SUM(incoming_quantity), 0) AS incoming_stock')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN ROUND(stock_quantity - reserved_quantity, 2) <= low_stock_threshold'
                . ' AND stock_quantity > 0 THEN 1 ELSE 0 END), 0) AS low_stock_count'
            )
            ->selectRaw('COALESCE(SUM(CASE WHEN stock_quantity <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count')
            ->first();

        $totalStock      = (float) $kpis->total_stock;
        $stockValue      = (float) $kpis->stock_value;
        $reservedStock   = (float) $kpis->reserved_stock;
        $incomingStock   = (float) $kpis->incoming_stock;
        $lowStockCount   = (int) $kpis->low_stock_count;
        $outOfStockCount = (int) $kpis->out_of_stock_count;

        // Feeds the category -> product cascade in the New Operation modal.
        // Only the four columns that modal actually reads, so this stays a thin
        // id/name list instead of a full row per product.
        $products = Product::where('status', 'Active')
            ->select('id', 'cs_category_id', 'name', 'sku')
            ->orderBy('name')
            ->get();

        // Fetch Inventory List — searched and paginated on the server. The KPI
        // figures above come from their own aggregate query, so limiting this
        // list does not distort them.
        $inventoryQuery = Product::with(['category', 'productLocations.location'])
                            ->where('status', 'Active');

        if (request()->filled('search')) {
            $search = trim(request('search'));

            $inventoryQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if (request()->filled('stock_status')) {
            match (request('stock_status')) {
                'out'     => $inventoryQuery->where('stock_quantity', '<', 0.01),
                'low'     => $inventoryQuery->where('stock_quantity', '>=', 0.01)
                                            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold'),
                'healthy' => $inventoryQuery->whereColumn('stock_quantity', '>', 'low_stock_threshold'),
                default   => null,
            };
        }

        $inventory = $inventoryQuery->orderBy('name')->paginate(50)->withQueryString();

        $categories = \App\Models\ClothStore\Category::orderBy('name')->get();

        return view('cloth-store.stock.index', compact(
            'totalStock', 'stockValue', 'lowStockCount', 'outOfStockCount', 
            'reservedStock', 'incomingStock', 'inventory', 'categories', 'products'
        ));
    }

    public function history()
    {
        $transactions = StockTransaction::with(['product', 'user', 'fromLocation', 'toLocation'])
                            ->orderByDesc('created_at')
                            ->paginate(50);
                            
        return view('cloth-store.stock.history', compact('transactions'));
    }

    public function transaction(Request $request)
    {
        $request->validate([
            'cs_product_id' => 'required|exists:cs_products,id',
            'operation' => 'required|in:in,out,adjustment',
            // Fabric is bought in fractional metres, so quantities retain two
            // decimal places for direct market stock intake.
            'quantity' => 'required|numeric|min:0.01|decimal:0,2',
            'reason' => 'nullable|string',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $request->validate(['adjustment_type'=>'nullable|in:add,subtract']);
        DB::transaction(function () use ($request) {
            $stock=app(\App\Services\ClothStore\InventoryService::class);
            $qty=\App\Services\Decimal::value((string)$request->quantity);
            $reference=$request->reference ?? 'STOCK-'.\Illuminate\Support\Str::uuid();
            $subtract=$request->operation==='out' || ($request->operation==='adjustment' && $request->adjustment_type==='subtract');
            $reason=$request->reason ?? ($request->operation === 'in' ? 'Market stock purchase' : $request->operation);
            $stock->move((int)$request->cs_product_id,$subtract?\App\Services\Decimal::sub('0',$qty):$qty,
                $reason,$reference);
        },3);
        return response()->json(['success'=>true,'message'=>'Stock operation recorded successfully.']);
    }

    public function alerts()
    {
        $products = Product::with(['category'])->where('status', 'Active')->get();
        
        $criticalCount = 0;
        $lowCount = 0;
        $outOfStockCount = 0;
        $recommendedCount = 0;

        $alertProducts = [];

        foreach ($products as $p) {
            if ($p->ignore_stock_alerts) continue;

            $level = $p->alert_level;
            
            if ($level === 'Out of Stock') $outOfStockCount++;
            elseif ($level === 'Critical') $criticalCount++;
            elseif ($level === 'Low') $lowCount++;

            if (in_array($level, ['Out of Stock', 'Critical', 'Low'])) {
                $alertProducts[] = $p;
                if ($p->suggested_reorder_qty > 0) {
                    $recommendedCount++;
                }
            }
        }

        return view('cloth-store.stock.alerts', compact(
            'alertProducts', 'criticalCount', 'lowCount', 'outOfStockCount', 'recommendedCount'
        ));
    }

    public function ignoreAlert(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        $product->ignore_stock_alerts = true;
        $product->save();

        return response()->json(['success' => true, 'message' => 'Alert ignored successfully.']);
    }

    public function updateReorderConfig(Request $request, $id)
    {
        $request->validate([
            'low_stock_threshold' => 'required|integer|min:0',
            'suggested_reorder_qty' => 'required|integer|min:0'
        ]);

        $product = Product::findOrFail($id);
        $product->update([
            'low_stock_threshold' => $request->low_stock_threshold,
            'suggested_reorder_qty' => $request->suggested_reorder_qty,
        ]);

        return response()->json(['success' => true, 'message' => 'Reorder configuration updated.']);
    }
}
