<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * All aggregate/analytics queries live here. Results are cached for a short
 * window and invalidated on every write, which keeps dashboards under a single
 * round-trip while staying accurate.
 */
class StatsService
{
    /** Cache keys owned by this service, cleared together on any data change. */
    private const KEYS = [
        'stats.dashboard',
        'stats.revenue_trend.7',
        'stats.revenue_trend.30',
        'stats.revenue_trend.365',
        'stats.finance',
        'stats.order_status_breakdown',
        'stats.sales_by_category',
        'stats.customer_growth',
        'stats.top_customers',
        'stats.tailor_performance',
        'dashboard.activity_feed',
        'stats.reports.today',
        'stats.reports.yesterday',
        'stats.reports.week',
        'stats.reports.month',
        'stats.reports.year',
    ];

    private const TTL = 60;

    public static function flush(): void
    {
        foreach (self::KEYS as $key) {
            Cache::forget($key);
        }

        foreach (['week', 'month', 'year'] as $range) {
            Cache::forget("stats.reports.{$range}");
        }

        foreach ([6, 12] as $months) {
            Cache::forget("stats.cashflow.{$months}");
        }

        Cache::forget('expenses.stats');
        Cache::forget('customers.stats');
        Cache::forget('measurements.stats');

        NotificationService::flushCache();
    }

    /* ------------------------------------------------------------------ */
    /* Dashboard                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Headline counters plus a real week-over-week delta for each, replacing
     * the previously hardcoded trend percentages.
     */
    public static function dashboard(): array
    {
        return Cache::remember('stats.dashboard', self::TTL, function () {
            $now           = now();
            $weekStart     = $now->copy()->startOfWeek();
            $prevWeekStart = $weekStart->copy()->subWeek();
            $monthStart    = $now->copy()->startOfMonth();
            $prevMonth     = $monthStart->copy()->subMonth();

            $totalCustomers = Customer::count();
            $newThisWeek    = Customer::where('created_at', '>=', $weekStart)->count();
            $newPrevWeek    = Customer::whereBetween('created_at', [$prevWeekStart, $weekStart])->count();

            $activeOrders     = Order::open()->count();
            $activeThisWeek   = Order::open()->where('created_at', '>=', $weekStart)->count();
            $activePrevWeek   = Order::open()->whereBetween('created_at', [$prevWeekStart, $weekStart])->count();

            $revenueMonth     = (float) Payment::where('status','Completed')->whereNull('reverses_payment_id')->where('date', '>=', $monthStart)->sum('amount');
            $revenuePrevMonth = (float) Payment::where('status','Completed')->whereNull('reverses_payment_id')->whereBetween('date', [$prevMonth, $monthStart])->sum('amount');

            $pendingDues     = (float) Order::open()->sum('balance');
            $pendingLastWeek = (float) Order::open()->where('created_at', '<', $weekStart)->sum('balance');

            return [
                'total_customers'  => $totalCustomers,
                'active_orders'    => $activeOrders,
                'revenue_month'    => $revenueMonth,
                'pending_dues'     => $pendingDues,
                'trends'           => [
                    'customers' => self::delta($newThisWeek, $newPrevWeek),
                    'orders'    => self::delta($activeThisWeek, $activePrevWeek),
                    'revenue'   => self::delta($revenueMonth, $revenuePrevMonth),
                    'dues'      => self::delta($pendingDues, $pendingLastWeek),
                ],
                'due_today_count'  => Order::dueToday()->count(),
                'overdue_count'    => Order::overdue()->count(),
                // Orders whose garments are back in the shop and waiting for a
                // member of staff to physically check them.
                'awaiting_verification' => Order::where('status', 'Ready for Verification')->count(),
            ];
        });
    }

    /**
     * Daily revenue for the last N days, zero-filled so the chart always has a
     * continuous X axis.
     *
     * @return array{labels: array<int, string>, data: array<int, float>}
     */
    public static function revenueTrend(int $days = 30): array
    {
        return Cache::remember("stats.revenue_trend.{$days}", self::TTL, function () use ($days) {
            $start = now()->subDays($days - 1)->startOfDay();

            $rows = Payment::query()->where('status','Completed')->whereNull('reverses_payment_id')
                ->selectRaw('DATE(`date`) as d, SUM(amount) as total')
                ->where('date', '>=', $start)
                ->groupBy('d')
                ->pluck('total', 'd');

            $labels = [];
            $data   = [];

            for ($i = 0; $i < $days; $i++) {
                $day = $start->copy()->addDays($i);
                $key = $day->toDateString();

                $labels[] = $days > 60 ? $day->format('M') : $day->format('j');
                $data[]   = round((float) ($rows[$key] ?? 0), 2);
            }

            return ['labels' => $labels, 'data' => $data];
        });
    }

