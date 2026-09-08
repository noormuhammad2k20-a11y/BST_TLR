<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Order;
use App\Services\ActivityLogger;
use App\Services\OrderService;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class DeliveryController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index(Request $request)
    {
        $this->orders->flagOverdueOrders();
        $this->backfillMissingDeliveries();

        $deliveries = Delivery::query()
            ->with(['order.customer:id,name,phone,city,address'])
            ->join('orders', 'deliveries.order_id', '=', 'orders.id')
            ->orderByRaw('COALESCE(deliveries.delivery_date, orders.delivery_date) asc')
            ->select('deliveries.*')
            ->get()
            ->map(fn (Delivery $d) => $this->serialize($d));

        $stats = [
            'total'     => $deliveries->count(),
            'scheduled' => $deliveries->where('status', 'Scheduled')->count(),
            'ready'     => $deliveries->where('status', 'Ready')->count(),
            'delivered' => $deliveries->where('status', 'Delivered')->count(),
            'overdue'   => $deliveries->where('overdue', true)->count(),
            'dueToday'  => $deliveries->where('dueToday', true)->count(),
        ];

        $statuses = Delivery::STATUSES;

        // Same payload as JSON, so the page can refresh itself without a reload.
        if ($request->boolean('json') || $request->expectsJson()) {
            return response()->json(compact('deliveries', 'stats'));
        }

        return view('delivery.index', compact('deliveries', 'stats', 'statuses'));
    }

    /**
     * Move a delivery forward and keep the parent order in step.
     */
    public function updateStatus(Request $request, Delivery $delivery): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Delivery::STATUSES)],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);

        \Illuminate\Support\Facades\DB::transaction(function () use (&$delivery,$validated) {
            if ($delivery->order_id) \App\Models\Order::whereKey($delivery->order_id)->lockForUpdate()->firstOrFail();
            $delivery=Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
        $from = $delivery->status;

        $delivery->forceFill([
            'status'       => $validated['status'],
            'delivered_at' => $validated['status'] === 'Delivered' ? now() : null,
            'notes'        => $validated['note'] ?? $delivery->notes,
        ])->save();

        /*
         * Only "Delivered" travels back up to the order.
         *
         * Handing the garments over happens at this counter, so this page is the
         * right place to record it. "Ready", on the other hand, is earned on the
         * Orders page — the garments are verified and the customer is told, and
         * only then is the order Ready. Letting this page set it directly would
         * quietly skip the verification step and could tell a customer to come in
         * for clothes nobody has checked.
         */
        if ($delivery->order && $validated['status'] === 'Delivered' && $delivery->order->status !== 'Delivered') {
            $this->orders->changeStatus(
                $delivery->order,
                'Delivered',
                $validated['note'] ?? 'Collected by the customer'
            );
        }

        ActivityLogger::log(
            'Delivery updated',
            sprintf(
                '%s moved from %s to %s',
                $delivery->order?->display_number ?? 'Delivery',
                $from,
                $validated['status']
            ),
            'orders',
            $delivery,
            ['from' => $from, 'to' => $validated['status']],
            'status_changed'
        );

        StatsService::flush();

        });

        $delivery->load('order.customer');

        return response()->json([
            'success'  => true,
            'message'  => 'Delivery status updated.',
            'delivery' => $this->serialize($delivery),
        ]);
    }

    /**
     * Send the shop's collection notice to several customers at once.
     *
     * This is the whole point of the page: on a busy morning a member of staff
     * wants to tell everyone whose garments are on the shelf that they can come
     * in, without opening twenty orders one at a time.
     *
     * It deliberately does not move any order forward. An order reaches "Ready"
     * through the verification step on the Orders page, and the messages sent
     * from here are reminders and follow-ups for orders that are already there
     * — re-sending a reminder should never re-write history.
     */
    public function bulkNotify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_ids'   => ['required', 'array', 'min:1', 'max:200'],
            'delivery_ids.*' => ['integer', 'exists:deliveries,id'],
        ]);

        $deliveries = Delivery::with('order.customer:id,name,phone')
            ->whereIn('id', $validated['delivery_ids'])
            ->get()
            ->filter(fn (Delivery $d) => $d->order !== null && $d->order->status !== 'Delivered');

        if ($deliveries->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'None of the selected rows can be notified.',
                'sent'    => 0,
            ], 422);
        }

        $sent    = [];
        $failed  = [];

        foreach ($deliveries as $delivery) {
            $order  = $delivery->order;
            $result = \App\Services\CustomerNotificationDispatcher::dispatch('order-ready', $order);

            if (!$result['sent']) {
                $failed[] = [
                    'order'    => $order->display_number,
                    'customer' => $order->customer?->name ?? 'Unknown',
                    'reason'   => $result['error'] ?? 'The ORDER READY template is switched off in Settings.',
                ];
                continue;
            }

            $this->orders->markNotified($order);

            $sent[] = [
                'order'    => $order->display_number,
                'customer' => $order->customer?->name ?? 'Unknown',
            ];

        }

        ActivityLogger::log(
            'Collection notices sent',
            sprintf('%d customer(s) notified from the Delivery board', count($sent)),
            'orders',
            null,
            ['sent' => count($sent), 'failed' => count($failed)],
            'notified'
        );

        return response()->json([
            'success'  => true,
            'sent'     => count($sent),
            'failed'   => $failed,
            'results'  => $sent,
            'channels' => ['whatsapp', 'sms'],
            'message'  => $failed
                ? sprintf('%d notice(s) accepted, %d could not be sent.', count($sent), count($failed))
                : sprintf('%d customer notice(s) accepted for sending.', count($sent)),
        ]);
    }

    public function destroy(Delivery $delivery): JsonResponse
    {
        $label = $delivery->order?->display_number ?? 'Delivery';
        $delivery->delete();

        ActivityLogger::log('Deleted Delivery', "{$label} delivery record removed", 'orders', null, [], 'deleted');
        StatsService::flush();

        return response()->json(['success' => true, 'message' => 'Delivery record removed.']);
    }

    /* ------------------------------------------------------------------ */

    /**
     * Older orders may pre-date the deliveries table; create the missing rows
     * once so the page is complete without a manual migration step.
     *
     * Rate-limited: this used to run a `whereDoesntHave` scan plus up to 200
     * inserts on every single page load, which dominated the response time.
     * New orders create their delivery row through OrderService anyway, so this
     * only needs to catch historic gaps.
     */
    private function backfillMissingDeliveries(): void
    {
        if (Cache::has('deliveries.backfilled')) {
            return;
        }

        Cache::put('deliveries.backfilled', true, now()->addHours(6));

        Order::query()
            ->whereDoesntHave('delivery')
            ->with('customer:id,name,city,address')
            ->limit(200)
            ->get()
            ->each(function (Order $order) {
                Delivery::create([
                    'order_id'       => $order->id,
                    'status'         => match ($order->status) {
                        'Ready'                  => 'Ready',
                        'Delivered', 'Completed' => 'Delivered',
                        default                  => 'Scheduled',
                    },
                    'address'        => $order->customer?->address ?: $order->customer?->city,
                    'delivery_date'  => $order->delivery_date,
                    'delivered_at'   => $order->delivered_at,
                    'recipient_name' => $order->customer?->name,
                ]);
            });
    }

    private function serialize(Delivery $d): array
    {
        $order = $d->order;
        $date  = $d->delivery_date ?? $order?->delivery_date;

        // Legacy courier states are folded back onto the three the shop uses.
        $status = $d->shop_status;

        // Lateness is a property of the date, not a column somebody has to keep
        // up to date, so it is worked out on every read and can never be stale.
        $overdue  = $status !== 'Delivered' && $date && $date->isPast() && !$date->isToday();
        $dueToday = $status !== 'Delivered' && $date && $date->isToday();

        return [
            'db_id'     => $d->id,
            'order_id'  => $order?->id,
            'id'        => $order?->display_number ?? \App\Services\Settings::str('order_prefix') . $d->order_id,
            'cust'      => $order?->customer?->name ?? 'Unknown',
            'gmt'       => $order?->primary_item_name ?? 'Garment',
            'due'       => $date ? ($date->isToday() ? 'Today' : \App\Services\Dates::format($date)) : 'N/A',
            'dueDate'   => $date?->toIso8601String(),
            'status'    => $status,
            'overdue'   => (bool) $overdue,
            'dueToday'  => (bool) $dueToday,
            'phone'     => $order?->customer?->phone ?? '',
            'notified'  => $order?->notified_at !== null,
            'orderStatus' => $order?->status ?? '',
            'recipient' => $d->recipient_name ?: ($order?->customer?->name ?? ''),
            'amount'    => (float) ($order?->total ?? 0),
            'advance'   => (float) ($order?->advance ?? 0),
            'balance'   => (float) ($order?->balance ?? 0),
            'createdAt' => $order?->created_at?->toIso8601String(),
            'readyAt'   => $order?->completed_at?->toIso8601String(),
            'deliveredAt' => ($d->delivered_at ?? $order?->delivered_at)?->toIso8601String(),
            'notes'     => $d->notes ?? '',
        ];
    }
}
