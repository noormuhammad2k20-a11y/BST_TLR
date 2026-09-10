<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItemPiece extends Model
{
    use SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = ['profile' => 'array', 'piece_no' => 'integer'];
    public function item() { return $this->belongsTo(OrderItem::class, 'order_item_id'); }
    public function measurement() { return $this->hasOne(Measurement::class); }
}
