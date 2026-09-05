<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'cs_orders';
    protected $guarded = ['id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'cs_customer_id')->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'cs_order_id');
    }
}