    /**
     * Live activity feed for the dashboard, sourced from the audit trail.
     */
    public static function activityFeed(int $limit = 12): array
    {
        return Cache::remember('dashboard.activity_feed', 20, function () use ($limit) {
            return ActivityLog::query()
                ->latest()
                ->limit($limit)
                ->get()
                ->map(fn (ActivityLog $log) => [
                    'id'     => $log->id,
                    'title'  => $log->action,
                    'desc'   => $log->description,
                    'time'   => $log->created_at->format('g:i A'),
                    'when'   => $log->created_at->diffForHumans(),
                    'color'  => match ($log->category) {
                        'orders'    => '#4F46E5',
                        'payments'  => '#10B981',
                        'customers' => '#0EA5E9',
                        'inventory' => '#F59E0B',
                        'auth'      => '#8B5CF6',
                        default     => '#64748B',
                    },
                ])
                ->all();
        });
    }

    /* ------------------------------------------------------------------ */
    /* Finance                                                             */
    /* ------------------------------------------------------------------ */

    public static function finance(): array
    {
        return Cache::remember('stats.finance', self::TTL, function () {
            $collected = (float) Payment::where('status','Completed')->whereNull('reverses_payment_id')->sum('amount');
            $invoiced  = (float) Order::sum('total');

            $totalInvoices = Order::count();
            $paidInvoices  = Order::where('balance', '<=', 0)->count();

            return [
                'total_collected' => $collected,
                'pending'         => max($invoiced - $collected, 0),
                'total_invoices'  => $totalInvoices,
                'paid_invoices'   => $paidInvoices,
                'collection_rate' => $totalInvoices > 0
                    ? round($paidInvoices / $totalInvoices * 100, 1)
                    : 0.0,
                'total_expenses'  => (float) Expense::sum('amount'),
                'net_profit'      => $collected - (float) Expense::sum('amount'),
            ];
        });
    }

    /**
     * Income vs expenses per month for the cash-flow chart.
     *
     * @return array{labels: array<int,string>, income: array<int,float>, expenses: array<int,float>}
     */
    public static function cashflow(int $months = 6): array
    {
        return Cache::remember("stats.cashflow.{$months}", self::TTL, function () use ($months) {
            $labels = [];
            $income = [];
            $spend  = [];

            for ($i = $months - 1; $i >= 0; $i--) {
                $month = now()->copy()->subMonths($i);
                $start = $month->copy()->startOfMonth();
                $end   = $month->copy()->endOfMonth();

                $labels[] = $month->format('M');
                $income[] = round((float) Payment::where('status','Completed')->whereNull('reverses_payment_id')->whereBetween('date', [$start, $end])->sum('amount'), 2);
                $spend[]  = round((float) Expense::whereBetween('date', [$start, $end])->sum('amount'), 2);
            }

            return ['labels' => $labels, 'income' => $income, 'expenses' => $spend];
        });
    }

