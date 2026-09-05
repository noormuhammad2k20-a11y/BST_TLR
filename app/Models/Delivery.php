<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'delivery_date' => 'datetime',
        'delivered_at'  => 'datetime',
    ];

    /**
     * The only states a shop-managed collection can be in.
     *
     * These are garments the customer collects from the counter, so there is no
     * courier and nothing is ever "in transit": the order is either still being
     * worked on (Scheduled), sitting on the shelf waiting (Ready), or handed
     * over (Delivered).
     *
     * "Overdue" is deliberately not here. It is derived from the due date every
     * time the row is read, so it can never go stale, and staff are never asked
     * to mark something late by hand.
     */
    public const STATUSES = ['Scheduled', 'Ready', 'Delivered'];

    /**
     * Statuses that used to exist when this page was modelled on a courier
     * service. Rows written back then are mapped onto the list above on read,
     * so no data migration is needed and nothing renders as a blank badge.
     */
    public const LEGACY_STATUS_MAP = [
        'Out for Delivery' => 'Ready',
        'Failed'           => 'Scheduled',
        'Overdue'          => 'Scheduled',
        'Pending'          => 'Scheduled',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeOfStatus(Builder $q, ?string $status): Builder
    {
        return blank($status) || $status === 'All' ? $q : $q->where('status', $status);
    }

    /** A stored status, corrected for the retired courier states. */
    public function getShopStatusAttribute(): string
    {
        $status = (string) $this->status;

        return self::LEGACY_STATUS_MAP[$status]
            ?? (in_array($status, self::STATUSES, true) ? $status : 'Scheduled');
    }
}
