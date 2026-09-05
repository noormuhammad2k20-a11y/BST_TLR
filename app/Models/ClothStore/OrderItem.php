<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'cs_order_items';
    protected $guarded = ['id'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'cs_product_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'cs_order_id');
    }
}
