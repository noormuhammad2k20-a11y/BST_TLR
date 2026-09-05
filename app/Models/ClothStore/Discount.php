<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $table = 'cs_discounts';
    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'value' => 'float',
        'min_purchase' => 'float',
        'max_discount' => 'float',
        'usage_limit' => 'integer',
        'customer_limit' => 'integer',
        'usage_count' => 'integer',
        'applicable_products' => 'array',
        'applicable_categories' => 'array',
        'applicable_customers' => 'array',
        'is_active' => 'boolean',
    ];

    public function getCalculatedStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'Disabled';
        }

        $now = now();

        if ($this->end_date && $now->gt($this->end_date)) {
            return 'Expired';
        }

        if ($this->start_date && $now->lt($this->start_date)) {
            return 'Scheduled';
        }

        return 'Active';
    }
}
