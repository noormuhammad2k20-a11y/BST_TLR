<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'delivery_date' => 'datetime',
        'notified_at'   => 'datetime',
        'completed_at'  => 'datetime',
        'delivered_at'  => 'datetime',
        'items'         => 'array',
        'total'         => 'decimal:2',
        'advance'       => 'decimal:2',
        'balance'       => 'decimal:2',
        'progress'      => 'integer',
    ];

    /**
     * The shop's actual workflow, in order.
     *
     *   Pending → In Progress → Ready for Verification → Ready → Delivered
     *
     * The timeline renders one step per entry plus a leading "Received" step,
     * which is not a stored status: an order row existing at all means the
     * material was received, so it is always complete.
     *
     * "Overdue" is deliberately absent. It is a condition of an order, not a
     * stage of it — a garment can be overdue while it is still being stitched,
     * and overwriting the real status with "Overdue" destroys the information
     * the workshop actually needs. Read `is_overdue` for that instead.
     */
    public const WORKFLOW = ['Pending', 'In Progress', 'Ready for Verification', 'Ready', 'Delivered'];

    /**
     * Statuses that mean the order is still moving through the workshop.
     */
    public const OPEN_STATUSES = ['Pending', 'In Progress', 'Ready for Verification', 'Ready'];

    public const CLOSED_STATUSES = ['Delivered', 'Completed', 'Cancelled'];

    /**
     * Which status each status is allowed to move to.
     *
     * Until now any status could jump to any other, so a garment nobody had
     * touched could be marked Delivered, and a delivered order could silently
     * fall back to Pending. The workshop's real sequence is one step at a time,
     * with two exits that are always available — a step back to correct a
     * mistake, and Cancelled.
     *
     * Backward moves are allowed on purpose: staff mis-click, and forcing them
     * to live with a wrong status is worse than letting them undo it. What a
     * backward move now costs is a written reason (see `transitionNeedsNote`).
     */
    public const TRANSITIONS = [
        'Pending'                => ['In Progress', 'Cancelled'],
        'In Progress'            => ['Ready for Verification', 'Pending', 'Cancelled'],
        'Ready for Verification' => ['Ready', 'In Progress', 'Cancelled'],
        'Ready'                  => ['Delivered', 'Ready for Verification', 'Cancelled'],
        'Delivered'              => ['Completed', 'Ready'],
        'Completed'              => ['Delivered'],
        'Cancelled'              => ['Pending'],

        // Legacy rows written before Overdue stopped being a status. Without
        // this the bulk "extend delivery" action, which rescues exactly those
        // rows, would be locked out of the only status it can move them to.
        'Overdue'                => ['In Progress', 'Pending', 'Ready for Verification', 'Ready', 'Cancelled'],
    ];

    /**
     * Every status a row is allowed to hold, including the terminal ones.
     */
    public const ALL_STATUSES = [
        'Pending', 'In Progress', 'Ready for Verification',
        'Ready', 'Delivered', 'Completed', 'Cancelled',
    ];

    /**
     * Canonical progress percentage for each status. Used everywhere so the
     * kanban bar, table and details modal can never drift apart.
     */
    public const PROGRESS_MAP = [
        'Pending'                => 10,
        'In Progress'            => 40,
        'Ready for Verification' => 70,
        'Ready'                  => 100,
        'Delivered'              => 100,
        'Completed'              => 100,
        'Cancelled'              => 0,

        // Legacy rows written before Overdue stopped being a status. Kept so an
        // un-migrated database still renders instead of showing 0%.
        'Overdue'                => 40,
    ];

    /* ------------------------------------------------------------------ */
    /* Relationships                                                       */
    /* ------------------------------------------------------------------ */

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    /**
     * Every measurement sheet booked with this order, piece 1 first.
     *
     * `measurement()` (via `measurement_id`) still points at the first sheet;
     * this is the full set for an order of two or three garments.
     */
    public function measurementSheets(): HasMany
    {
        return $this->hasMany(Measurement::class)->orderBy('piece_no');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function productService(): BelongsTo
    {
        return $this->belongsTo(ProductService::class);
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(Measurement::class);
    }

    public function tailor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tailor_id');
    }

    /**
     * The person on the payroll doing the stitching.
     *
     * `tailor_id` (a User) is kept for the records that predate the Staff
     * module; new assignments go through here.
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                              */
    /* ------------------------------------------------------------------ */

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', self::CLOSED_STATUSES);
    }

    public function scopeDueToday(Builder $q): Builder
    {
        return $q->whereDate('delivery_date', today())
                 ->whereNotIn('status', self::CLOSED_STATUSES);
    }

    public function scopeOverdue(Builder $q): Builder
    {
        return $q->whereNotNull('delivery_date')
                 ->where('delivery_date', '<', now())
                 ->whereNotIn('status', self::CLOSED_STATUSES);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }

        $term = trim($term);

        return $q->where(function (Builder $sub) use ($term) {
            $sub->where('order_number', 'like', "%{$term}%")
                ->orWhere('invoice_number', 'like', "%{$term}%")
                ->orWhere('garment', 'like', "%{$term}%")
                ->orWhere('fabric', 'like', "%{$term}%")
                ->orWhereHas('customer', function (Builder $c) use ($term) {
                    $c->where('name', 'like', "%{$term}%")
                      ->orWhere('phone', 'like', "%{$term}%");
                });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Computed attributes                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Records created before a number was assigned fall back to the shop's
     * configured prefix, so changing it in Settings is reflected everywhere.
     */
    public function getDisplayNumberAttribute(): string
    {
        return $this->order_number ?: \App\Services\Settings::str('order_prefix') . (1000 + $this->id);
    }

    public function getDisplayInvoiceAttribute(): string
    {
        return $this->invoice_number ?: \App\Services\Settings::str('invoice_prefix') . (1000 + $this->id);
    }

    /**
     * The advance taken at the counter is deliberately written in two places:
     * onto `orders.advance`, so the order carries its own deposit, and as a
     * Payment row of type "Advance", so Payments & Billing, the reports and
     * the collection rate all reconcile against one ledger.
     *
     * That means the deposit is already inside the payments total. Adding the
     * `advance` column on top of it counted the same money twice and was why a
     * Rs 200 advance printed as "Advance Paid Rs 400".
     *
     * Everything that needs "money received" must therefore add only the part
     * of the advance that has no matching payment row: zero for every order
     * this app creates, and the full advance for legacy or imported rows whose
     * deposit was never recorded as a payment.
     */
    public static function unrecordedAdvance(float $advance, float $recordedAdvance): float
    {
        return round(max($advance - $recordedAdvance, 0), 2);
    }

    /**
     * Sum of every recorded payment, preferring whatever the caller already
     * eager-loaded (`withSum(... as payments_total)`, `withSum(...)`'s default
     * alias, or the relation itself) over another query.
     */
    public function paymentsTotal(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->sum('amount');
        }

        $attributes = $this->getAttributes();

        foreach (['payments_total', 'payments_sum_amount'] as $alias) {
            if (isset($attributes[$alias])) {
                return (float) $attributes[$alias];
            }
        }

        return (float) $this->payments()->sum('amount');
    }

    /**
     * Sum of the payments that represent the advance itself.
     */
    public function advancePaymentsTotal(): float
    {
        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->where('type', 'Advance')->sum('amount');
        }

        $attributes = $this->getAttributes();

        if (isset($attributes['advance_payments_total'])) {
            return (float) $attributes['advance_payments_total'];
        }

        return (float) $this->payments()->where('type', 'Advance')->sum('amount');
    }

    /**
     * Money actually received for this order, counting the advance exactly once.
     *
     * `$advance` lets a caller ask the question for a deposit that is being
     * edited but not yet saved; it defaults to the stored column.
     */
    public function paidTotalFor(?float $advance = null): float
    {
        $advance = $advance ?? (float) $this->advance;

        return round(
            $this->paymentsTotal() + self::unrecordedAdvance($advance, $this->advancePaymentsTotal()),
            2
        );
    }

    /**
     * Eager-load both payment sums, so a list of orders can report what each
     * one has been paid without a query per row.
     */
    public function scopeWithPaymentTotals(Builder $q): Builder
    {
        return $q
            ->withSum('payments as payments_total', 'amount')
            ->withSum(
                ['payments as advance_payments_total' => fn ($p) => $p->where('type', 'Advance')],
                'amount'
            );
    }

    /**
     * The `scopeWithPaymentTotals` equivalent for a model already in hand.
     */
    public function loadPaymentTotals(): self
    {
        return $this
            ->loadSum('payments as payments_total', 'amount')
            ->loadSum(
                ['payments as advance_payments_total' => fn ($p) => $p->where('type', 'Advance')],
                'amount'
            );
    }

    /**
     * Total actually received: every recorded payment, plus any advance that
     * was never written to the payment ledger. Never both.
     */
    public function getPaidAmountAttribute(): float
    {
        return $this->paidTotalFor();
    }

    public function getBalanceDueAttribute(): float
    {
        return round(max((float) $this->total - $this->paid_amount, 0), 2);
    }

    public function getPaymentStatusAttribute(): string
    {
        $paid = $this->paid_amount;

        if ($paid <= 0) {
            return 'Pending';
        }

        return $paid >= (float) $this->total ? 'Paid' : 'Partial';
    }

    /**
     * The statuses this order may legally move to right now.
     *
     * @return array<int, string>
     */
    /**
     * Each status that a timer may move on, and where it moves to.
     *
     * The settings key holds the delay; a delay of zero switches that hop off.
     */
    public const AUTO_ADVANCE = [
        'Pending'                => ['to' => 'In Progress',            'setting' => 'auto_status_pending_hours'],
        'In Progress'            => ['to' => 'Ready for Verification', 'setting' => 'auto_status_progress_delay'],
        'Ready for Verification' => ['to' => 'Ready',                  'setting' => 'auto_status_verify_delay'],
        'Ready'                  => ['to' => 'Delivered',              'setting' => 'auto_status_ready_delay'],
    ];

    /**
     * Select the moment this order entered its current status, as `stage_since`.
     *
     * `updated_at` cannot answer this — any edit moves it. The status history
     * can, and already holds a row per stage; this pulls the latest one in a
     * single subquery so a board of two hundred orders still costs one query.
     */
    public function scopeWithStageSince(Builder $q): Builder
    {
        // Mirrors what withSum() does: only claim the base columns if the
        // caller has not selected anything yet, so chaining order never
        // silently drops another scope's aggregates.
        if (is_null($q->getQuery()->columns)) {
            $q->select([$q->getQuery()->from . '.*']);
        }

        return $q->addSelect(['stage_since' => OrderStatusHistory::query()
            ->select('created_at')
            ->whereColumn('order_id', 'orders.id')
            ->orderByDesc('id')
            ->limit(1),
        ]);
    }

    /**
     * When this order entered its current status. Falls back to when it was
     * created, which is correct for an order that has never moved.
     */
    public function getStageSinceAtAttribute(): \Illuminate\Support\Carbon
    {
        $raw = $this->getAttributes()['stage_since'] ?? null;

        if ($raw) {
            return \Illuminate\Support\Carbon::parse($raw);
        }

        if ($this->relationLoaded('statusHistories') && $this->statusHistories->isNotEmpty()) {
            return $this->statusHistories->last()->created_at;
        }

        return $this->created_at;
    }

    public function getAllowedStatusesAttribute(): array
    {
        return self::TRANSITIONS[$this->status] ?? [];
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, $this->allowed_statuses, true);
    }

    /**
     * True when the move goes back down the workflow, or ends the order.
     *
     * Both deserve an explanation in the audit trail: one undoes work that was
     * reported as done, the other closes an order the shop was paid for.
     */
    public static function transitionNeedsNote(string $from, string $to): bool
    {
        if ($to === 'Cancelled') {
            return true;
        }

        $fromIndex = array_search($from, self::WORKFLOW, true);
        $toIndex   = array_search($to, self::WORKFLOW, true);

        return $fromIndex !== false && $toIndex !== false && $toIndex < $fromIndex;
    }

    /**
     * Late, but not yet late enough for anyone to have noticed.
     *
     * `is_overdue` only fires once the delivery date has already passed, which
     * is the moment it stops being useful. This is the warning before that: the
     * garment is due within the shop's at-risk window and has not reached the
     * shelf, so there is still time to do something about it.
     */
    public function getIsAtRiskAttribute(): bool
    {
        if (!$this->delivery_date || $this->is_overdue) {
            return false;
        }

        if (!in_array($this->status, self::OPEN_STATUSES, true)) {
            return false;
        }

        // Already verified or on the shelf: the work is done, so a near date is
        // not a risk any more.
        if (in_array($this->status, ['Ready'], true)) {
            return false;
        }

        $hours = max(\App\Services\Settings::int('at_risk_hours'), 1);

        return $this->delivery_date->lte(now()->addHours($hours));
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->delivery_date
            && $this->delivery_date->isPast()
            && !in_array($this->status, self::CLOSED_STATUSES, true);
    }

    public function getIsDueTodayAttribute(): bool
    {
        return $this->delivery_date
            && $this->delivery_date->isToday()
            && !in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /**
     * Human label for the "Due" column: "Today" when due today, else "MMM d".
     */
    public function getDueLabelAttribute(): string
    {
        if (!$this->delivery_date) {
            return 'N/A';
        }

        if ($this->delivery_date->isToday()) {
            return 'Today';
        }

        return $this->delivery_date->format('M d');
    }

    /**
     * How many garments this order is for. Lives in the `items` payload rather
     * than its own column, so a future multi-item order can carry a different
     * quantity per line without another migration.
     */
    public function getQuantityAttribute(): int
    {
        return max((int) ($this->items[0]['qty'] ?? 1), 1);
    }

    /**
     * Price of a single garment. Falls back to dividing the line total when an
     * older row was written before unit prices were stored.
     */
    public function getUnitPriceAttribute(): float
    {
        $item = $this->items[0] ?? [];

        if (isset($item['unit_price'])) {
            return round((float) $item['unit_price'], 2);
        }

        return round(((float) ($item['price'] ?? $this->total)) / $this->quantity, 2);
    }

    public function getPrimaryItemNameAttribute(): string
    {
        if (filled($this->garment)) {
            return $this->garment;
        }

        return $this->items[0]['name'] ?? 'Custom Order';
    }

    public static function progressFor(string $status): int
    {
        return self::PROGRESS_MAP[$status] ?? 0;
    }

    /**
     * How far along the workflow this order is, as an index into WORKFLOW.
     *
     * Terminal statuses report the end of the line; anything unrecognised
     * (a legacy "Overdue" row, say) reports -1 so the caller can decide rather
     * than being handed a misleading position.
     */
    public function getStageIndexAttribute(): int
    {
        $index = array_search($this->status, self::WORKFLOW, true);

        if ($index !== false) {
            return $index;
        }

        return in_array($this->status, ['Completed'], true)
            ? count(self::WORKFLOW) - 1
            : -1;
    }
}