    /* ------------------------------------------------------------------ */
    /* Reports                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Full analytics payload for the Reports page, scoped to a date range.
     */
    public static function reports(string $range = 'month', ?Carbon $from = null, ?Carbon $to = null): array
    {
        $custom = $from !== null || $to !== null;
        $cacheKey = "stats.reports.{$range}";

        $build = function () use ($range, $from, $to) {
            [$start, $end]         = self::resolveRange($range, $from, $to);
            [$prevStart, $prevEnd] = self::previousRange($start, $end);

            $revenue     = (float) Payment::where('status','Completed')->whereNull('reverses_payment_id')->whereBetween('date', [$start, $end])->sum('amount');
            $prevRevenue = (float) Payment::where('status','Completed')->whereNull('reverses_payment_id')->whereBetween('date', [$prevStart, $prevEnd])->sum('amount');

            $completed = Order::whereIn('status', ['Delivered', 'Completed'])
                ->whereBetween('updated_at', [$start, $end])->count();
            $prevCompleted = Order::whereIn('status', ['Delivered', 'Completed'])
                ->whereBetween('updated_at', [$prevStart, $prevEnd])->count();

            $ordersInRange = Order::whereBetween('created_at', [$start, $end]);
            $orderCount    = (clone $ordersInRange)->count();
            $orderValue    = (float) (clone $ordersInRange)->sum('total');
            $avgOrder      = $orderCount > 0 ? $orderValue / $orderCount : 0;

            $prevOrders    = Order::whereBetween('created_at', [$prevStart, $prevEnd]);
            $prevCount     = (clone $prevOrders)->count();
            $prevAvg       = $prevCount > 0 ? (float) (clone $prevOrders)->sum('total') / $prevCount : 0;

            $invoiced      = (float) (clone $ordersInRange)->sum('total');
            $collectionRate = $invoiced > 0 ? min(round($revenue / $invoiced * 100, 1), 100) : 0.0;

            $prevInvoiced   = (float) (clone $prevOrders)->sum('total');
            $prevCollection = $prevInvoiced > 0 ? min(round($prevRevenue / $prevInvoiced * 100, 1), 100) : 0.0;

            return [
                'range'        => $range,
                'start'        => $start->toDateString(),
                'end'          => $end->toDateString(),
                'label'        => $start->format('M j, Y') . ' - ' . $end->format('M j, Y'),
                'kpis'         => [
                    'revenue' => [
                        'value' => $revenue,
                        'delta' => self::delta($revenue, $prevRevenue),
                        'bar'   => self::progressAgainst($revenue, max($prevRevenue, $revenue)),
                    ],
                    'completed' => [
                        'value' => $completed,
                        'delta' => self::delta($completed, $prevCompleted),
                        'bar'   => self::progressAgainst($completed, max($prevCompleted, $completed)),
                    ],
                    'avg_order' => [
                        'value' => round($avgOrder, 2),
                        'delta' => self::delta($avgOrder, $prevAvg),
                        'bar'   => self::progressAgainst($avgOrder, max($prevAvg, $avgOrder)),
                    ],
                    'collection_rate' => [
                        'value' => $collectionRate,
                        'delta' => self::delta($collectionRate, $prevCollection),
                        'bar'   => (int) round($collectionRate),
                    ],
                ],
                'revenue_series'  => self::revenueSeries($start, $end),
                'category_series' => self::salesByCategory($start, $end),
                'status_series'   => self::orderStatusBreakdown(),
                'growth_series'   => self::customerGrowth(),
                'top_customers'   => self::topCustomers($start, $end),
                'tailors'         => self::tailorPerformance($start, $end),
            ];
        };

        return $custom ? $build() : Cache::remember($cacheKey, self::TTL, $build);
    }

    /**
     * Revenue bucketed sensibly for the range width (days / weeks / months).
     */
    private static function revenueSeries(Carbon $start, Carbon $end): array
    {
        $days = max($start->diffInDays($end), 1);

        if ($days <= 14) {
            $format = '%Y-%m-%d';
            $label  = fn (Carbon $d) => $d->format('M j');
            $step   = 'addDay';
        } elseif ($days <= 92) {
            $format = '%x-W%v';
            $label  = null;
            $step   = null;
        } else {
            $format = '%Y-%m';
            $label  = null;
            $step   = null;
        }

        $rows = Payment::query()->where('status','Completed')->whereNull('reverses_payment_id')
            ->selectRaw("DATE_FORMAT(`date`, '{$format}') as bucket, SUM(amount) as total")
            ->whereBetween('date', [$start, $end])
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        if ($step === 'addDay') {
            $labels = [];
            $data   = [];
            $map    = $rows->pluck('total', 'bucket');

            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                $labels[] = $label($d);
                $data[]   = round((float) ($map[$d->toDateString()] ?? 0), 2);
            }

            return ['labels' => $labels, 'data' => $data];
        }

