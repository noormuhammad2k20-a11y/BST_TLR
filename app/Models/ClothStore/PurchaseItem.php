<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseItem extends Model
{
    protected $table = 'cs_purchase_items';
    protected $guarded = ['id'];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class, 'cs_purchase_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'cs_product_id');
    }
}
