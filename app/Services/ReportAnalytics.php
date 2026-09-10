<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The analytical layer behind the reports screen.
 *
 * StatsService answers "what were the headline numbers"; this class answers the
 * questions an owner actually asks next — how does that compare with last
 * period, are we on target, who owes us money and for how long, are customers
 * coming back, and are garments leaving on time. Everything is derived from one
 * date window so the screen, the JSON endpoint and the PDF all agree.
 */
class ReportAnalytics
{
    /** Buckets used for receivables ageing, in days. */
    public const AGE_BUCKETS = [
        ['label' => '0 – 30 days',  'min' => 0,  'max' => 30],
        ['label' => '31 – 60 days', 'min' => 31, 'max' => 60],
        ['label' => '61 – 90 days', 'min' => 61, 'max' => 90],
        ['label' => 'Over 90 days', 'min' => 91, 'max' => null],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function build(Carbon $start, Carbon $end): array
    {
        $bundle = [
            'window'     => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
                'days'  => max((int) $start->diffInDays($end) + 1, 1),
            ],
            'comparison' => self::comparison($start, $end),
            'target'     => self::target($start, $end),
            'dues'       => self::dues(),
            'retention'  => self::retention($start, $end),
            'delivery'   => self::delivery($start, $end),
            'weekdays'   => self::weekdays($start, $end),
            'garments'   => self::garments($start, $end),
        ];

        $bundle['insights'] = self::insights($bundle);

        return $bundle;
    }

    /* ------------------------------------------------------------------ */
    /*  This period vs the one before it                                   */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function comparison(Carbon $start, Carbon $end): array
    {
        [$prevStart, $prevEnd] = self::previousWindow($start, $end);

        $now  = self::snapshot($start, $end);
        $then = self::snapshot($prevStart, $prevEnd);

        $rows = [
            ['key' => 'revenue',    'label' => 'Revenue collected', 'format' => 'money',  'higher_is_better' => true],
            ['key' => 'invoiced',   'label' => 'Value invoiced',    'format' => 'money',  'higher_is_better' => true],
            ['key' => 'orders',     'label' => 'Orders taken',      'format' => 'number', 'higher_is_better' => true],
            ['key' => 'delivered',  'label' => 'Orders delivered',  'format' => 'number', 'higher_is_better' => true],
            ['key' => 'avg_order',  'label' => 'Average order',     'format' => 'money',  'higher_is_better' => true],
            ['key' => 'collection', 'label' => 'Collection rate',   'format' => 'percent', 'higher_is_better' => true],
            ['key' => 'expenses',   'label' => 'Expenses',          'format' => 'money',  'higher_is_better' => false],
            ['key' => 'net',        'label' => 'Net (collected − expenses)', 'format' => 'money', 'higher_is_better' => true],
            ['key' => 'new_customers', 'label' => 'New customers',  'format' => 'number', 'higher_is_better' => true],
        ];

        return array_map(function (array $row) use ($now, $then) {
            $current  = $now[$row['key']];
            $previous = $then[$row['key']];

            return $row + [
                'current'  => $current,
                'previous' => $previous,
                'change'   => $current - $previous,
                'delta'    => StatsService::delta($current, $previous),
            ];
        }, $rows);
    }

