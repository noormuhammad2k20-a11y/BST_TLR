<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'cs_products';
    protected $guarded = ['id'];

    /**
     * Fabric is sold by the metre, so every quantity is fractional.
     *
     * These are cast to float rather than 'decimal:2' deliberately: the
     * 'decimal' cast returns a *string*, which JSON-encodes as "9.00" and
     * makes the client compare stock levels lexicographically — "9.00" would
     * then read as greater than "10.00" and low-stock alerts would silently
     * stop firing. float keeps the JSON numeric. The decimal(12,2) columns
     * remain the source of truth for precision.
     */
    protected $casts = [
        'price' => 'float',
        'cost_price' => 'float',
        'stock_quantity' => 'float',
        'low_stock_threshold' => 'float',
        'reserved_quantity' => 'float',
        'incoming_quantity' => 'float',
        'suggested_reorder_qty' => 'float',
        'ignore_stock_alerts' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'cs_category_id');
    }



    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'cs_product_id');
    }
    
    public function locations()
    {
        return $this->belongsToMany(Location::class, 'cs_product_locations', 'cs_product_id', 'cs_location_id')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }

    public function productLocations(): HasMany
    {
        return $this->hasMany(ProductLocation::class, 'cs_product_id');
    }

    /**
     * Sellable stock, in the product's own unit (metres for fabric).
     *
     * Returns float, not int — an int return type would round 115.50 m to 116.
     */
    public function getAvailableStockAttribute(): float
    {
        return round((float) $this->stock_quantity - (float) $this->reserved_quantity, 2);
    }

    public function getIsLowStockAttribute(): bool
    {
        return $this->available_stock <= (float) $this->low_stock_threshold;
    }

    public function getAlertLevelAttribute(): string
    {
        if ($this->ignore_stock_alerts) {
            return 'Ignored';
        }

        $available = $this->available_stock;
        $threshold = (float) $this->low_stock_threshold;

        // Treat sub-centimetre remnants as sold out rather than sellable.
        if ($available < 0.01) {
            return 'Out of Stock';
        }

        // Critical once down to the last 30% of the reorder threshold. No
        // floor() here: on a 0.50 m threshold, floor() collapsed the critical
        // band to zero and the alert never fired.
        if ($available <= $threshold * 0.3) {
            return 'Critical';
        }

        if ($available <= $threshold) {
            return 'Low';
        }

        return 'Healthy';
    }
}
