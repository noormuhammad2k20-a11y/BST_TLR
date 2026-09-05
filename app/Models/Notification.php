<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public const CATEGORIES = ['orders', 'payments', 'stock', 'whatsapp', 'alerts', 'system'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeUnread(Builder $q): Builder
    {
        return $q->where('is_read', false);
    }

    public function scopeOfCategory(Builder $q, ?string $category): Builder
    {
        return blank($category) || $category === 'all' ? $q : $q->where('category', $category);
    }

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->forceFill(['is_read' => true, 'read_at' => now()])->save();
        }
    }

    public function getDisplayTitleAttribute(): string
    {
        return $this->title ?: ($this->type ?: 'Notification');
    }

    public function getDateBucketAttribute(): string
    {
        if ($this->created_at?->isToday()) {
            return 'today';
        }

        if ($this->created_at?->isYesterday()) {
            return 'yesterday';
        }

        return 'earlier';
    }
}
