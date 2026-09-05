<?php

namespace App\Http\Controllers\ClothStore;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\ClothStore\SalesAnalytics;
use App\Models\ClothStore\Order;
use App\Models\ClothStore\Product;
use App\Models\ClothStore\Customer;
use App\Models\ClothStore\Expense;
use App\Models\ClothStore\OrderItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->query('range', 'today');
        
        $startDate = Carbon::today();
        $endDate = Carbon::now();

        switch ($range) {
            case 'yesterday':
                $startDate = Carbon::yesterday();
                $endDate = Carbon::yesterday()->endOfDay();
                break;
            case 'week':
                $startDate = Carbon::now()->startOfWeek();
                break;
            case 'month':
                $startDate = Carbon::now()->startOfMonth();
                break;
            case 'last_month':
                $startDate = Carbon::now()->subMonth()->startOfMonth();
                $endDate = Carbon::now()->subMonth()->endOfMonth();
                break;
            case 'all':
                $startDate = Carbon::create(2000, 1, 1);
                break;
        }

        /*
         * Cancelled orders are excluded from every revenue figure.
         *
         * The KPIs previously summed *all* orders in the range, so cancelling
         * a sale left its value in today's takings, gross profit, metres sold
         * and transaction count — the dashboard reported money the shop had
         * refunded or never collected.
         */
        $soldStatuses = fn ($q) => $q->whereNotIn('status', ['Cancelled']);

        $ordersQuery = SalesAnalytics::orders()->whereBetween('created_at', [$startDate, $endDate]);
        $expensesQuery = Expense::whereBetween('expense_date', [$startDate, $endDate]);

        $transactions = (clone $ordersQuery)->sum('sale_count');

        // KPIs
        $kpis = [
            'sales' => (float) (clone $ordersQuery)->sum('total_amount'),
            'meters_sold' => (float) (clone $ordersQuery)->sum('total_meters_sold'),
            'transactions' => $transactions,
            'gross_profit' => (float) (clone $ordersQuery)->sum('gross_profit'),
            'total_stock_meters' => (float) Product::where('unit', 'meter')->sum('stock_quantity'),
            'total_stock_value' => (float) Product::sum(DB::raw('stock_quantity * COALESCE(cost_price, 0)')),
            // Only active products can actually be reordered/sold.
            'low_stock_count' => Product::where('status', 'Active')
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count(),
            'customer_dues' => (float) Customer::sum('due_balance'),
            'total_products' => Product::count(),
            'total_customers' => Customer::count(),
            'expenses' => (float) $expensesQuery->sum('amount'),
            'avg_sale_value' => $transactions > 0
                ? round((float) (clone $ordersQuery)->sum('total_amount') / $transactions, 2) : 0,
            'avg_meters_per_trx' => $transactions > 0
                ? round((float) (clone $ordersQuery)->sum('total_meters_sold') / $transactions, 2) : 0,
            'total_discounts' => (float) (clone $ordersQuery)->sum('discount'),
            'total_returns' => (float) DB::table('cs_financial_adjustments')->whereBetween('created_at', [$startDate, $endDate])->where('kind','return')->sum('amount'),
        ];

        // 1 & 2: Daily Sales & Daily Meters (Last 30 Days)
        $dailyRaw = SalesAnalytics::orders()->select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total_amount) as total_sales'),
            DB::raw('SUM(total_meters_sold) as total_meters')
        )->where('created_at', '>=', Carbon::now()->subDays(30))->groupBy('date')->orderBy('date')->get();

        $dailyLabels = [];
        $dailySales = [];
        $dailyMeters = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = Carbon::now()->subDays($i)->format('Y-m-d');
            $dailyLabels[] = Carbon::now()->subDays($i)->format('M d');
            $row = $dailyRaw->firstWhere('date', $d);
            $dailySales[] = $row ? (float) $row->total_sales : 0;
            $dailyMeters[] = $row ? (float) $row->total_meters : 0;
        }

        // 3 & 4: Monthly Revenue & Monthly Profit (Last 12 Months)
        $monthlyRaw = SalesAnalytics::orders()->select(
            DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
            DB::raw('SUM(total_amount) as revenue'),
            DB::raw('SUM(gross_profit) as profit')
        )->where('created_at', '>=', Carbon::now()->subMonths(11)->startOfMonth())->groupBy('month')->orderBy('month')->get();

        $monthlyLabels = [];
        $monthlyRevenue = [];
        $monthlyProfit = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = Carbon::now()->subMonths($i)->format('Y-m');
            $monthlyLabels[] = Carbon::now()->subMonths($i)->format('M Y');
            $row = $monthlyRaw->firstWhere('month', $m);
            $monthlyRevenue[] = $row ? (float) $row->revenue : 0;
            $monthlyProfit[] = $row ? (float) $row->profit : 0;
        }

        // 5. Sales by Category
        $catSales = SalesAnalytics::lines()->join('cs_products', 'cs_order_items.cs_product_id', '=', 'cs_products.id')
            ->join('cs_categories', 'cs_products.cs_category_id', '=', 'cs_categories.id')
            ->select('cs_categories.name', DB::raw('SUM(cs_order_items.total) as total'))
            ->whereBetween('cs_order_items.created_at', [$startDate, $endDate])
            ->groupBy('cs_categories.name')->get();

        // 6. Sales by Payment Method
        $paymentSales = SalesAnalytics::orders()->select('payment_method', DB::raw('SUM(total_amount) as total'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('payment_method')->get();

        // 7 & 8: Top Selling Fabrics (Meters and Revenue)
        $topRevenue = SalesAnalytics::lines()
            ->select('cs_product_id', DB::raw('SUM(cs_order_items.total) as revenue'), DB::raw('SUM(cs_order_items.quantity) as meters'), DB::raw('SUM(cs_order_items.total - (cs_order_items.unit_cost * cs_order_items.quantity)) as profit'))
            ->whereBetween('cs_order_items.created_at', [$startDate, $endDate])
            ->groupBy('cs_product_id')->orderByDesc('revenue')->limit(5)->get();
            
        $topMeters = SalesAnalytics::lines()
            ->select('cs_product_id', DB::raw('SUM(cs_order_items.total) as revenue'), DB::raw('SUM(cs_order_items.quantity) as meters'), DB::raw('SUM(cs_order_items.total - (cs_order_items.unit_cost * cs_order_items.quantity)) as profit'))
            ->join('cs_products', 'cs_order_items.cs_product_id', '=', 'cs_products.id')
            ->where('cs_products.unit', 'meter')
            ->whereBetween('cs_order_items.created_at', [$startDate, $endDate])
            ->groupBy('cs_product_id')->orderByDesc('meters')->limit(5)->get();

        $productMap=Product::withTrashed()->with('category')->whereIn('id',$topRevenue->pluck('cs_product_id')->merge($topMeters->pluck('cs_product_id')))->get()->keyBy('id');
        foreach ($topRevenue->merge($topMeters) as $row) $row->product=$productMap->get($row->cs_product_id);

        // Tables Data — every list is capped. These were unbounded ->get()
        // calls, so a shop with a few thousand low-stock lines would render
        // the entire table into the dashboard.
        $lowStock = Product::with('category')
            ->where('status', 'Active')
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
            ->orderBy('stock_quantity')
            ->limit(10)->get();

        $recentSales = Order::with('customer')->orderByDesc('created_at')->limit(10)->get();

        $recentCustomers = Customer::whereNotNull('last_purchase_date')
            ->orderByDesc('last_purchase_date')->limit(5)->get();

        $customerDuesList = Customer::where('due_balance', '>', 0)
            ->orderByDesc('due_balance')->limit(10)->get();

        // Package all charts
        $charts = [
            'daily_labels' => $dailyLabels, 'daily_sales' => $dailySales, 'daily_meters' => $dailyMeters,
            'monthly_labels' => $monthlyLabels, 'monthly_revenue' => $monthlyRevenue, 'monthly_profit' => $monthlyProfit,
            'cat_labels' => $catSales->pluck('name'), 'cat_data' => $catSales->pluck('total'),
            'pay_labels' => $paymentSales->pluck('payment_method'), 'pay_data' => $paymentSales->pluck('total')
        ];

        if ($request->wantsJson()) {
            return response()->json(compact('kpis', 'charts'));
        }

        return view('cloth-store.dashboard', compact(
            'range', 'kpis', 'charts', 
            'topRevenue', 'topMeters', 'lowStock', 'recentSales', 'recentCustomers',
            'customerDuesList'
        ));
    }
}
