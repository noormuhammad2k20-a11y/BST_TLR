<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\Customer;
use App\Models\Measurement;
use App\Models\Order;
use App\Models\ProductService;
use App\Models\Staff;
use App\Models\User;
use App\Services\Dates;
use App\Services\Money;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $service)
    {
    }

    public function index()
    {
        // Two rate-limited sweeps run off a page view, so the board is correct
        // even on a shop that never runs `schedule:work`:
        //   - the queue auto-start the orders screen counts down towards, and
        //   - the overdue alert, which reports lateness without rewriting the
        //     status (a garment can be late while still being stitched).
        $this->service->autoAdvanceOrders();
        $this->service->flagOverdueOrders();

        $orders = Order::query()
            ->with(['customer:id,name,phone,city', 'staff:id,name'])
            ->withPaymentTotals()
            ->withStageSince()
            ->latest()
            ->get()
            ->map(fn (Order $o) => $this->serialize($o));

        // Everything the create/edit wizard needs, in one pass.
        $customers = Customer::query()
            ->select('id', 'code', 'name', 'phone', 'email', 'type', 'city', 'notes', 'created_at')
            ->withCount('orders')
            ->withSum('orders as orders_total', 'total')
            ->with(['measurements' => fn ($q) => $q->select(
                array_merge(['id', 'customer_id', 'garment_type', 'unit', 'details', 'order_item_piece_id'], \App\Models\Measurement::FIELDS)
            )])
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $c) => [
                'db_id'        => $c->id,
                'id'           => $c->display_code,
                'name'         => $c->name,
                'phone'        => $c->phone,
                'email'        => $c->email,
                'type'         => $c->type,
                'city'         => $c->city,
                'orders'       => $c->orders_count,
                'spent'        => (float) ($c->orders_total ?? 0),
                'since'        => $c->created_at?->format('M Y'),
                'notes'        => $c->notes ?? '',
                'measurements' => $c->measurements->map(fn($m) => array_merge($m->toArray(), ['profile_key' => $m->piece?->profile['key'] ?? \App\Services\MeasurementProfiles::infer($m->garment_type)])),
            ]);

        $activeServices = ProductService::active()
            ->whereNull('canonical_id')
            ->orderBy('name')
            ->get();

        $activeServices->each(fn($service) => $service->setAttribute('profile', \App\Services\MeasurementProfiles::forProduct($service)));

        // Assignable people now come from the Staff module rather than from
        // login accounts, so a tailor who never signs in can still be assigned.
        $tailors = Staff::active()->orderBy('name')->get(['id', 'name', 'per_suit_rate']);

        $timeSlots = $this->timeSlots();

        $autoStatus = [
            'enabled'       => Settings::bool('auto_status_enabled'),
            'unit'          => Settings::str('auto_status_unit') === 'minutes' ? 'minutes' : 'hours',
            // Delay per status, so the board can count down every automated hop
            // rather than only the first one. Zero means "not automated".
            'delays'        => collect(Order::AUTO_ADVANCE)
                ->map(fn (array $stage) => Settings::int($stage['setting']))
                ->all(),
            'next'          => collect(Order::AUTO_ADVANCE)
                ->map(fn (array $stage) => $stage['to'])
                ->all(),
            'pending_hours' => max(Settings::int('auto_status_pending_hours'), 1),
        ];

        $extensionReasons = $this->extensionReasons();

        return view('orders.index', compact(
            'orders', 'customers', 'activeServices', 'tailors',
            'timeSlots', 'autoStatus', 'extensionReasons'
        ));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->service->create($request->validated());

        // Flag out-of-hours intake when the shop has asked to be warned. The
        // order is still accepted — this is a note, not a refusal.
        $warning = (Settings::bool('enforce_business_hours') && !Settings::isOpenNow())
            ? 'Heads up: this order was taken outside your business hours.'
            : null;

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully.',
            'warning' => $warning,
            'order'   => $this->serialize($order->loadPaymentTotals()),
        ], 201);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'customer',
            'staff:id,name',
            'measurement',
            'payments' => fn ($q) => $q->latest('date'),
            'statusHistories',
        ]);

        return response()->json([
            'success'  => true,
            'order'    => $this->serialize($order),
            'timeline' => $this->timeline($order),
            'payments' => $order->payments->map(fn ($p) => [
                'amount' => (float) $p->amount,
                'method' => $p->payment_method,
                'date'   => $p->date?->format('M d, Y'),
            ]),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $order = $this->service->update($order, $request->validated());

        return response()->json([
            'success' => true,
            'message' => "Order {$order->display_number} updated successfully.",
            'order'   => $this->serialize($order->loadPaymentTotals()),
        ]);
    }

    public function destroy(Request $request, Order $order)
    {
        $label = $order->display_number;
        $this->service->delete($order);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => "Order {$label} deleted successfully."]);
        }

        return redirect()->route('orders.index')->with('success', "Order {$label} deleted successfully.");
    }

    /**
     * Status-only change, used by the kanban board and the details modal.
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(Order::ALL_STATUSES)],
            'note'   => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->service->changeStatus($order, $validated['status'], $validated['note'] ?? null);
        $order->loadPaymentTotals();

        // Handing over a garment that has not been paid for is the shop's call
        // to make, not the software's — so this reports, it does not refuse.
        $outstanding = $order->status === 'Delivered' ? $order->balance_due : 0.0;

        return response()->json([
            'success' => true,
            'message' => "{$order->display_number} moved to {$order->status}.",
            'warning' => $outstanding > 0
                ? sprintf('%s was delivered with %s still outstanding.', $order->display_number, Money::format($outstanding, true))
                : null,
            'order'   => $this->serialize($order),
        ]);
    }

    /** Explicit collection notice; notification acceptance does not prove delivery. */
    public function notify(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate(['mark_ready' => ['nullable', 'boolean']]);
        if (($validated['mark_ready'] ?? false) && $order->status !== 'Ready') {
            $order = $this->service->changeStatus($order, 'Ready', 'Marked ready before notifying the customer');
        }
        $result = $this->dispatchCustomerNotice($order->load('customer'), 'order-ready');
        if ($result['sent']) $this->service->markNotified($order);
        return response()->json(['success' => true,
            'message' => $result['sent'] ? 'Collection notice accepted for sending.' : $result['error'],
            'notification' => $result, 'order' => $this->serialize($order->loadPaymentTotals())]);
    }

    public function bulkNotify(Request $request): JsonResponse
    {
        $validated = $request->validate(['order_ids' => ['required', 'array', 'min:1', 'max:200'],
            'order_ids.*' => ['integer', 'exists:orders,id']]);
        $orders = Order::with('customer:id,name,phone')->whereIn('id', $validated['order_ids'])
            ->where('status', 'Ready for Verification')->get();
        if ($orders->isEmpty()) return response()->json(['success' => false,
            'message' => 'None of the selected orders are awaiting verification.', 'sent' => 0], 422);
        $accepted = 0; $failed = [];
        foreach ($orders as $order) {
            $result = $this->dispatchCustomerNotice($order, 'order-ready');
            if (!$result['sent']) { $failed[] = $order->display_number; continue; }
            $this->service->markNotified($order);
            $this->service->changeStatus($order, 'Ready', 'Verified and collection notice accepted for sending');
            $accepted++;
        }
        return response()->json(['success' => true, 'sent' => $accepted, 'accepted' => $accepted,
            'promoted' => $accepted, 'skipped' => count($validated['order_ids']) - $orders->count(),
            'failed' => $failed, 'message' => sprintf('%d notice(s) accepted; %d failed. %d order(s) marked Ready.', $accepted, count($failed), $accepted)]);
    }

    /**
     * Push the delivery date of every open order out by N days, recording a
     * reason against each one.
     */
    public function bulkExtend(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days'        => ['required', 'integer', 'min:1', 'max:90'],
            'reason'      => ['required', 'string', 'max:255'],
            'order_ids'   => ['nullable', 'array'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $query = Order::open();

        if (!empty($validated['order_ids'])) {
            $query->whereIn('id', $validated['order_ids']);
        }

        $orders = $query->with('customer')->get();

        foreach ($orders as $order) {
            $oldDate = $order->delivery_date?->copy();

            if ($order->delivery_date) {
                $order->forceFill([
                    'delivery_date' => $order->delivery_date->copy()->addDays($validated['days']),
                ])->save();
            }

            $this->service->changeStatus(
                $order,
                $order->status === 'Overdue' ? 'In Progress' : $order->status,
                sprintf('Delivery extended by %d day(s): %s', $validated['days'], $validated['reason'])
            );

            // Tell the customer, using the shop's DUE DATE EXTENDED template.
            $result = $this->dispatchCustomerNotice($order->fresh()->load('customer'), 'due-extended', [
                'newDate' => Dates::format($order->fresh()->delivery_date, 'To be confirmed'),
                'oldDate' => Dates::format($oldDate, 'the original date'),
                'reason'  => $validated['reason'],
            ]);

        }

        return response()->json([
            'success' => true,
            'updated' => $orders->count(),
            'message' => sprintf('%d order(s) extended by %d day(s).', $orders->count(), $validated['days']),
        ]);
    }

    /**
     * Receipt payload rendered by the thermal-receipt modal.
     */
    public function receipt(Order $order): JsonResponse
    {
        $order->load(['customer', 'payments', 'tailor', 'measurement', 'measurementSheets']);

        // Every optional block below is governed by the Thermal Printer panel,
        // so a switch turned off there really does drop off the paper.
        $config  = Settings::receipt();
        $pricing = PricingService::forOrder($order);

        // The workshop copy needs the numbers the tailor actually cuts to.
        // An order usually carries its own measurement record; when it was
        // created without one, fall back to the customer's most recent sheet
        // rather than printing a blank job card.
        $sheet = $order->measurement
            ?: Measurement::query()
                ->where('customer_id', $order->customer_id)
                ->latest('id')
                ->first();

        // An order for three garments carries three sheets; the job card prints
        // each one under its own "PIECE n" heading so the cutter never has to
        // guess which numbers belong to which suit. A single-garment order
        // resolves to exactly one entry and prints exactly as it always did.
        $sheets = $order->items_migrated_at ? $order->lineItems()->with('pieces.measurement')->get()->flatMap(fn($item) => $item->pieces->pluck('measurement')->filter())->all() : $order->measurementSheets->all();

        if (!$order->items_migrated_at && empty($sheets) && $sheet) {
            $sheets = [$sheet];
        }

        return response()->json([
            'success' => true,
            'config'  => $config,
            'receipt' => [
                'store'       => $config['show_logo'] ? (Settings::str('store_name') ?: 'Atelier') : null,
                'logo'        => $config['show_logo'] ? (Settings::str('logo_path') ?: null) : null,
                'tagline'     => Settings::str('tagline') ?: null,
                'address'     => Settings::str('address') ?: null,
                'phone'       => Settings::str('phone') ?: null,
                'footer'      => $config['footer'] ?: null,
                'terms'       => $config['show_terms'] ? ($config['terms'] ?: null) : null,
                'stamp'       => $config['show_stamp'] ? (Settings::str('stamp_path') ?: null) : null,
                'width'       => $config['width'],
                'order'       => $order->display_number,
                'invoice'     => $order->display_invoice,
                'date'        => Dates::format($order->created_at),
                'customer'    => $order->customer?->name,
                'customer_ph' => $config['show_phone'] ? $order->customer?->phone : null,
                'garment'     => $order->primary_item_name,
                'items' => PricingService::invoiceItems($order),
                'fabric'      => $order->fabric,
                'lines'       => PricingService::orderLines($order),
                'subtotal'    => $pricing['subtotal'],
                'tax'         => $pricing['tax_enabled'] ? $pricing['tax'] : null,
                'tax_label'   => $pricing['tax_label'],
                'tax_rate'    => $pricing['tax_rate'],
                'service_charge'       => $pricing['service_charge_enabled'] ? $pricing['service_charge'] : null,
                'service_charge_label' => $pricing['service_charge_label'],
                'total'       => $pricing['total'],
                'advance'     => $config['show_advance'] ? (float) $order->paid_amount : null,
                'balance'     => $config['show_balance'] ? (float) $order->balance_due : null,
                'barcode'     => $config['show_barcode'] ? $order->display_number : null,
                'due'         => trim(Dates::format($order->delivery_date, '') . ' ' . ($order->time_slot ?? '')),
                'currency'    => Money::symbol(),

                /* ------------------------------------------------------------
                 | Workshop copy
                 |------------------------------------------------------------
                 | Printed as a second, separate slip for the tailor. It carries
                 | no pricing on purpose — the tailor needs the garment, the
                 | measurements and the deadline, and the shop does not want its
                 | margins walking around the workshop.
                 */
                'status'      => $order->status,
                'priority'    => $order->priority ?: 'Normal',
                'tailor'      => $order->tailor?->name,
                'notes'       => $order->notes ?: null,
                'style_notes' => $order->style_notes ?: null,
                'measure'     => [
                    'garment' => $sheet?->garment_type ?: $order->primary_item_name,
                    'unit'    => $sheet?->unit ?: (Settings::str('measurement_unit') ?: 'inch'),
                    'notes'   => $sheet?->notes ?: null,
                    'taken'   => $sheet ? Dates::format($sheet->created_at) : null,
                    // `rows` stays the first sheet so nothing that already reads
                    // it has to change; `pieces` is the full set.
                    'rows'    => $this->measurementRows($sheet),
                    'pieces'  => collect($sheets)->values()->map(fn (Measurement $m, int $i) => [
                        'piece' => $m->piece_no ?: $i + 1,
                        'garment' => $m->garment_type, 'unit' => $m->unit,
                        'notes' => $m->notes ?: null,
                        'rows'  => $this->measurementRows($m),
                    ])->all(),
                ],
                'quantity'    => $order->quantity,
                'unit_price'  => $order->unit_price,
            ],
        ]);
    }

    /**
     * Lightweight polling payload: statuses and counts only.
     */
    public function live(): JsonResponse
    {
        // The board polls this endpoint, so the same rate-limited sweep runs
        // here too: an order whose countdown reaches zero flips to "In
        // Progress" on the next poll instead of waiting for a reload.
        $this->service->autoAdvanceOrders();

        $orders = Order::query()
            ->with('customer:id,name,phone')
            ->withPaymentTotals()
            ->withStageSince()
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders->map(fn (Order $o) => $this->serialize($o)),
            'counts' => [
                'All'         => $orders->count(),
                'Pending'     => $orders->where('status', 'Pending')->count(),
                'In Progress' => $orders->where('status', 'In Progress')->count(),
                'Overdue'     => $orders->where('status', 'Overdue')->count(),
                'Due Today'   => $orders->filter(fn (Order $o) => $o->is_due_today)->count(),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Measurement columns that were actually filled in, as label/value pairs.
     *
     * Empty fields are dropped rather than printed as blanks: a job card with
     * twenty rows, half of them empty, is harder to read on 80mm paper than one
     * with the eight numbers that matter.
     *
     * @return array<int, array{label: string, value: string}>
     */
    private function measurementRows(?Measurement $sheet): array
    {
        if (!$sheet) {
            return [];
        }

        $rows = [];

        $profile = $sheet->piece?->profile;
        foreach ($profile['fields'] ?? Measurement::FIELDS as $field) {
            $value = in_array($field, Measurement::FIELDS) ? $sheet->{$field} : ($sheet->details[$field] ?? null);

            if ($value === null || $value === '') {
                continue;
            }

            // 12.50 prints as 12.5, 12.00 prints as 12 — trailing zeros are
            // noise on a receipt.
            $clean = rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');

            $rows[] = [
                'label' => $profile['labels'][$field] ?? Measurement::label($field),
                'value' => $clean === '' ? '0' : $clean,
            ];
        }

        return $rows;
    }

    /**
     * The order's history with the time each stage actually took.
     *
     * A history row records the moment a stage *started*; how long it lasted is
     * the gap to the next row, and for the stage still running, the gap to now.
     * That gap is the only honest answer to "where does the work pile up?", and
     * it was already in the table — nothing was reading it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Order $order): array
    {
        $entries = $order->statusHistories->values();

        return $entries->map(function ($h, int $i) use ($entries, $order) {
            $next    = $entries[$i + 1] ?? null;
            $endedAt = $next?->created_at;

            // The last entry is the stage the order is sitting in right now,
            // unless the order is finished — then it simply stopped there.
            $running = $next === null && !in_array($order->status, Order::CLOSED_STATUSES, true);

            if ($next === null) {
                $endedAt = $running ? now() : null;
            }

            return [
                'label'    => $h->label,
                'note'     => $h->note,
                'actor'    => $h->actor_label,
                'at'       => $h->created_at->format('M d, g:i A'),
                'status'   => $h->to_status,
                'running'  => $running,
                'duration' => $endedAt
                    ? $this->humanDuration($h->created_at->diffInMinutes($endedAt))
                    : null,
            ];
        })->all();
    }

    /**
     * Minutes as something a person reads at a glance: "2d 4h", "3h 10m", "8m".
     */
    private function humanDuration(int $minutes): string
    {
        $minutes = max($minutes, 0);

        if ($minutes < 60) {
            return $minutes . 'm';
        }

        $hours = intdiv($minutes, 60);
        $mins  = $minutes % 60;

        if ($hours < 24) {
            return $mins ? "{$hours}h {$mins}m" : "{$hours}h";
        }

        $days  = intdiv($hours, 24);
        $hours = $hours % 24;

        return $hours ? "{$days}d {$hours}h" : "{$days}d";
    }

    /**
     * Move a batch of orders to the same status.
     *
     * Orders that cannot legally reach that status are skipped rather than
     * failing the whole batch — selecting forty orders and having one Delivered
     * row abort the other thirty-nine would be useless. The response says
     * exactly how many moved and how many were left alone, so nothing is
     * silently dropped.
     */
    public function bulkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status'      => ['required', Rule::in(Order::ALL_STATUSES)],
            'note'        => ['nullable', 'string', 'max:255'],
            'order_ids'   => ['required', 'array', 'min:1', 'max:200'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
        ]);

        $orders  = Order::with('customer')->whereIn('id', $validated['order_ids'])->get();
        $status  = $validated['status'];
        $note    = $validated['note'] ?? 'Moved in bulk';
        $moved   = 0;
        $skipped = [];

        foreach ($orders as $order) {
            if ($order->status === $status || !$order->canMoveTo($status)) {
                $skipped[] = $order->display_number;
                continue;
            }

            $this->service->changeStatus($order, $status, $note);
            $moved++;
        }

        $message = sprintf('%d order(s) moved to %s.', $moved, $status);

        if ($skipped) {
            $message .= sprintf(' %d skipped (cannot move there): %s.', count($skipped), implode(', ', array_slice($skipped, 0, 5)));
        }

        // No explicit stats flush: changeStatus() already flushes on every move.

        return response()->json([
            'success' => true,
            'moved'   => $moved,
            'skipped' => count($skipped),
            'message' => $message,
        ]);
    }

    /**
     * Single serialisation shape shared by the page load, every mutation
     * response and the polling endpoint, so the client never has to reconcile
     * two different payloads.
     */
    private function serialize(Order $order): array
    {
        // `paid_amount` counts the advance once: it is already a payment row.
        $paid = $order->paid_amount;

        return [
            'db_id'     => $order->id,
            'customer_id' => $order->customer_id,
            'edit_version' => $order->edit_version,
            'billing_lines' => PricingService::orderLines($order),
            'items_locked' => $order->items_locked,
            'garments' => $order->lineItems()->with('pieces.measurement')->get()->map(fn($item) => [
                'id' => $item->id, 'product_service_id' => $item->product_service_id, 'name' => $item->name,
                'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'subtotal' => $item->subtotal, 'fabric' => $item->fabric ?? '', 'style_notes' => $item->style_notes ?? '',
                'pieces' => $item->pieces->map(fn($piece) => ['id' => $piece->id, 'unit' => $piece->unit, 'profile' => $piece->profile,
                    'values' => $piece->measurement ? array_merge($piece->measurement->only(Measurement::FIELDS), $piece->measurement->details ?? []) : (object)[]])->all(),
            ])->all(),
            'id'        => $order->display_number,
            'invoice'   => $order->display_invoice,
            'customer'  => $order->customer?->name ?? 'Unknown',
            'cid'       => $order->customer?->display_code ?? '',
            'phone'     => $order->customer?->phone ?? '',
            'garment'   => $order->primary_item_name,
            'fabric'    => $order->fabric ?? '',
            'status'    => $order->status,
            'priority'  => $order->priority ?? 'Normal',
            'amount'    => (float) $order->total,
            'qty'       => $order->quantity,
            'unitPrice' => $order->unit_price,
            'advance'   => (float) $order->advance,
            'paid'      => round($paid, 2),
            'balance'   => round(max((float) $order->total - $paid, 0), 2),
            'due'       => $order->due_label,
            'dueDate'   => $order->delivery_date?->toIso8601String() ?? now()->toIso8601String(),
            'slot'      => $order->time_slot ?? '',
            // Client-side keys stay `tailor` / `tailor_id` so every view that
            // already reads them keeps working; what they point at is now a
            // Staff record.
            'tailor'    => $order->staff?->name ?? '',
            'tailor_id' => $order->staff_id,
            'service_id' => $order->product_service_id,
            // Position in Order::WORKFLOW, so the timeline can mark every earlier
            // step complete without re-deriving the order of the stages.
            'stage'     => $order->stage_index,
            'progress'  => (int) ($order->progress ?? Order::progressFor($order->status)),
            'createdAt' => $order->created_at->toIso8601String(),
            'stageSince' => $order->stage_since_at->toIso8601String(),
            'notified'  => $order->notified_at !== null,
            'notes'     => $order->notes ?? '',
            'overdue'   => $order->is_overdue,
            'atRisk'    => $order->is_at_risk,
            'dueToday'  => $order->is_due_today,
            // Lets the board grey out impossible drops instead of finding out
            // from a rejected request.
            'allowed'   => $order->allowed_statuses,
        ];
    }

    private function dispatchCustomerNotice(Order $order, string $templateId, array $extra = []): array
    {
        return \App\Services\CustomerNotificationDispatcher::dispatch($templateId, $order, $extra);
    }

    /**
     * @return array<int, string>
     */
    private function timeSlots(): array
    {
        return Settings::list('delivery_slots');
    }

    /**
     * @return array<int, string>
     */
    private function extensionReasons(): array
    {
        return Settings::list('extension_reasons');
    }
}
