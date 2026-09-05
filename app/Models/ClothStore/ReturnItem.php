<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;

class ReturnItem extends Model
{
    protected $table = 'cs_return_items';
    protected $guarded = ['id'];

    public function returnOrder()
    {
        return $this->belongsTo(ReturnOrder::class, 'cs_return_id');
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'cs_order_item_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'cs_product_id');
    }

    public function exchangeProduct()
    {
        return $this->belongsTo(Product::class, 'exchange_product_id');
    }
}
