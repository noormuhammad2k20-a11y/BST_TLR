<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductService extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'requires_measurements' => 'boolean',
        'price'               => 'decimal:2',
        'cost_price'          => 'decimal:2',
        'stock_quantity'      => 'integer',
        'low_stock_threshold' => 'integer',
        'duration_days'       => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function ($product) {
            $product->normalized_name = $product->canonical_id ? null : \App\Services\CatalogueIdentity::normalize($product->name);
        });
    }

    public function lineItems(): HasMany { return $this->hasMany(OrderItem::class); }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(TailorRate::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'Active');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }

        $term = trim($term);

        return $q->where(function (Builder $sub) use ($term) {
            $sub->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /**
     * The level at which this item counts as low. An item may set its own
     * threshold; anything that does not falls back to the shop-wide default
     * configured in Settings.
     */
    public function getEffectiveLowStockThresholdAttribute(): int
    {
        return $this->low_stock_threshold !== null
            ? (int) $this->low_stock_threshold
            : \App\Services\NotificationService::lowStockThreshold();
    }

    /**
     * Only meaningful for stocked items; services return false.
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->stock_quantity !== null
            && $this->stock_quantity <= $this->effective_low_stock_threshold;
    }

    /** Stocked items at or below their threshold. */
    public function scopeLowStock(Builder $q): Builder
    {
        $fallback = \App\Services\NotificationService::lowStockThreshold();

        return $q->whereNotNull('stock_quantity')
            ->whereRaw('stock_quantity <= COALESCE(low_stock_threshold, ?)', [$fallback]);
    }
}
