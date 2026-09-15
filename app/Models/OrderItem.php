<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;
    protected $guarded = ['id'];
    protected $casts = ['quantity' => 'integer', 'unit_price' => 'decimal:2', 'subtotal' => 'decimal:2', 'tailor_rate_override' => 'decimal:2'];
    public function order() { return $this->belongsTo(Order::class); }
    public function productService() { return $this->belongsTo(ProductService::class); }
    public function pieces() { return $this->hasMany(OrderItemPiece::class)->orderBy('piece_no'); }
}
