<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Money;
use App\Services\PdfExporter;
use App\Services\ReportAnalytics;
use App\Services\Settings;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Ranges the report screen and every export understand. */
    public const RANGES = ['today', 'yesterday', 'week', 'month', 'year', 'custom'];

    public function index(Request $request)
    {
        [$range, $from, $to] = $this->resolveInput($request);

        $report        = StatsService::reports($range, $from, $to);
        [$start, $end] = StatsService::resolveRange($range, $from, $to);

        $analytics = ReportAnalytics::build($start, $end);
        $canSetTarget = (bool) optional($request->user())->isAdmin();

        return view('reports.index', compact('report', 'range', 'analytics', 'canSetTarget'));
    }

    /**
     * Same payload as the page, fetched when the user switches range.
     */
    public function data(Request $request): JsonResponse
    {
        [$range, $from, $to] = $this->resolveInput($request);
        [$start, $end]       = StatsService::resolveRange($range, $from, $to);

        return response()->json([
            'success'   => true,
            'report'    => StatsService::reports($range, $from, $to),
            'analytics' => ReportAnalytics::build($start, $end),
        ]);
    }

    /**
     * The full report as a downloadable PDF.
     *
     * Reads the same range the screen is showing, so whatever the user has
     * filtered to — a single day, a custom window, the whole year — is exactly
     * what lands in the file.
     */
    public function pdf(Request $request): Response
    {
        [$range, $from, $to] = $this->resolveInput($request);

        $report        = StatsService::reports($range, $from, $to);
        [$start, $end] = StatsService::resolveRange($range, $from, $to);

        $data = array_merge(
            [
                'report'            => $report,
                'range'             => $range,
                'finance'           => $this->finance($start, $end),
                'paymentMethods'    => $this->paymentMethods($start, $end),
                'expenseCategories' => $this->expenseCategories($start, $end),
                'topCustomers'      => $this->customers($start, $end),
                'tailors'           => StatsService::tailorPerformance($start, $end, 50),
                'analytics'         => ReportAnalytics::build($start, $end),
            ],
            PdfExporter::documentMeta(
                'Business Report',
                $this->rangeLabel($range, $start, $end),
                $this->filterLabel($range)
            )
        );

        return PdfExporter::download(
            'reports.pdf',
            $data,
            'tailoring-report-' . $start->toDateString() . '-to-' . $end->toDateString() . '.pdf'
        );
    }

    /**
     * CSV export of the current report window.
     */
    public function export(Request $request): StreamedResponse
    {
        [$range, $from, $to] = $this->resolveInput($request);

        $report   = StatsService::reports($range, $from, $to);
        $filename = "report-{$report['start']}-to-{$report['end']}.csv";

        return response()->streamDownload(function () use ($report) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Report Period', $report['label']]);
            fputcsv($handle, []);

            fputcsv($handle, ['Metric', 'Value', 'Change vs previous period']);
            fputcsv($handle, ['Total Revenue', Money::format($report['kpis']['revenue']['value']), $this->deltaLabel($report['kpis']['revenue']['delta'])]);
            fputcsv($handle, ['Orders Completed', $report['kpis']['completed']['value'], $this->deltaLabel($report['kpis']['completed']['delta'])]);
            fputcsv($handle, ['Avg. Order Value', Money::format($report['kpis']['avg_order']['value']), $this->deltaLabel($report['kpis']['avg_order']['delta'])]);
            fputcsv($handle, ['Collection Rate', $report['kpis']['collection_rate']['value'] . '%', $this->deltaLabel($report['kpis']['collection_rate']['delta'])]);

            fputcsv($handle, []);
            fputcsv($handle, ['Revenue Trend']);
            fputcsv($handle, ['Period', 'Revenue']);
            foreach ($report['revenue_series']['labels'] as $i => $label) {
                fputcsv($handle, [$label, $report['revenue_series']['data'][$i] ?? 0]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Sales by Category']);
            fputcsv($handle, ['Category', 'Orders']);
            foreach ($report['category_series']['labels'] as $i => $label) {
                fputcsv($handle, [$label, $report['category_series']['data'][$i] ?? 0]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Order Status Breakdown']);
            fputcsv($handle, ['Status', 'Orders']);
            foreach ($report['status_series']['labels'] as $i => $label) {
                fputcsv($handle, [$label, $report['status_series']['data'][$i] ?? 0]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Top Customers']);
            fputcsv($handle, ['Customer', 'Orders', 'Spend']);
            foreach ($report['top_customers'] as $c) {
                fputcsv($handle, [$c['name'], $c['orders'], $c['spent']]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Tailor Performance']);
            fputcsv($handle, ['Tailor', 'Orders', 'On-time %']);
            foreach ($report['tailors'] as $t) {
                fputcsv($handle, [$t['name'], $t['orders'], $t['rate']]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /* ------------------------------------------------------------------ */
    /*  Detail tables                                                      */
    /* ------------------------------------------------------------------ */

    /** Columns each table may be sorted by, and the SQL behind them. */
    private const SORTABLE = [
        'orders' => [
            'created_at'    => 'orders.created_at',
            'order_number'  => 'orders.order_number',
            'customer'      => 'customers.name',
            'total'         => 'orders.total',
            'balance'       => 'orders.balance',
            'status'        => 'orders.status',
            'delivery_date' => 'orders.delivery_date',
        ],
        'payments' => [
            'date'     => 'payments.date',
            'amount'   => 'payments.amount',
            'customer' => 'customers.name',
            'method'   => 'payments.payment_method',
        ],
        'expenses' => [
            'date'     => 'expenses.date',
            'amount'   => 'expenses.amount',
            'category' => 'expenses.category',
            'vendor'   => 'expenses.vendor',
        ],
        'dues' => [
            'balance'    => 'orders.balance',
            'created_at' => 'orders.created_at',
            'customer'   => 'customers.name',
            'total'      => 'orders.total',
        ],
    ];

    /**
     * Row-level data behind the report, paged and searched server-side.
     *
     * The summary cards answer "how much"; this answers "which ones" without
     * ever shipping a whole year of rows to the browser.
     */
    public function table(Request $request): JsonResponse
    {
        $params = $request->validate([
            'type' => ['required', 'in:orders,payments,expenses,dues'],
            'q'    => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', 'string', 'max:30'],
            'dir'  => ['nullable', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per'  => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        [$range, $from, $to] = $this->resolveInput($request);
        [$start, $end]       = StatsService::resolveRange($range, $from, $to);

        $type = $params['type'];
        $term = trim($params['q'] ?? '');
        $per  = (int) ($params['per'] ?? 15);
        $page = (int) ($params['page'] ?? 1);
        $dir  = $params['dir'] ?? ($type === 'dues' ? 'desc' : 'desc');

        $sortKey = $params['sort'] ?? match ($type) {
            'payments', 'expenses' => 'date',
            'dues'                 => 'balance',
            default                => 'created_at',
        };

        $column = self::SORTABLE[$type][$sortKey] ?? array_values(self::SORTABLE[$type])[0];

        $query = match ($type) {
            'orders'   => $this->ordersQuery($start, $end, $term),
            'payments' => $this->paymentsQuery($start, $end, $term),
            'expenses' => $this->expensesQuery($start, $end, $term),
            'dues'     => $this->duesQuery($term),
        };

        $total = (clone $query)->count();
        $pages = max((int) ceil($total / $per), 1);
        $page  = min($page, $pages);

        // toBase() drops down to the query builder so each row arrives as a
        // plain object. Casting a hydrated Eloquent model with (array) yields
        // its protected properties, not its attributes, which is why every
        // column serialised as null before this.
        $rows = $query->toBase()
            ->orderBy($column, $dir)
            ->forPage($page, $per)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return response()->json([
            'success' => true,
            'rows'    => $rows,
            'meta'    => [
                'type'  => $type,
                'page'  => $page,
                'pages' => $pages,
                'total' => $total,
                'per'   => $per,
                'sort'  => $sortKey,
                'dir'   => $dir,
                'from'  => $total ? ($page - 1) * $per + 1 : 0,
                'to'    => min($page * $per, $total),
            ],
        ]);
    }

    private function ordersQuery(Carbon $start, Carbon $end, string $term)
    {
        return Order::query()
            ->whereBetween('orders.created_at', [$start, $end])
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->leftJoin('staff as tailors', 'orders.staff_id', '=', 'tailors.id')
            ->when($term !== '', fn ($q) => $q->where(function ($sub) use ($term) {
                $sub->where('orders.order_number', 'like', "%{$term}%")
                    ->orWhere('orders.invoice_number', 'like', "%{$term}%")
                    ->orWhere('orders.garment', 'like', "%{$term}%")
                    ->orWhere('orders.status', 'like', "%{$term}%")
                    ->orWhere('customers.name', 'like', "%{$term}%");
            }))
            ->select(
                'orders.id',
                'orders.order_number',
                'orders.created_at',
                'orders.garment',
                'orders.status',
                'orders.total',
                'orders.advance',
                'orders.balance',
                'orders.delivery_date',
                'customers.name as customer',
                'customers.phone as phone',
                'tailors.name as tailor'
            );
    }

    private function paymentsQuery(Carbon $start, Carbon $end, string $term)
    {
        return Payment::query()->where('status','Completed')->whereNull('reverses_payment_id')
            ->whereBetween('payments.date', [$start, $end])
            ->leftJoin('customers', 'payments.customer_id', '=', 'customers.id')
            ->leftJoin('orders', 'payments.order_id', '=', 'orders.id')
            ->when($term !== '', fn ($q) => $q->where(function ($sub) use ($term) {
                $sub->where('payments.invoice_id', 'like', "%{$term}%")
                    ->orWhere('payments.reference', 'like', "%{$term}%")
                    ->orWhere('payments.payment_method', 'like', "%{$term}%")
                    ->orWhere('orders.order_number', 'like', "%{$term}%")
                    ->orWhere('customers.name', 'like', "%{$term}%");
            }))
            ->select(
                'payments.id',
                'payments.date',
                'payments.amount',
                'payments.payment_method as method',
                'payments.status',
                'payments.reference',
                'payments.invoice_id',
                'orders.order_number',
                'customers.name as customer'
            );
    }

    private function expensesQuery(Carbon $start, Carbon $end, string $term)
    {
        return Expense::query()
            ->whereBetween('expenses.date', [$start, $end])
            ->when($term !== '', fn ($q) => $q->where(function ($sub) use ($term) {
                $sub->where('expenses.description', 'like', "%{$term}%")
                    ->orWhere('expenses.category', 'like', "%{$term}%")
                    ->orWhere('expenses.vendor', 'like', "%{$term}%")
                    ->orWhere('expenses.reference', 'like', "%{$term}%");
            }))
            ->select(
                'expenses.id',
                'expenses.date',
                'expenses.description',
                'expenses.category',
                'expenses.vendor',
                'expenses.payment_method as method',
                'expenses.amount'
            );
    }

    /**
     * Unpaid balances are a running position rather than a period figure, so
     * this list deliberately ignores the selected window.
     */
    private function duesQuery(string $term)
    {
        return Order::query()
            ->where('orders.balance', '>', 0)
            ->whereNotIn('orders.status', ['Cancelled'])
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->when($term !== '', fn ($q) => $q->where(function ($sub) use ($term) {
                $sub->where('orders.order_number', 'like', "%{$term}%")
                    ->orWhere('customers.name', 'like', "%{$term}%")
                    ->orWhere('customers.phone', 'like', "%{$term}%");
            }))
            ->select(
                'orders.id',
                'orders.order_number',
                'orders.created_at',
                'orders.status',
                'orders.total',
                'orders.balance',
                'orders.delivery_date',
                'customers.name as customer',
                'customers.phone as phone'
            );
    }

    /* ------------------------------------------------------------------ */
    /*  Revenue target                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Stores the monthly revenue goal the report measures pace against.
     */
    public function target(Request $request): JsonResponse
    {
        if (!optional($request->user())->isAdmin()) {
            return response()->json([
                'message' => 'Only an administrator can change the revenue target.',
            ], 403);
        }

        $validated = $request->validate([
            'monthly_revenue_target' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);

        Settings::put(['monthly_revenue_target' => $validated['monthly_revenue_target']]);

        [$range, $from, $to] = $this->resolveInput($request);
        [$start, $end]       = StatsService::resolveRange($range, $from, $to);

        return response()->json([
            'success' => true,
            'message' => 'Revenue target saved.',
            'target'  => ReportAnalytics::target($start, $end),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /*  PDF data                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Invoiced vs collected vs spent for the window.
     *
     * @return array<string, float|int>
     */
    private function finance(Carbon $start, Carbon $end): array
    {
        $orders   = Order::whereBetween('created_at', [$start, $end]);
        $invoiced = (float) (clone $orders)->sum('total');

        $collected = (float) Payment::where('status','Completed')->whereNull('reverses_payment_id')->whereBetween('date', [$start, $end])->sum('amount');
        $expenses  = (float) Expense::whereBetween('date', [$start, $end])->sum('amount');

        return [
            'orders'      => (int) (clone $orders)->count(),
            'invoiced'    => $invoiced,
            'collected'   => $collected,
            'outstanding' => max($invoiced - $collected, 0),
            'expenses'    => $expenses,
            'net'         => $collected - $expenses,
        ];
    }

    /**
     * @return array<int, array{method: string, count: int, total: float}>
     */
    private function paymentMethods(Carbon $start, Carbon $end): array
    {
        return Payment::query()->where('status','Completed')->whereNull('reverses_payment_id')
            ->whereBetween('date', [$start, $end])
            ->selectRaw("COALESCE(NULLIF(payment_method, ''), 'Unspecified') as method_label")
            ->selectRaw('COUNT(*) as entries, SUM(amount) as total')
            ->groupBy('method_label')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'method' => (string) $r->method_label,
                'count'  => (int) $r->entries,
                'total'  => (float) $r->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array{category: string, count: int, total: float}>
     */
    private function expenseCategories(Carbon $start, Carbon $end): array
    {
        return Expense::query()
            ->whereBetween('date', [$start, $end])
            ->selectRaw("COALESCE(NULLIF(category, ''), 'Uncategorised') as category_label")
            ->selectRaw('COUNT(*) as entries, SUM(amount) as total')
            ->groupBy('category_label')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'category' => (string) $r->category_label,
                'count'    => (int) $r->entries,
                'total'    => (float) $r->total,
            ])
            ->all();
    }

    /**
     * Every customer who ordered in the window — the screen shows a top four,
     * the PDF is the place for the full list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function customers(Carbon $start, Carbon $end, int $limit = 100): array
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
                'name'   => $c->name,
                'phone'  => $c->phone,
                'orders' => (int) $c->period_orders,
                'spent'  => (float) ($c->period_spent ?? 0),
            ])
            ->all();
    }

    /* ------------------------------------------------------------------ */
    /*  Input                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{0: string, 1: ?Carbon, 2: ?Carbon}
     */
    private function resolveInput(Request $request): array
    {
        $validated = $request->validate([
            'range' => ['nullable', 'in:' . implode(',', self::RANGES)],
            'from'  => ['nullable', 'date'],
            'to'    => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $range = $validated['range'] ?? 'month';

        $from = !empty($validated['from']) ? Carbon::parse($validated['from']) : null;
        $to   = !empty($validated['to']) ? Carbon::parse($validated['to']) : null;

        // A custom range only makes sense with both ends supplied.
        if ($range === 'custom' && (!$from || !$to)) {
            $range = 'month';
            $from = $to = null;
        }

        // A named range owns its own dates; stray from/to would silently
        // override it and produce a file that disagrees with its own heading.
        if ($range !== 'custom') {
            $from = $to = null;
        }

        return [$range, $from, $to];
    }

    private function rangeLabel(string $range, Carbon $start, Carbon $end): string
    {
        if ($start->toDateString() === $end->toDateString()) {
            return $start->format('l, d M Y');
        }

        return $start->format('d M Y') . '  –  ' . $end->format('d M Y');
    }

    private function filterLabel(string $range): string
    {
        $names = [
            'today'     => 'Today',
            'yesterday' => 'Yesterday',
            'week'      => 'This week',
            'month'     => 'This month',
            'year'      => 'This year',
            'custom'    => 'Custom range',
        ];

        return 'Filter: ' . ($names[$range] ?? ucfirst($range));
    }

    private function deltaLabel(array $delta): string
    {
        $sign = match ($delta['direction']) {
            'up'   => '+',
            'down' => '-',
            default => '',
        };

        return $sign . $delta['value'] . '%';
    }
}
