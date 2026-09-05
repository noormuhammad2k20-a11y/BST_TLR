<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $table = 'cs_purchases';
    protected $guarded = ['id'];



    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class, 'cs_purchase_id');
    }
}
