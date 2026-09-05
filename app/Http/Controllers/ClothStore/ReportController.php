<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use App\Services\PdfExporter;
use Illuminate\Http\Request;
use App\Models\ClothStore\Order;
use App\Models\ClothStore\OrderItem;
use App\Models\ClothStore\Product;
use App\Models\ClothStore\Category;
use App\Models\ClothStore\Expense;
use App\Models\ClothStore\Customer;
use App\Models\ClothStore\CustomerPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /** Named windows the period selector offers. */
    public const FILTERS = [
        'today'      => 'Today',
        'yesterday'  => 'Yesterday',
        'this_week'  => 'This Week',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_year'  => 'This Year',
        'custom'     => 'Custom Range',
    ];

    public function index(Request $request)
    {
        $data = $this->build($request);

        return view('cloth-store.reports.index', $data);
    }

    /**
     * The whole report — every tab, in order — as a downloadable PDF for the
     * period currently selected on screen.
     */
    public function pdf(Request $request): Response
    {
        $data = $this->build($request);

        /** @var Carbon $startDate */
        $startDate = $data['startDate'];
        /** @var Carbon $endDate */
        $endDate = $data['endDate'];

        $data = array_merge($data, PdfExporter::documentMeta(
            'Cloth Store Report',
            $startDate->toDateString() === $endDate->toDateString()
                ? $startDate->format('l, d M Y')
                : $startDate->format('d M Y') . '  –  ' . $endDate->format('d M Y'),
            'Filter: ' . (self::FILTERS[$data['filter']] ?? ucfirst($data['filter']))
        ));

        return PdfExporter::download(
            'cloth-store.reports.pdf',
            $data,
            'cloth-store-report-' . $startDate->toDateString() . '-to-' . $endDate->toDateString() . '.pdf'
        );
    }

    /* ------------------------------------------------------------------ */

    /**
     * Every figure both the screen and the PDF are built from, for one window.
     *
     * Keeping this in a single place is what guarantees the exported file and
     * the page it was exported from can never disagree.
     *
     * @return array<string, mixed>
     */
    private function build(Request $request): array
    {
        [$filter, $startDate, $endDate] = $this->resolveRange($request);

        // Top Level KPIs
        $salesQuery = Order::whereBetween('created_at', [$startDate, $endDate])->where('status', 'Completed');

        $totalSales = $salesQuery->sum('total_amount');
        $totalMetersSold = $salesQuery->sum('total_meters_sold');
        $totalTransactions = $salesQuery->count();
        $grossProfit = $salesQuery->sum('gross_profit');

        $totalExpenses = Expense::whereBetween('expense_date', [$startDate, $endDate])->sum('amount');
        $netProfit = $grossProfit - $totalExpenses;
        $profitMargin = $totalSales > 0 ? ($netProfit / $totalSales) * 100 : 0;

        $kpis = [
            'net_sales' => $totalSales,
            'meters_sold' => $totalMetersSold,
            'gross_profit' => $grossProfit,
            'total_expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'profit_margin' => $profitMargin
        ];

        // 1. Sales Report
        $salesReport = [
            'transactions' => $totalTransactions,
            'average_sale' => $totalTransactions > 0 ? $totalSales / $totalTransactions : 0,
            'average_meters' => $totalTransactions > 0 ? $totalMetersSold / $totalTransactions : 0,
            'discounts' => $salesQuery->sum('discount'),
        ];

        // 2. Fabric Sales Report (Grouped by Product)
        $fabricSales = OrderItem::join('cs_orders', 'cs_order_items.cs_order_id', '=', 'cs_orders.id')
            ->join('cs_products', 'cs_order_items.cs_product_id', '=', 'cs_products.id')
            ->join('cs_categories', 'cs_products.cs_category_id', '=', 'cs_categories.id')
            ->where('cs_orders.status', 'Completed')
            ->whereBetween('cs_orders.created_at', [$startDate, $endDate])
            ->select(
                'cs_products.name as product',
                'cs_categories.name as category',
                DB::raw('SUM(cs_order_items.quantity) as meters_sold'),
                DB::raw('SUM(cs_order_items.total) as revenue'),
                DB::raw('SUM(cs_order_items.quantity * cs_order_items.unit_cost) as purchase_cost')
            )
            ->groupBy('cs_products.id', 'cs_products.name', 'cs_categories.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(function($item) {
                $item->profit = $item->revenue - $item->purchase_cost;
                $item->margin = $item->revenue > 0 ? ($item->profit / $item->revenue) * 100 : 0;
                return $item;
            });

        // 3. Category Report
        $categorySales = OrderItem::join('cs_orders', 'cs_order_items.cs_order_id', '=', 'cs_orders.id')
            ->join('cs_products', 'cs_order_items.cs_product_id', '=', 'cs_products.id')
            ->join('cs_categories', 'cs_products.cs_category_id', '=', 'cs_categories.id')
            ->where('cs_orders.status', 'Completed')
            ->whereBetween('cs_orders.created_at', [$startDate, $endDate])
            ->select(
                'cs_categories.name as category',
                DB::raw('SUM(cs_order_items.quantity) as meters_sold'),
                DB::raw('SUM(cs_order_items.total) as revenue'),
                DB::raw('COUNT(DISTINCT cs_orders.id) as transactions')
            )
            ->groupBy('cs_categories.id', 'cs_categories.name')
            ->orderByDesc('revenue')
            ->get();

        // 4. Inventory Valuation
        $inventory = Product::with('category')->where('status', 'Active')->get()->map(function($item) {
            return [
                'product' => $item->name,
                'category' => $item->category->name ?? 'Uncategorized',
                'meters' => $item->stock_quantity,
                'purchase_rate' => $item->cost_price,
                'selling_rate' => $item->price,
                'value' => $item->stock_quantity * $item->cost_price,
                'reorder' => $item->low_stock_threshold,
                'status' => $item->stock_quantity <= $item->low_stock_threshold ? 'Low Stock' : 'In Stock'
            ];
        });

        // 5. Customer Report
        $customers = Order::join('cs_customers', 'cs_orders.cs_customer_id', '=', 'cs_customers.id')
            ->where('cs_orders.status', 'Completed')
            ->whereBetween('cs_orders.created_at', [$startDate, $endDate])
            ->select(
                'cs_customers.name',
                'cs_customers.due_balance',
                DB::raw('COUNT(cs_orders.id) as visits'),
                DB::raw('SUM(cs_orders.total_meters_sold) as meters_purchased'),
                DB::raw('SUM(cs_orders.total_amount) as total_spending')
            )
            ->groupBy('cs_customers.id', 'cs_customers.name', 'cs_customers.due_balance')
            ->orderByDesc('total_spending')
            ->take(20)
            ->get();

        // 6. Expense Report
        $expenses = Expense::whereBetween('expense_date', [$startDate, $endDate])
            ->select('category', DB::raw('SUM(amount) as total'))
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        // 7. Payment Methods
        $payments = Order::where('status', 'Completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select('payment_method', DB::raw('SUM(total_amount) as total'))
            ->groupBy('payment_method')
            ->get();

        // Graph Data: Daily Sales
        $dailySales = Order::where('status', 'Completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_amount) as revenue'), DB::raw('SUM(total_meters_sold) as meters'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get();

        $chartDates = $dailySales->pluck('date')->map(function($d) { return Carbon::parse($d)->format('M d'); })->toArray();
        $chartRevenues = $dailySales->pluck('revenue')->toArray();
        $chartMeters = $dailySales->pluck('meters')->toArray();

        return compact(
            'filter', 'startDate', 'endDate', 'kpis', 'salesReport', 'fabricSales',
            'categorySales', 'inventory', 'customers', 'expenses', 'payments',
            'dailySales', 'chartDates', 'chartRevenues', 'chartMeters'
        );
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $filter = $request->get('date_filter', 'this_month');

        if (!array_key_exists($filter, self::FILTERS)) {
            $filter = 'this_month';
        }

        $now = Carbon::now();

        [$startDate, $endDate] = match ($filter) {
            'today'      => [$now->copy()->startOfDay(),                 $now->copy()->endOfDay()],
            'yesterday'  => [$now->copy()->subDay()->startOfDay(),       $now->copy()->subDay()->endOfDay()],
            'this_week'  => [$now->copy()->startOfWeek(),                $now->copy()->endOfWeek()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(),   $now->copy()->subMonth()->endOfMonth()],
            'this_year'  => [$now->copy()->startOfYear(),                $now->copy()->endOfYear()],
            'custom'     => $this->customRange($request, $now),
            default      => [$now->copy()->startOfMonth(),               $now->copy()->endOfMonth()],
        };

        return [$filter, $startDate, $endDate];
    }

    /**
     * A custom window needs both ends and a start that is not after the end;
     * anything else quietly falls back to the current month rather than
     * producing an empty report the user cannot explain.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function customRange(Request $request, Carbon $now): array
    {
        $start = $request->filled('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $end   = $request->filled('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : null;

        if (!$start || !$end) {
            return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        return $start->lte($end) ? [$start, $end] : [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
    }
}
