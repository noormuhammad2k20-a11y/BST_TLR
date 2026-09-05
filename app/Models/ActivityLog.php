<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'properties' => 'array',
    ];

    public const CATEGORIES = ['auth', 'orders', 'customers', 'inventory', 'payments', 'system'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfCategory(Builder $q, ?string $category): Builder
    {
        return blank($category) || $category === 'all' ? $q : $q->where('category', $category);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }

        $term = trim($term);

        return $q->where(function (Builder $sub) use ($term) {
            $sub->where('action', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('actor_name', 'like', "%{$term}%");
        });
    }

    public function getActorLabelAttribute(): string
    {
        return $this->actor_name ?: ($this->user?->name ?? 'System');
    }
}