        return [
            'labels' => $rows->pluck('bucket')->map(function ($b) {
                if (str_contains($b, '-W')) {
                    return 'Wk ' . ltrim(explode('-W', $b)[1], '0');
                }

                return Carbon::createFromFormat('Y-m', $b)->format('M');
            })->all(),
            'data' => $rows->pluck('total')->map(fn ($v) => round((float) $v, 2))->all(),
        ];
    }

    /**
     * Share of orders per garment category, derived from linked services and
     * falling back to the free-text garment name.
     */
    private static function salesByCategory(Carbon $start, Carbon $end): array
    {
        $rows = Order::query()
            ->leftJoin('product_services', 'orders.product_service_id', '=', 'product_services.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('COALESCE(product_services.category, orders.garment, "Others") as label, COUNT(*) as total')
            ->groupBy('label')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return [
            'labels' => $rows->pluck('label')->all(),
            'data'   => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
        ];
    }

    private static function orderStatusBreakdown(): array
    {
        return Cache::remember('stats.order_status_breakdown', self::TTL, function () {
            $rows = Order::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->orderByDesc('total')
                ->get();

            return [
                'labels' => $rows->pluck('status')->all(),
                'data'   => $rows->pluck('total')->map(fn ($v) => (int) $v)->all(),
            ];
        });
    }

    /**
     * New vs returning customers over the last six months.
     */
    private static function customerGrowth(): array
    {
        return Cache::remember('stats.customer_growth', self::TTL, function () {
            $labels    = [];
            $newData   = [];
            $returning = [];

            for ($i = 5; $i >= 0; $i--) {
                $month = now()->copy()->subMonths($i);
                $start = $month->copy()->startOfMonth();
                $end   = $month->copy()->endOfMonth();

                $labels[] = $month->format('M');

                $newData[] = Customer::whereBetween('created_at', [$start, $end])->count();

                $returning[] = Order::whereBetween('orders.created_at', [$start, $end])
                    ->whereHas('customer', fn ($q) => $q->where('created_at', '<', $start))
                    ->distinct('customer_id')
                    ->count('customer_id');
            }

            return ['labels' => $labels, 'new' => $newData, 'returning' => $returning];
        });
    }

    private static function topCustomers(Carbon $start, Carbon $end, int $limit = 4): array
    {
        return Customer::query()
            ->select('customers.*')
            ->withCount(['orders as period_orders' => fn ($q) => $q->whereBetween('created_at', [$start, $end])])
            ->withSum(['orders as period_spent' => fn ($q) => $q->whereBetween('created_at', [$start, $end])], 'total')
            ->having('period_orders', '>', 0)
            ->orderByDesc('period_spent')
            ->limit($limit)
            ->get()
            ->map(fn (Customer $c) => [
                'id'       => $c->id,
                'name'     => $c->name,
                'initials' => $c->initials,
                'orders'   => (int) $c->period_orders,
                'spent'    => (float) ($c->period_spent ?? 0),
            ])
            ->all();
    }

    /**
     * Per-tailor throughput and on-time completion rate.
     */
    public static function tailorPerformance(Carbon $start, Carbon $end, int $limit = 4): array
    {
        $rows = Order::query()
            ->join('staff', 'orders.staff_id', '=', 'staff.id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->selectRaw('staff.id, staff.name, COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN orders.status IN ('Delivered','Completed') AND (orders.delivered_at IS NULL OR orders.delivery_date IS NULL OR orders.delivered_at <= orders.delivery_date) THEN 1 ELSE 0 END) as on_time")
            ->groupBy('staff.id', 'staff.name')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'name'   => $r->name,
            'orders' => (int) $r->total,
            'rate'   => $r->total > 0 ? (int) round($r->on_time / $r->total * 100) : 0,
        ])->all();
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{value: float, direction: string}
     */
    public static function delta(float|int $current, float|int $previous): array
    {
        if ($previous == 0) {
            return [
                'value'     => $current > 0 ? 100.0 : 0.0,
                'direction' => $current > 0 ? 'up' : 'flat',
            ];
        }

        $change = (($current - $previous) / abs($previous)) * 100;

        return [
            'value'     => round(abs($change), 1),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat'),
        ];
    }

    private static function progressAgainst(float $value, float $max): int
    {
        if ($max <= 0) {
            return 0;
        }

        return (int) min(round($value / $max * 100), 100);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolveRange(string $range, ?Carbon $from = null, ?Carbon $to = null): array
    {
        if ($from && $to) {
            return [$from->copy()->startOfDay(), $to->copy()->endOfDay()];
        }

        return match ($range) {
            'today'     => [now()->startOfDay(), now()->endOfDay()],
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'week'      => [now()->startOfWeek(), now()->endOfWeek()],
            'year'      => [now()->startOfYear(), now()->endOfYear()],
            default     => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private static function previousRange(Carbon $start, Carbon $end): array
    {
        $length = max((int) $start->diffInDays($end), 0);

        return [
            $start->copy()->subDays($length + 1)->startOfDay(),
            $start->copy()->subDay()->endOfDay(),
        ];
    }
}
