<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'last_visit_at'  => 'datetime',
        'loyalty_score'  => 'float',
        'is_active'      => 'boolean',
    ];

    protected static function booted(): void
    {
        // Give every customer a stable, human-readable code without needing a
        // second write: the code is derived from the auto-increment id.
        static::created(function (Customer $customer) {
            if (blank($customer->code)) {
                $customer->forceFill(['code' => 'C-' . (1041 + $customer->id)])->saveQuietly();
            }
        });
    }

    /* ------------------------------------------------------------------ */
    /* Relationships                                                       */
    /* ------------------------------------------------------------------ */

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                              */
    /* ------------------------------------------------------------------ */

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }

        $term = trim($term);

        return $q->where(function (Builder $sub) use ($term) {
            $sub->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('city', 'like', "%{$term}%");
        });
    }

    public function scopeOfType(Builder $q, ?string $type): Builder
    {
        return blank($type) || $type === 'All' ? $q : $q->where('type', $type);
    }

    /* ------------------------------------------------------------------ */
    /* Computed attributes                                                 */
    /* ------------------------------------------------------------------ */

    public function getDisplayCodeAttribute(): string
    {
        return $this->code ?: 'C-' . (1041 + $this->id);
    }

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $initials = collect($parts)
            ->filter()
            ->map(fn ($p) => mb_substr($p, 0, 1))
            ->take(2)
            ->implode('');

        return mb_strtoupper($initials ?: '?');
    }

    /**
     * Loyalty is derived, not stored, unless an explicit score was set:
     * order volume and spend both contribute, capped at 5.0.
     */
    public function getLoyaltyAttribute(): float
    {
        if ($this->loyalty_score !== null) {
            return round((float) $this->loyalty_score, 1);
        }

        // Accepts either withSum() alias so an eager-loaded total is always
        // reused — falling through to a query here caused an N+1.
        $orders = (int) ($this->orders_count ?? $this->orders()->count());
        $spent  = (float) ($this->orders_sum_total ?? $this->orders_total ?? $this->orders()->sum('total'));

        if ($orders === 0) {
            return 0.0;
        }

        $volumeScore = min($orders / 10, 1) * 2.5;
        $spendScore  = min($spent / 100000, 1) * 2.5;

        return round(max($volumeScore + $spendScore, 1.0), 1);
    }

    /**
     * Payment behaviour inferred from the customer's outstanding balances.
     */
    public function getBehaviorLabelAttribute(): string
    {
        if (filled($this->behavior)) {
            return $this->behavior;
        }

        $outstanding = (float) ($this->orders_sum_balance ?? $this->orders_balance ?? $this->orders()->sum('balance'));
        $orders      = (int) ($this->orders_count ?? $this->orders()->count());

        if ($orders === 0) {
            return 'New Customer';
        }

        if ($outstanding <= 0) {
            return 'Always Pays';
        }

        return $outstanding > 20000 ? 'Payment Pending' : 'Occasional Delay';
    }

    public function getLastVisitLabelAttribute(): string
    {
        $date = $this->last_visit_at ?? $this->orders_max_created_at ?? $this->last_order_at ?? null;

        if (!$date) {
            return $this->created_at?->diffForHumans() ?? '—';
        }

        $date = $date instanceof \DateTimeInterface ? $date : \Illuminate\Support\Carbon::parse($date);

        if ($date->isToday()) {
            return 'Today';
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        return $date->diffForHumans();
    }
}