    /**
     * @return array<string, float|int>
     */
    private static function snapshot(Carbon $start, Carbon $end): array
    {
        $revenue  = (float) Payment::where('status','Completed')->whereNull('reverses_payment_id')->whereBetween('date', [$start, $end])->sum('amount');
        $expenses = (float) Expense::whereBetween('date', [$start, $end])->sum('amount');

        $orderAgg = Order::whereBetween('created_at', [$start, $end])
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as invoiced')
            ->first();

        $orders   = (int) ($orderAgg->orders ?? 0);
        $invoiced = (float) ($orderAgg->invoiced ?? 0);

        $delivered = Order::whereIn('status', ['Delivered', 'Completed'])
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        return [
            'revenue'       => $revenue,
            'invoiced'      => $invoiced,
            'orders'        => $orders,
            'delivered'     => $delivered,
            'avg_order'     => $orders > 0 ? round($invoiced / $orders, 2) : 0.0,
            'collection'    => $invoiced > 0 ? min(round($revenue / $invoiced * 100, 1), 100) : 0.0,
            'expenses'      => $expenses,
            'net'           => $revenue - $expenses,
            'new_customers' => Customer::whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Revenue target                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Progress against the monthly revenue goal, scaled to the window being
     * viewed so a single day is judged against a single day's share of it.
     *
     * @return array<string, mixed>
     */
    public static function target(Carbon $start, Carbon $end): array
    {
        $monthly = (float) Settings::get('monthly_revenue_target', 0);
        $days    = max((int) $start->diffInDays($end) + 1, 1);
        $inMonth = max($start->copy()->daysInMonth, 1);

        $scaled   = $monthly > 0 ? $monthly / $inMonth * $days : 0.0;
        $achieved = (float) Payment::where('status','Completed')->whereNull('reverses_payment_id')->whereBetween('date', [$start, $end])->sum('amount');

        // How far through the window we are — a month at 40% on day 12 is fine,
        // the same 40% on day 28 is not, and the report should say so.
        $today    = now();
        $elapsed  = $today->lt($start) ? 0 : ($today->gt($end) ? $days : (int) $start->diffInDays($today) + 1);
        $elapsed  = max(min($elapsed, $days), 0);
        $expected = $days > 0 ? $scaled / $days * $elapsed : 0.0;

        return [
            'monthly'       => $monthly,
            'configured'    => $monthly > 0,
            'target'        => round($scaled, 2),
            'achieved'      => $achieved,
            'remaining'     => max(round($scaled - $achieved, 2), 0),
            'percent'       => $scaled > 0 ? min(round($achieved / $scaled * 100, 1), 999) : 0.0,
            'expected'      => round($expected, 2),
            'pace_percent'  => $scaled > 0 ? min(round($expected / $scaled * 100, 1), 100) : 0.0,
            'on_track'      => $scaled <= 0 || $achieved >= $expected,
            'days'          => $days,
            'days_elapsed'  => $elapsed,
            'days_left'     => max($days - $elapsed, 0),
            'daily_needed'  => ($days - $elapsed) > 0 ? round(max($scaled - $achieved, 0) / ($days - $elapsed), 2) : 0.0,
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Receivables ageing                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Outstanding balances are a running position, not a period figure, so
     * this is deliberately a snapshot of everything still unpaid today.
     *
     * @return array<string, mixed>
     */
    public static function dues(): array
    {
        $rows = Order::query()
            ->where('balance', '>', 0)
            ->whereNotIn('status', ['Cancelled'])
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->select(
                'orders.id',
                'orders.order_number',
                'orders.balance',
                'orders.total',
                'orders.status',
                'orders.created_at',
                'orders.delivery_date',
                'customers.name as customer_name',
                'customers.phone as customer_phone'
            )
            ->orderByDesc('orders.balance')
            ->get();

        $today   = now()->startOfDay();
        $buckets = [];

        foreach (self::AGE_BUCKETS as $bucket) {
            $buckets[$bucket['label']] = ['label' => $bucket['label'], 'orders' => 0, 'amount' => 0.0];
        }

        $byCustomer = [];
        $oldestDays = 0;

        foreach ($rows as $row) {
            $age = (int) Carbon::parse($row->created_at)->startOfDay()->diffInDays($today);
            $oldestDays = max($oldestDays, $age);

            foreach (self::AGE_BUCKETS as $bucket) {
                if ($age >= $bucket['min'] && ($bucket['max'] === null || $age <= $bucket['max'])) {
                    $buckets[$bucket['label']]['orders']++;
                    $buckets[$bucket['label']]['amount'] += (float) $row->balance;
                    break;
                }
            }

            $name = $row->customer_name ?: 'Walk-in';
            $byCustomer[$name] ??= ['name' => $name, 'phone' => $row->customer_phone, 'orders' => 0, 'amount' => 0.0, 'oldest' => 0];
            $byCustomer[$name]['orders']++;
            $byCustomer[$name]['amount'] += (float) $row->balance;
            $byCustomer[$name]['oldest'] = max($byCustomer[$name]['oldest'], $age);
        }

        usort($byCustomer, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $total = array_sum(array_column($buckets, 'amount'));

        return [
            'total'       => $total,
            'orders'      => $rows->count(),
            'oldest_days' => $oldestDays,
            'buckets'     => array_values($buckets),
            'customers'   => array_slice($byCustomer, 0, 25),
            'over_60'     => array_sum(array_map(
                fn ($b) => str_contains($b['label'], '61') || str_contains($b['label'], '90') ? $b['amount'] : 0,
                array_values($buckets)
            )),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Customer retention                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    public static function retention(Carbon $start, Carbon $end): array
    {
        $rows = Order::query()
            ->whereBetween('orders.created_at', [$start, $end])
            ->whereNotNull('customer_id')
            ->leftJoin('customers', 'orders.customer_id', '=', 'customers.id')
            ->selectRaw('orders.customer_id, COUNT(*) as orders, COALESCE(SUM(orders.total), 0) as spent, MIN(customers.created_at) as joined_at')
            ->groupBy('orders.customer_id')
            ->get();

        $active   = $rows->count();
        $returning = 0;
        $multi     = 0;

        foreach ($rows as $row) {
            if ($row->joined_at && Carbon::parse($row->joined_at)->lt($start)) {
                $returning++;
            }
            if ((int) $row->orders > 1) {
                $multi++;
            }
        }

        $totalOrders = (int) $rows->sum('orders');
        $totalSpent  = (float) $rows->sum('spent');

        return [
            'active'            => $active,
            'returning'         => $returning,
            'new'               => $active - $returning,
            'return_rate'       => $active > 0 ? round($returning / $active * 100, 1) : 0.0,
            'repeat_buyers'     => $multi,
            'repeat_rate'       => $active > 0 ? round($multi / $active * 100, 1) : 0.0,
            'orders_per_client' => $active > 0 ? round($totalOrders / $active, 2) : 0.0,
            'value_per_client'  => $active > 0 ? round($totalSpent / $active, 2) : 0.0,
            'lapsed_90'         => Customer::whereDoesntHave('orders', fn ($q) => $q->where('created_at', '>=', now()->subDays(90)))->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Workshop delivery performance                                      */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<string, mixed>
     */
    public static function delivery(Carbon $start, Carbon $end): array
    {
        $delivered = Order::query()
            ->whereIn('status', ['Delivered', 'Completed'])
            ->whereBetween('updated_at', [$start, $end])
            ->select('created_at', 'delivery_date', 'delivered_at', 'updated_at')
            ->get();

        $turnarounds = [];
        $onTime      = 0;
        $measured    = 0;

        foreach ($delivered as $order) {
            $out = $order->delivered_at ?: $order->updated_at;

            if ($out) {
                $turnarounds[] = max((float) Carbon::parse($order->created_at)->diffInDays(Carbon::parse($out)), 0);
            }

            if ($order->delivery_date) {
                $measured++;
                if (!$out || Carbon::parse($out)->lte(Carbon::parse($order->delivery_date)->endOfDay())) {
                    $onTime++;
                }
            }
        }

        $open = Order::whereIn('status', Order::OPEN_STATUSES);

        return [
            'delivered'    => $delivered->count(),
            'avg_days'     => $turnarounds ? round(array_sum($turnarounds) / count($turnarounds), 1) : 0.0,
            'fastest_days' => $turnarounds ? round(min($turnarounds), 1) : 0.0,
            'slowest_days' => $turnarounds ? round(max($turnarounds), 1) : 0.0,
            'on_time'      => $onTime,
            'measured'     => $measured,
            'on_time_rate' => $measured > 0 ? round($onTime / $measured * 100, 1) : 0.0,
            'open_orders'  => (clone $open)->count(),
            'overdue'      => (clone $open)->whereNotNull('delivery_date')->whereDate('delivery_date', '<', now()->toDateString())->count(),
            'due_today'    => (clone $open)->whereDate('delivery_date', now()->toDateString())->count(),
            'due_7_days'   => (clone $open)->whereBetween('delivery_date', [now()->startOfDay(), now()->addDays(7)->endOfDay()])->count(),
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  When the shop is busy                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function weekdays(Carbon $start, Carbon $end): array
    {
        $rows = Order::query()
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DAYOFWEEK(created_at) as dow, COUNT(*) as orders, COALESCE(SUM(total), 0) as value')
            ->groupBy('dow')
            ->get()
            ->keyBy('dow');

        // MySQL's DAYOFWEEK is 1 = Sunday; the shop week reads better from Monday.
        $order = [2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday', 5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday', 1 => 'Sunday'];
        $out   = [];

        foreach ($order as $dow => $label) {
            $row = $rows->get($dow);

            $out[] = [
                'day'    => $label,
                'short'  => substr($label, 0, 3),
                'orders' => (int) ($row->orders ?? 0),
                'value'  => (float) ($row->value ?? 0),
            ];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */
    /*  What is being stitched                                             */
    /* ------------------------------------------------------------------ */

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function garments(Carbon $start, Carbon $end, int $limit = 12): array
    {
        $groups = [];
        foreach (Order::with('lineItems')->whereBetween('created_at', [$start,$end])->get() as $order) {
            foreach (PricingService::allocatedItems($order) as $item) {
                $name = $item['name'];
                $groups[$name] ??= ['garment' => $name, 'ids' => [], 'quantity' => 0, 'value' => '0.00'];
                $groups[$name]['ids'][$order->id] = true;
                $groups[$name]['quantity'] += $item['qty'];
                $groups[$name]['value'] = Decimal::add($groups[$name]['value'], $item['revenue']);
            }
        }
        return collect($groups)->map(fn($g) => ['garment' => $g['garment'], 'orders' => count($g['ids']), 'quantity' => $g['quantity'],
            'value' => (float)$g['value'], 'avg' => round((float)$g['value']/count($g['ids']),2)])->sortByDesc('value')->take($limit)->values()->all();
    }

    /* ------------------------------------------------------------------ */
    /*  Written read-out                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Turns the numbers above into the handful of sentences someone would say
     * out loud about the period. Each line names the figure it is based on, so
     * nothing here has to be taken on trust.
     *
     * @param  array<string, mixed>  $bundle
     * @return array<int, array{tone: string, text: string}>
     */
    public static function insights(array $bundle): array
    {
        $out  = [];
        $cur  = Settings::currency();
        $money = fn ($v) => $cur . number_format((float) $v);

        $byKey = [];
        foreach ($bundle['comparison'] as $row) {
            $byKey[$row['key']] = $row;
        }

        // 1. Revenue direction
        if (isset($byKey['revenue'])) {
            $r = $byKey['revenue'];
            if ($r['previous'] <= 0 && $r['current'] > 0) {
                $out[] = ['tone' => 'good', 'text' => 'Collected ' . $money($r['current']) . ' this period, against nothing in the previous one.'];
            } elseif ($r['delta']['direction'] === 'up') {
                $out[] = ['tone' => 'good', 'text' => 'Collections are up ' . $r['delta']['value'] . '% on the previous period — ' . $money($r['current']) . ' against ' . $money($r['previous']) . '.'];
            } elseif ($r['delta']['direction'] === 'down') {
                $out[] = ['tone' => 'warn', 'text' => 'Collections are down ' . $r['delta']['value'] . '% on the previous period — ' . $money($r['current']) . ' against ' . $money($r['previous']) . '.'];
            }
        }

        // 2. Target pace
        $t = $bundle['target'];
        if ($t['configured']) {
            if ($t['percent'] >= 100) {
                $out[] = ['tone' => 'good', 'text' => 'Target met: ' . $money($t['achieved']) . ' of ' . $money($t['target']) . ' (' . $t['percent'] . '%).'];
            } elseif ($t['on_track']) {
                $out[] = ['tone' => 'good', 'text' => 'On pace for the target — ' . $t['percent'] . '% collected with ' . $t['days_left'] . ' day(s) to run.'];
            } else {
                $out[] = ['tone' => 'warn', 'text' => 'Behind pace: ' . $t['percent'] . '% of target with ' . $t['days_left'] . ' day(s) left. Needs about ' . $money($t['daily_needed']) . ' a day to close the gap.'];
            }
        } else {
            $out[] = ['tone' => 'flat', 'text' => 'No monthly revenue target set yet — set one to track pace against it.'];
        }

        // 3. Money still owed
        $d = $bundle['dues'];
        if ($d['total'] > 0) {
            $tone = $d['over_60'] > 0 ? 'warn' : 'flat';
            $text = $money($d['total']) . ' is outstanding across ' . $d['orders'] . ' order(s)';
            if ($d['over_60'] > 0) {
                $text .= ', of which ' . $money($d['over_60']) . ' is more than 60 days old';
            }
            $out[] = ['tone' => $tone, 'text' => $text . '.'];
        } else {
            $out[] = ['tone' => 'good', 'text' => 'Nothing outstanding — every order is fully paid.'];
        }

        // 4. Collection efficiency
        if (isset($byKey['collection']) && $byKey['collection']['current'] > 0) {
            $rate = $byKey['collection']['current'];
            $out[] = [
                'tone' => $rate >= 85 ? 'good' : ($rate >= 60 ? 'flat' : 'warn'),
                'text' => 'Collection rate is ' . $rate . '% of what was invoiced this period.',
            ];
        }

        // 5. Delivery reliability
        $del = $bundle['delivery'];
        if ($del['measured'] > 0) {
            $out[] = [
                'tone' => $del['on_time_rate'] >= 90 ? 'good' : ($del['on_time_rate'] >= 75 ? 'flat' : 'warn'),
                'text' => $del['on_time_rate'] . '% of deliveries met their promised date, averaging ' . $del['avg_days'] . ' day(s) from order to hand-over.',
            ];
        }
        if ($del['overdue'] > 0) {
            $out[] = ['tone' => 'warn', 'text' => $del['overdue'] . ' open order(s) are past their delivery date right now.'];
        }

        // 6. Who is buying
        $ret = $bundle['retention'];
        if ($ret['active'] > 0) {
            $out[] = [
                'tone' => $ret['return_rate'] >= 50 ? 'good' : 'flat',
                'text' => $ret['active'] . ' customer(s) ordered this period — ' . $ret['returning'] . ' returning, ' . $ret['new'] . ' new (' . $ret['return_rate'] . '% returning).',
            ];
        }

        // 7. What sells
        if (!empty($bundle['garments'])) {
            $top = $bundle['garments'][0];
            $out[] = ['tone' => 'flat', 'text' => 'Biggest earner: ' . $top['garment'] . ' — ' . $top['orders'] . ' order(s) worth ' . $money($top['value']) . '.'];
        }

        // 8. Busiest day
        $busiest = null;
        foreach ($bundle['weekdays'] as $day) {
            if ($busiest === null || $day['orders'] > $busiest['orders']) {
                $busiest = $day;
            }
        }
        if ($busiest && $busiest['orders'] > 0) {
            $out[] = ['tone' => 'flat', 'text' => $busiest['day'] . ' is the busiest day, taking ' . $busiest['orders'] . ' order(s) in this window.'];
        }

        // 9. Cost of running the shop
        if (isset($byKey['expenses'], $byKey['revenue']) && $byKey['revenue']['current'] > 0) {
            $ratio = round($byKey['expenses']['current'] / $byKey['revenue']['current'] * 100, 1);
            $out[] = [
                'tone' => $ratio > 70 ? 'warn' : 'flat',
                'text' => 'Expenses absorbed ' . $ratio . '% of everything collected, leaving ' . $money($byKey['net']['current'] ?? 0) . ' net.',
            ];
        }

        return $out;
    }

    /* ------------------------------------------------------------------ */

    /**
     * The window of identical length ending the day before this one starts.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function previousWindow(Carbon $start, Carbon $end): array
    {
        $length = max((int) $start->diffInDays($end), 0);

        return [
            $start->copy()->subDays($length + 1)->startOfDay(),
            $start->copy()->subDay()->endOfDay(),
        ];
    }
}
