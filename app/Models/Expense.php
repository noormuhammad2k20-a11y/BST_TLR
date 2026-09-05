<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date'   => 'datetime',
        'amount' => 'decimal:2',
    ];

    public const CATEGORIES = [
        'Raw Material', 'Utilities', 'Salary', 'Rent', 'Equipment',
        'Marketing', 'Transport', 'Maintenance', 'Other',
    ];

    public const METHODS = ['Cash', 'Bank', 'Card', 'UPI', 'Cheque'];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeBetweenDates(Builder $q, $from, $to): Builder
    {
        return $q->whereBetween('date', [$from, $to]);
    }

    public function scopeOfCategory(Builder $q, ?string $category): Builder
    {
        return blank($category) || $category === 'All' ? $q : $q->where('category', $category);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }

        $term = trim($term);

        return $q->where(function (Builder $sub) use ($term) {
            $sub->where('description', 'like', "%{$term}%")
                ->orWhere('vendor', 'like', "%{$term}%")
                ->orWhere('reference', 'like', "%{$term}%")
                ->orWhere('category', 'like', "%{$term}%");
        });
    }
}
