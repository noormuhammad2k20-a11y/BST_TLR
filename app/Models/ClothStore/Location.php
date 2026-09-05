<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $table = 'cs_locations';
    protected $guarded = [];

    public function productLocations()
    {
        return $this->hasMany(ProductLocation::class, 'cs_location_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'cs_product_locations', 'cs_location_id', 'cs_product_id')
                    ->withPivot('quantity')
                    ->withTimestamps();
    }
}
