<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class TailorRate extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(TailorCategory::class, 'tailor_category_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ProductService::class, 'product_service_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'Active');
    }
}
