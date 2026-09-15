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
            if (!isset($data['garments'])) \Illuminate\Support\Facades\Validator::make($data,[
                'quantity'=>['sometimes','integer','min:1','max:20'], 'pieces'=>['nullable','array','max:20'],
            ])->validate();
            $customer = $this->resolveCustomer($data);
            $prepared = isset($data['garments']) ? app(OrderItemsService::class)->prepare($data, $customer->id) : null;
            if ($prepared) $data['total'] = $prepared['subtotal'];

            $measurementId = $data['measurement_id'] ?? null;
            if ($measurementId && !Measurement::where('customer_id',$customer->id)->whereKey($measurementId)->exists()) {
                throw ValidationException::withMessages(['measurement_id'=>'Select a measurement belonging to this customer.']);
            }

            // A saved sheet the counter picked wins. Otherwise the booking form
            // supplies one sheet per garment, so a customer dropping off three
            // suits gets three sets of numbers the cutter can tell apart.
            $sheetIds = [];

            if (!$prepared && empty($measurementId)) {
                $sheetIds      = $this->storeMeasurementSheets($customer, $data);
                $measurementId = $sheetIds[0] ?? null;
            }

            $priced  = Decimal::value((string)($data['total'] ?? '0'));
            $advance = Decimal::value((string)($data['advance'] ?? '0'));
            $status  = 'Received';
            $deliveryAt = DeliveryTiming::promise($data);
            if (!$deliveryAt) throw ValidationException::withMessages(['delivery_date' => 'A promised delivery date and time is required.']);

            // Tax and service charge that are configured as *exclusive* are
            // added here, so the stored total is always what the customer
            // genuinely owes and the balance can never disagree with the
            // invoice. Inclusive rates leave the priced figure untouched.
            $total = PricingService::grandTotal($priced);
            if (Decimal::cmp($total,'99999999') > 0) throw ValidationException::withMessages(['garments'=>'The order total exceeds the supported maximum.']);
            if (Decimal::cmp($advance,$total)>0) throw ValidationException::withMessages(['advance'=>'Advance exceeds invoice total.']);

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
                'balance'            => Decimal::max(Decimal::sub($total,$advance)),
                'status'             => $status,
                'priority'           => $data['priority'] ?? 'Normal',
                'progress'           => Order::progressFor($status),
                'delivery_date'      => $deliveryAt,
                'time_slot'          => $deliveryAt->format('g:i A'),
                'notes'              => $data['notes'] ?? null,
            ]);

            $order->forceFill([
                'order_number'   => $this->generateOrderNumber($order),
                'invoice_number' => $this->generateInvoiceNumber($order),
            ])->save();

            if ($prepared) {
                app(OrderItemsService::class)->write($order, $prepared['rows']);
                $order->forceFill(['billing_snapshot' => PricingService::breakdown((float)$total)])->save();
            }

            // The sheets exist before the order does (the order needs to point
            // at the first one), so ownership is stamped on once there is an
            // id to point back at.
            if ($sheetIds) {
                Measurement::whereIn('id', $sheetIds)->update(['order_id' => $order->id]);
            }

            if (!$prepared) {
                app(OrderItemsBackfill::class)->run($order, true);
                $order->refresh()->forceFill(['billing_snapshot'=>PricingService::breakdown((float)$total)])->save();
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

            CustomerNotificationDispatcher::dispatch('order-created', $order);

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
            $order=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $previousDeliveryDate = $order->delivery_date?->copy();
            if (!isset($data['garments']) && $order->items_migrated_at) {
                $data = app(OrderItemsService::class)->normalizeLegacyUpdate($order,$data);
            }
            if (isset($data['edit_version']) && (int)$data['edit_version'] !== $order->edit_version) {
                throw ValidationException::withMessages(['edit_version' => 'This order changed. Reload it before saving.']);
            }
            $prepared = null;
            if (isset($data['garments'])) {
                if (!array_key_exists('edit_version', $data)) throw ValidationException::withMessages(['edit_version' => 'Reload the order before editing garments.']);
                if ($order->items_locked) throw ValidationException::withMessages(['garments' => 'Garment, price and piece changes are locked after completion or credited work.']);
                $prepared = app(OrderItemsService::class)->prepare($data, $order->customer_id, $order);
                unset($data['total']);
                if ($prepared['repriced']) $data['total'] = $prepared['subtotal'];
            }
            if (!$prepared && $order->items_migrated_at && array_intersect(array_keys($data), ['total','garment','product_service_id','fabric','style_notes'])) {
                throw ValidationException::withMessages(['garments' => 'Use the garment editor to change this order.']);
            }
            $previousStatus = $order->status;
            $newStatus      = $data['status'] ?? $previousStatus;
            if ($newStatus === 'Ready' && $previousStatus !== 'Ready') {
                throw ValidationException::withMessages(['status'=>'Use Send SMS to mark this order Ready after the notification succeeds.']);
            }
            if ($newStatus !== $previousStatus && !$order->canMoveTo($newStatus)) {
                throw ValidationException::withMessages(['status' => 'Follow the order workflow one stage at a time.']);
            }

            // A re-priced order goes through the same tax rules as a new one;
            // an untouched total is already inclusive and must not be re-taxed.
            $total = array_key_exists('total', $data)
                ? PricingService::grandTotal((string) $data['total'])
                : (string) $order->total;
            if (Decimal::cmp($total,'99999999') > 0) throw ValidationException::withMessages(['garments'=>'The order total exceeds the supported maximum.']);

            $advance = array_key_exists('advance', $data) ? (string) $data['advance'] : (string) $order->advance;

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

            if (array_intersect(['delivery_date', 'delivery_time', 'time_slot'], array_keys($data))) {
                $order->delivery_date = DeliveryTiming::promise($data, $order);
                $order->time_slot = $order->delivery_date?->format('g:i A');
            }

            if (array_key_exists('notes', $data)) {
                $order->notes = $data['notes'];
            }

            // A re-priced order must not keep advertising the old per-piece
            // rate on its receipt, so the line is rebuilt from the new total.
            if (!$prepared && array_key_exists('total', $data)) {
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

            if (Decimal::cmp($total, app(TailoringFinanceService::class)->paid($order)) < 0) throw ValidationException::withMessages(['total' => 'The invoice total cannot be less than recorded payments.']);

            $order->total    = $total;
            $order->advance  = $advance;
            $order->balance  = Decimal::max(Decimal::sub($total,app(TailoringFinanceService::class)->paid($order)));
            $order->status   = $newStatus;
            $order->progress = Order::progressFor($newStatus);
            $order->edit_version++;

            $this->applyStatusTimestamps($order, $newStatus);
            $order->save();

            if ($prepared) {
                app(OrderItemsService::class)->write($order, $prepared['rows']);
                if ($prepared['repriced']) $order->forceFill(['billing_snapshot' => PricingService::breakdown((float)$total)])->save();
            }

            $this->recordStaffWork($order, $newStatus);

            if ($previousStatus !== $newStatus) {
                $this->recordHistory($order, $previousStatus, $newStatus);
                NotificationService::orderStatusChanged($order, $previousStatus, $newStatus);
                if ($newStatus === 'Ready') CustomerNotificationDispatcher::dispatch('order-ready', $order);
            }

            $this->syncDelivery($order);
            if ($previousDeliveryDate?->toDateTimeString() !== $order->delivery_date?->toDateTimeString()) {
                CustomerNotificationDispatcher::dispatch('due-extended', $order, [
                    'oldDate' => Dates::format($previousDeliveryDate, ''),
                    'newDate' => Dates::format($order->delivery_date, ''),
                    'reason' => $data['reason'] ?? '',
                ]);
            }


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

    /** Staff verifies physical readiness; Ready is earned by an accepted SMS. */
    public function markReady(Order $order): array
    {
        $this->prepareForCollection($order);
        $before = $order->fresh()->status;
        $notification = app(CollectionNotifications::class)->sendOrders([$order->id]);
        $fresh = $order->fresh()->load('customer');
        return ['changed'=>$before !== 'Ready' && $fresh->status === 'Ready','order'=>$fresh,'notification'=>$notification];
    }

    public function prepareForCollection(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (!in_array($locked->status, Order::UNVERIFIED_STATUSES, true)) return false;
            if ($locked->status !== 'Ready for Verification') {
                $this->changeStatus($locked, 'Ready for Verification', 'Garments physically checked by operator');
            }
            return true;
        });
    }

    /**
     * Status-only transition, used by the kanban board and quick actions.
     */
    public function changeStatus(Order $order, string $status, ?string $note = null, bool $smsConfirmed = false): Order
    {
        if ($status === 'Ready' && !$smsConfirmed && $order->status !== 'Ready') {
            if (DB::transactionLevel() > 0) throw ValidationException::withMessages(['status'=>'Send the collection SMS outside an open transaction.']);
            return $this->markReady($order)['order'];
        }
        return DB::transaction(function () use ($order, $status, $note) {
            $order=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $from = $order->status;

            if ($status === 'Ready' && $from !== 'Ready' && !$order->collectionMessages()
                ->whereIn('sms_logs.status', CollectionNotifications::SUCCESS)->whereNotNull('sms_logs.sent_at')->exists()) {
                throw ValidationException::withMessages(['status'=>'A successful collection SMS is required before this order becomes Ready.']);
            }

            if ($from === $status) {
                return $order;
            }

            // Allow configured forward transitions, including early physical readiness.
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
     * Record that a customer notice was accepted by a delivery provider.
     */
    public function markNotified(Order $order): Order
    {
        $order->forceFill(['notified_at' => now()])->save();

        NotificationService::smsSent($order);
        ActivityLogger::log(
            'Customer notice accepted',
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
        $paid = app(TailoringFinanceService::class)->paid($order);
        $order->forceFill(['balance' => Decimal::max(Decimal::sub((string)$order->total, $paid))])->save();

        // Collection is confirmed manually, regardless of payment balance.
        StatsService::flush();

        return $order;
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
    private function syncAdvancePayment(Order $order, string $advance): void
    {
        // Editing an invoice must not rewrite money already received.
        if (Decimal::cmp((string)$advance, (string)$order->getOriginal('advance')) !== 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['advance'=>'Use Payments & Billing to record or reverse an existing deposit.']);
        }
    }

    private function paidTotal(Order $order, float $advance): float
    {
        return (float) app(TailoringFinanceService::class)->paid($order);
    }

    private function applyStatusTimestamps(Order $order, string $status): void
    {
        if (in_array($status, ['Ready for Verification','Ready', 'Completed'], true) && !$order->completed_at) {
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
        if (!in_array($status, ['Ready', 'Delivered', 'Completed'], true) || !$order->staff_id) {
            return;
        }

        if (StaffWorkLog::where('order_id', $order->id)->exists()) {
            return;
        }

        $staff = Staff::whereKey($order->staff_id)->with('serviceRates')->lockForUpdate()->first();

        if (!$staff) {
            return;
        }

        $totalAmount = 0;
        $totalQuantity = 0;
        $rateBreakdown = [];
        $defaultRate = (float) $staff->per_suit_rate;

        if ($order->items_migrated_at) {
            foreach ($order->lineItems as $item) {
                if ($item->productService && $item->productService->type === 'Service') continue;
                if (($item->pieces->first()?->profile['key'] ?? 'generic') === 'accessory') continue;
                
                $qty = (float) $item->quantity;
                if ($qty <= 0) continue;

                $resolvedRate = $defaultRate;
                $rateSource = 'Default Per-Suit Rate';
                
                if ($item->tailor_rate_override !== null) {
                    $resolvedRate = (float) $item->tailor_rate_override;
                    $rateSource = 'Custom Order Override';
                } else {
                    $specialRate = $staff->serviceRates->firstWhere('product_service_id', $item->product_service_id);
                    if ($specialRate) {
                        $resolvedRate = (float) $specialRate->rate;
                        $rateSource = 'Special Service Rate';
                    }
                }

                $amount = round($qty * $resolvedRate, 2);
                $totalAmount += $amount;
                $totalQuantity += $qty;

                $rateBreakdown[] = [
                    'garment' => $item->name,
                    'quantity' => $qty,
                    'rate' => $resolvedRate,
                    'amount' => $amount,
                    'source' => $rateSource,
                ];
            }
        } else {
            $totalQuantity = (float) $order->quantity;
            $totalAmount = round($totalQuantity * $defaultRate, 2);
            $rateBreakdown[] = [
                'garment' => $order->primary_item_name,
                'quantity' => $totalQuantity,
                'rate' => $defaultRate,
                'amount' => $totalAmount,
                'source' => 'Default Per-Suit Rate (Legacy Order)',
            ];
        }

        if ($totalQuantity <= 0) return;

        StaffWorkLog::create([
            'staff_id'     => $staff->id,
            'order_id'     => $order->id,
            'garment'      => $order->primary_item_name,
            'quantity'     => $totalQuantity,
            'rate'         => $totalQuantity > 0 ? round($totalAmount / $totalQuantity, 2) : $defaultRate,
            'amount'       => $totalAmount,
            'rate_breakdown' => $rateBreakdown,
            'completed_on' => ($order->completed_at ?? $order->delivered_at ?? $order->updated_at ?? now())->toDateString(),
            'notes'        => 'Recorded automatically on ' . $status,
        ]);
    }

    /** Repair missing completion credits without changing orders or sending messages. */
    public function reconcileStaffWork(Order $order): bool
    {
        return DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (StaffWorkLog::where('order_id', $locked->id)->exists()) return false;
            $this->recordStaffWork($locked, $locked->status);
            $created = StaffWorkLog::where('order_id', $locked->id)->exists();
            if ($created) StatsService::flush();
            return $created;
        });
    }

    private function recordHistory(Order $order, ?string $from, string $to, ?string $note = null, ?\Illuminate\Support\Carbon $effectiveAt = null): void
    {
        OrderStatusHistory::create([
            'order_id'    => $order->id,
            'from_status' => $from,
            'to_status'   => $to,
            'label'       => $note ?: ($from ? "Status changed to {$to}" : "Order {$to}"),
            'note'        => $note,
            'user_id'     => $effectiveAt ? null : Auth::id(),
            'actor_name'  => $effectiveAt ? 'System' : (Auth::user()?->name ?? 'System'),
            'created_at'  => $effectiveAt ?? now(),
            'updated_at'  => now(),
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

        if ($existing = CustomerLifecycle::matchingPhone((string)($data['customer_phone'] ?? ''))) {
            if ($existing->trashed()) CustomerLifecycle::rejectDuplicate($existing);
            return $existing;
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
            $pieces = array_fill(0, max(1,(int)($data['quantity'] ?? 1)), $data['measurements'] ?? []);
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
