<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Measurement;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Staff;
use App\Models\StaffWorkLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns every write path for orders so status, balance, progress, delivery
 * records, payments, notifications and the audit trail can never fall out of
 * sync with each other.
 */
class OrderService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $customer = $this->resolveCustomer($data);

            $measurementId = $data['measurement_id'] ?? null;

            // A saved sheet the counter picked wins. Otherwise the booking form
            // supplies one sheet per garment, so a customer dropping off three
            // suits gets three sets of numbers the cutter can tell apart.
            $sheetIds = [];

            if (empty($measurementId)) {
                $sheetIds      = $this->storeMeasurementSheets($customer, $data);
                $measurementId = $sheetIds[0] ?? null;
            }

            $priced  = (float) ($data['total'] ?? 0);
            $advance = (float) ($data['advance'] ?? 0);
            $status  = $data['status'] ?? 'Pending';

            // Tax and service charge that are configured as *exclusive* are
            // added here, so the stored total is always what the customer
            // genuinely owes and the balance can never disagree with the
            // invoice. Inclusive rates leave the priced figure untouched.
            $total = PricingService::grandTotal($priced);

            $order = Order::create([
                'customer_id'        => $customer->id,
                'product_service_id' => $data['product_service_id'] ?? null,
                'measurement_id'     => $measurementId,
                'tailor_id'          => $data['tailor_id'] ?? null,
                'staff_id'           => $data['staff_id'] ?? null,
                'created_by'         => Auth::id(),
                // Line items keep the pre-tax price the user actually entered.
                'items'              => $this->buildItems($data, $priced),
                'garment'            => $data['garment'] ?? null,
                'fabric'             => $data['fabric'] ?? null,
                'style_notes'        => $data['style_notes'] ?? null,
                'total'              => $total,
                'advance'            => $advance,
                'balance'            => max($total - $advance, 0),
                'status'             => $status,
                'priority'           => $data['priority'] ?? 'Normal',
                'progress'           => Order::progressFor($status),
                'delivery_date'      => $data['delivery_date'] ?? null,
                'time_slot'          => $data['time_slot'] ?? null,
                'notes'              => $data['notes'] ?? null,
            ]);

            $order->forceFill([
                'order_number'   => $this->generateOrderNumber($order),
                'invoice_number' => $this->generateInvoiceNumber($order),
            ])->save();

            // The sheets exist before the order does (the order needs to point
            // at the first one), so ownership is stamped on once there is an
            // id to point back at.
            if ($sheetIds) {
                Measurement::whereIn('id', $sheetIds)->update(['order_id' => $order->id]);
            }

            // The advance is a real payment: record it so Payments & Billing,
            // reports and the collection rate all reconcile.
            if ($advance > 0) {
                Payment::create([
                    'invoice_id'      => $order->invoice_number,
                    'order_id'        => $order->id,
                    'customer_id'     => $customer->id,
                    'amount'          => $advance,
                    'type'            => 'Advance',
                    'status'          => 'Completed',
                    'payment_method'  => $data['payment_method'] ?? 'Cash',
                    'date'            => now(),
                    'recorded_by'     => Auth::id(),
                ]);
            }

            $this->syncDelivery($order);

            $customer->forceFill(['last_visit_at' => now()])->saveQuietly();

            $this->recordHistory($order, null, $status, 'Order created');

            NotificationService::orderCreated($order);

            // Send the shop's own ORDER CREATED template, if it is switched on.
            WhatsAppService::sendTemplate('order-created', $order);

            // SMS channel — fires independently of WhatsApp.
            SmsService::sendTemplate('order-created', $order);

            ActivityLogger::created(
                $order,
                sprintf('%s created for %s (%s)', $order->display_number, $customer->name, Money::format($total)),
                'orders'
            );

            StatsService::flush();

            return $order->load('customer');
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $previousStatus = $order->status;
            $newStatus      = $data['status'] ?? $previousStatus;

            // A re-priced order goes through the same tax rules as a new one;
            // an untouched total is already inclusive and must not be re-taxed.
            $total = array_key_exists('total', $data)
                ? PricingService::grandTotal((float) $data['total'])
                : (float) $order->total;

            $advance = array_key_exists('advance', $data) ? (float) $data['advance'] : (float) $order->advance;

            $order->fill(array_filter([
                'garment'            => $data['garment'] ?? null,
                'fabric'             => $data['fabric'] ?? null,
                'style_notes'        => $data['style_notes'] ?? null,
                'priority'           => $data['priority'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'time_slot'          => $data['time_slot'] ?? null,
                'tailor_id'          => $data['tailor_id'] ?? null,
                'staff_id'           => $data['staff_id'] ?? null,
                'product_service_id' => $data['product_service_id'] ?? null,
            ], fn ($v) => $v !== null));

            if (array_key_exists('delivery_date', $data)) {
                $order->delivery_date = $data['delivery_date'];
            }

            if (array_key_exists('notes', $data)) {
                $order->notes = $data['notes'];
            }

            // A re-priced order must not keep advertising the old per-piece
            // rate on its receipt, so the line is rebuilt from the new total.
            if (array_key_exists('total', $data)) {
                $items = $order->items ?: [];
                $qty   = max((int) ($items[0]['qty'] ?? 1), 1);

                $items[0] = array_merge($items[0] ?? [], [
                    'qty'        => $qty,
                    'unit_price' => round($total / $qty, 2),
                    'price'      => $total,
                ]);

                $order->items = $items;
            }

            // Keep the deposit's ledger entry in step with the deposit column
            // before anything reads back what the order has been paid, so the
            // advance is the same figure on the order, in Payments & Billing
            // and on the receipt.
            $this->syncAdvancePayment($order, $advance);

            $order->total    = $total;
            $order->advance  = $advance;
            $order->balance  = max($total - $this->paidTotal($order, $advance), 0);
            $order->status   = $newStatus;
            $order->progress = Order::progressFor($newStatus);

            $this->applyStatusTimestamps($order, $newStatus);
            $order->save();

            $this->recordStaffWork($order, $newStatus);

            if ($previousStatus !== $newStatus) {
                $this->recordHistory($order, $previousStatus, $newStatus);
                NotificationService::orderStatusChanged($order, $previousStatus, $newStatus);
            }

            $this->syncDelivery($order);

            ActivityLogger::updated(
                $order,
                sprintf('%s updated (%s)', $order->display_number, $newStatus),
                'orders',
                ['from' => $previousStatus, 'to' => $newStatus]
            );

            StatsService::flush();

            return $order->load('customer');
        });
    }

    /**
     * Status-only transition, used by the kanban board and quick actions.
     */
    public function changeStatus(Order $order, string $status, ?string $note = null): Order
    {
        return DB::transaction(function () use ($order, $status, $note) {
            $from = $order->status;

            if ($from === $status) {
                return $order;
            }

            // The workflow moves one step at a time. Anything else — a jump
            // over a stage, or a status that cannot follow this one at all —
            // is a mis-click or a stale screen, never a real instruction.
            if (!$order->canMoveTo($status)) {
                throw ValidationException::withMessages([
                    'status' => sprintf(
                        '%s cannot go straight from %s to %s. Allowed from here: %s.',
                        $order->display_number,
                        $from,
                        $status,
                        implode(', ', $order->allowed_statuses) ?: 'nothing (this order is closed)'
                    ),
                ]);
            }

            // Undoing reported work, or cancelling an order the shop was paid
            // for, has to leave a trace of why.
            if (Order::transitionNeedsNote($from, $status) && blank($note)) {
                throw ValidationException::withMessages([
                    'note' => sprintf('Moving %s to %s needs a reason.', $order->display_number, $status),
                ]);
            }

            $order->status   = $status;
            $order->progress = Order::progressFor($status);
            $this->applyStatusTimestamps($order, $status);
            $order->save();

            $this->recordStaffWork($order, $status);

            $this->recordHistory($order, $from, $status, $note);
            $this->syncDelivery($order);

            NotificationService::orderStatusChanged($order, $from, $status);
            ActivityLogger::log(
                'Order status changed',
                sprintf('%s moved from %s to %s', $order->display_number, $from, $status),
                'orders',
                $order,
                ['from' => $from, 'to' => $status],
                'status_changed'
            );

            StatsService::flush();

            return $order->load('customer');
        });
    }

    /**
     * Mark ready + flag that the customer was notified over WhatsApp.
     */
    public function markNotified(Order $order): Order
    {
        $order->forceFill(['notified_at' => now()])->save();

        NotificationService::whatsappSent($order);
        ActivityLogger::log(
            'WhatsApp sent',
            sprintf('Pickup notification sent for %s', $order->display_number),
            'orders',
            $order,
            [],
            'notified'
        );

        return $order;
    }

    public function delete(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $label = $order->display_number;
            $name  = $order->customer?->name ?? 'Unknown';

            $order->delete();

            ActivityLogger::log(
                'Deleted Order',
                sprintf('%s for %s was deleted', $label, $name),
                'orders',
                null,
                [],
                'deleted'
            );

            StatsService::flush();
        });
    }

    /**
     * Recalculate the outstanding balance from actual recorded payments.
     */
    public function recalculateBalance(Order $order): Order
    {
        // Counts the advance exactly once: it is already a payment row.
        $paid = $this->paidTotal($order, (float) $order->advance);
        $order->forceFill(['balance' => max((float) $order->total - $paid, 0)])->save();

        // Auto-deliver on full payment when the shop has opted in.
        if ($order->balance <= 0
            && Settings::bool('auto_delivery_update')
            && $order->status === 'Ready') {
            $this->changeStatus($order, 'Delivered', 'Auto-delivered after full payment');
        }

        StatsService::flush();

        return $order;
    }

    /**
     * Walk every order that has waited out its stage's delay on to the next
     * status.
     *
     * Each hop in `Order::AUTO_ADVANCE` has its own delay in Settings, counted
     * in `auto_status_unit` (hours by default, minutes for a shop that wants a
     * fast turnaround or is testing the flow). A delay of zero leaves that hop
     * alone, which is the default for everything past Pending → In Progress.
     *
     * A word of caution worth keeping next to this code: automating the later
     * hops makes the software *claim* physical work has happened. An order that
     * reaches "Ready" on a timer tells the customer their garment is on the
     * shelf whether or not anyone has touched it. That is the shop's call to
     * make deliberately — hence zero by default — not something to switch on
     * because it looks tidy.
     */
    public function autoAdvanceOrders(bool $force = false): int
    {
        if (!Settings::bool('auto_status_enabled')) {
            return 0;
        }

        $unit = $this->autoStatusUnit();

        // A shop testing this in minutes cannot wait five of them to see the
        // sweep run, so the guard window follows the unit it is measuring.
        if (!$force && !$this->shouldSweep('orders.sweep.auto_status', $unit === 'minutes' ? 30 : 300)) {
            return 0;
        }

        $moved = 0;

        foreach (Order::AUTO_ADVANCE as $from => $stage) {
            $moved += $this->autoAdvanceStage($from, $stage['to'], $stage['setting'], $unit);
        }

        if ($moved > 0) {
            StatsService::flush();
        }

        return $moved;
    }

    /**
     * Backwards-compatible alias for the first hop's old name.
     *
     * @deprecated Use autoAdvanceOrders(); kept so nothing calling the old name breaks.
     */
    public function autoStartPendingOrders(bool $force = false): int
    {
        return $this->autoAdvanceOrders($force);
    }

    /** 'minutes' or 'hours' — the unit every auto-advance delay is counted in. */
    private function autoStatusUnit(): string
    {
        return Settings::str('auto_status_unit') === 'minutes' ? 'minutes' : 'hours';
    }

    /**
     * Move every order that has waited out its delay in one particular status.
     *
     * "Waited" is measured from when the order *entered* the status, not from
     * when it was created — otherwise the second hop would fire the instant the
     * first one did, and an order would race through the whole workflow in a
     * single sweep.
     */
    private function autoAdvanceStage(string $from, string $to, string $settingKey, string $unit): int
    {
        $delay = Settings::int($settingKey);

        // Zero means this hop is not automated. That is the default for every
        // stage past the first, and it is how the shop opts in one at a time.
        if ($delay < 1) {
            return 0;
        }

        $cutoff = $unit === 'minutes' ? now()->subMinutes($delay) : now()->subHours($delay);

        $orders = Order::query()
            ->withStageSince()
            ->where('status', $from)
            ->with('customer')
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->filter(fn (Order $o) => $o->stage_since_at->lte($cutoff));

        $note = sprintf(
            'Auto-advanced to %s after %d %s in %s',
            $to,
            $delay,
            $delay === 1 ? rtrim($unit, 's') : $unit,
            $from
        );

        $moved = 0;

        foreach ($orders as $order) {
            if (!$order->canMoveTo($to)) {
                continue;
            }

            $this->changeStatus($order, $to, $note);
            $moved++;
        }

        return $moved;
    }

    /**
     * Raise a notification for every open order whose delivery date has passed.
     *
     * This used to overwrite the order's status with "Overdue", which lost the
     * only thing the workshop cares about: whether the garment is still being
     * stitched, waiting for verification, or sitting on the shelf. Being late
     * is a property of the delivery date, not a stage of the work, so the
     * status is now left alone and `Order::$is_overdue` derives lateness on the
     * fly. Nothing needs to be written for the badge to be correct.
     *
     * What remains here is the alert: the shop should still be told once.
     */
    public function flagOverdueOrders(bool $force = false): int
    {
        if (!$force && !$this->shouldSweep('orders.sweep.overdue')) {
            return 0;
        }

        $orders = Order::query()
            ->whereNotNull('delivery_date')
            ->where('delivery_date', '<', now()->startOfDay())
            ->whereIn('status', Order::OPEN_STATUSES)
            ->with('customer')
            ->limit(200)
            ->get();

        $raised = 0;

        foreach ($orders as $order) {
            if (NotificationService::orderOverdue($order)) {
                $raised++;
            }
        }

        return $raised;
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Rate-limits the background sweeps so a burst of page views doesn't turn
     * into a burst of identical scans. Returns true at most once per window.
     */
    private function shouldSweep(string $key, int $seconds = 300): bool
    {
        if (Cache::has($key)) {
            return false;
        }

        Cache::put($key, true, $seconds);

        return true;
    }

    /**
     * Money received for an order, read straight from the ledger so an
     * in-flight edit can never be answered from a stale eager-loaded sum.
     *
     * The advance is already one of those payment rows, so only the part of it
     * that has no ledger entry is added on top — never the whole column again.
     * Adding both is what made a Rs 2,000 advance print as Rs 4,000.
     */
    /**
     * Re-point the order's "Advance" payment row at the deposit currently on
     * the order.
     *
     * The advance is recorded once as a payment when the order is created, so
     * editing the deposit afterwards has to move that row rather than add
     * another one — otherwise the ledger and the `advance` column disagree and
     * the receipt has to guess which of the two is real.
     */
    private function syncAdvancePayment(Order $order, float $advance): void
    {
        $advance = round($advance, 2);
        $row     = $order->payments()->where('type', 'Advance')->orderBy('id')->first();

        if ($advance <= 0) {
            $row?->delete();

            return;
        }

        if ($row) {
            if (round((float) $row->amount, 2) !== $advance) {
                $row->forceFill(['amount' => $advance])->save();
            }

            return;
        }

        Payment::create([
            'invoice_id'     => $order->display_invoice,
            'order_id'       => $order->id,
            'customer_id'    => $order->customer_id,
            'amount'         => $advance,
            'type'           => 'Advance',
            'status'         => 'Completed',
            'payment_method' => 'Cash',
            'date'           => now(),
            'recorded_by'    => Auth::id(),
        ]);
    }

    private function paidTotal(Order $order, float $advance): float
    {
        $payments = (float) $order->payments()->sum('amount');
        $recorded = (float) $order->payments()->where('type', 'Advance')->sum('amount');

        return round($payments + Order::unrecordedAdvance($advance, $recorded), 2);
    }

    private function applyStatusTimestamps(Order $order, string $status): void
    {
        if (in_array($status, ['Ready', 'Completed'], true) && !$order->completed_at) {
            $order->completed_at = now();
        }

        if ($status === 'Delivered' && !$order->delivered_at) {
            $order->delivered_at = now();
        }
    }

    /**
     * Credit the assigned tailor once the garment is finished.
     *
     * The rate is copied onto the log rather than looked up later: raising
     * someone's per-suit rate must not silently rewrite what they earned on work
     * they already delivered.
     *
     * The unique index on `order_id` is the real guard against double-counting;
     * the check here just avoids a pointless insert attempt when an order is
     * saved again or moved back and forth between statuses.
     */
    private function recordStaffWork(Order $order, string $status): void
    {
        if (!in_array($status, ['Delivered', 'Completed'], true) || !$order->staff_id) {
            return;
        }

        if (StaffWorkLog::where('order_id', $order->id)->exists()) {
            return;
        }

        $staff = Staff::find($order->staff_id);

        if (!$staff) {
            return;
        }

        // Per-suit rate means per suit: an order for three garments earns the
        // tailor three times the rate, not once.
        $quantity = (float) $order->quantity;
        $rate     = (float) $staff->per_suit_rate;

        StaffWorkLog::create([
            'staff_id'     => $staff->id,
            'order_id'     => $order->id,
            'garment'      => $order->primary_item_name,
            'quantity'     => $quantity,
            'rate'         => $rate,
            'amount'       => round($quantity * $rate, 2),
            'completed_on' => now()->toDateString(),
            'notes'        => 'Recorded automatically on ' . $status,
        ]);
    }

    private function recordHistory(Order $order, ?string $from, string $to, ?string $note = null): void
    {
        OrderStatusHistory::create([
            'order_id'    => $order->id,
            'from_status' => $from,
            'to_status'   => $to,
            'label'       => $note ?: ($from ? "Status changed to {$to}" : "Order {$to}"),
            'note'        => $note,
            'user_id'     => Auth::id(),
            'actor_name'  => Auth::user()?->name ?? 'System',
        ]);
    }

    /**
     * Keep the deliveries table mirroring the order's fulfilment state so the
     * Delivery page reads from real rows rather than deriving everything.
     */
    private function syncDelivery(Order $order): void
    {
        // "Overdue" is no longer an order status — the Delivery page derives
        // lateness from the date itself, so nothing needs to be written here.
        $status = match ($order->status) {
            'Ready'                  => 'Ready',
            'Delivered', 'Completed' => 'Delivered',
            default                  => 'Scheduled',
        };

        Delivery::updateOrCreate(
            ['order_id' => $order->id],
            [
                'status'         => $status,
                'address'        => $order->customer?->address ?: $order->customer?->city,
                'delivery_date'  => $order->delivery_date,
                'delivered_at'   => $status === 'Delivered' ? ($order->delivered_at ?? now()) : null,
                'recipient_name' => $order->customer?->name,
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function resolveCustomer(array $data): Customer
    {
        if (!empty($data['customer_id'])) {
            return Customer::findOrFail($data['customer_id']);
        }

        return Customer::firstOrCreate(
            ['phone' => $data['customer_phone'] ?? null],
            [
                'name' => $data['customer_name'] ?? 'Walk-in Customer',
                'type' => 'Regular',
            ]
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    /**
     * Write one measurement sheet per garment on the order.
     *
     * The booking form sends `pieces` — an array of field maps, one per
     * garment. A single-garment order (and any older client that still posts a
     * flat `measurements` map) is just the one-element case, so both shapes are
     * accepted and take the same path.
     *
     * @return array<int, int> Sheet ids in piece order; empty when nothing was measured.
     */
    private function storeMeasurementSheets(Customer $customer, array $data): array
    {
        $pieces = $data['pieces'] ?? null;

        if (!is_array($pieces) || $pieces === []) {
            $pieces = [$data['measurements'] ?? []];
        }

        $ids     = [];
        $pieceNo = 0;

        foreach ($pieces as $piece) {
            $pieceNo++;

            $values = collect(is_array($piece) ? $piece : [])
                ->only(Measurement::FIELDS)
                ->filter(fn ($v) => $v !== null && $v !== '')
                ->all();

            // A blank piece is skipped rather than stored as an empty sheet,
            // but it still consumes its piece number so the pieces that were
            // filled in keep the position the counter gave them.
            if (empty($values)) {
                continue;
            }

            $sheet = Measurement::create(array_merge($values, [
                'customer_id'  => $customer->id,
                'piece_no'     => $pieceNo,
                'garment_type' => $data['garment'] ?? 'Custom',
                'tailor'       => $data['tailor'] ?? (Auth::user()?->name ?? 'Unassigned'),
                'unit'         => $data['unit'] ?? 'cm',
                'created_by'   => Auth::id(),
            ]));

            $ids[] = $sheet->id;
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildItems(array $data, float $total): array
    {
        $qty = max((int) ($data['quantity'] ?? 1), 1);

        return [[
            'name'   => $data['garment'] ?? 'Custom Order',
            'fabric' => $data['fabric'] ?? null,
            'qty'    => $qty,
            // Both figures are stored: the receipt prints "3 x Rs 2,500" from
            // the unit price, while every total still reads `price`.
            'unit_price' => round($total / $qty, 2),
            'price'      => $total,
        ]];
    }

    private function generateOrderNumber(Order $order): string
    {
        return Settings::str('order_prefix') . (1000 + $order->id);
    }

    private function generateInvoiceNumber(Order $order): string
    {
        return Settings::str('invoice_prefix') . (1000 + $order->id);
    }
}
