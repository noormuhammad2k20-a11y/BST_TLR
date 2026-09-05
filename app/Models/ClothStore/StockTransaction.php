<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransaction extends Model
{
    protected $table = 'cs_stock_transactions';
    protected $guarded = ['id'];

    /**
     * Stock moves in fractional metres, so the movement amount and the
     * before/after snapshots must all stay decimal. These were 'integer',
     * which rounded every fabric movement to the nearest whole metre and
     * left the audit trail disagreeing with cs_products.stock_quantity.
     */
    protected $casts = [
        'quantity' => 'float',
        'previous_qty' => 'float',
        'new_qty' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'cs_product_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }
}
