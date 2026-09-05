<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;

class ProductLocation extends Model
{
    protected $table = 'cs_product_locations';
    protected $guarded = [];

    /** Per-location stock is fractional (metres of fabric on that shelf). */
    protected $casts = [
        'quantity' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'cs_product_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'cs_location_id');
    }
}
