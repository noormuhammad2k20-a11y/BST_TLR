<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person on the shop's payroll.
 *
 * Separate from User by design: a User is a login, a Staff member is someone
 * who stitches and gets paid. Most tailors never sign in.
 */
class Staff extends Model
{
    protected $table = 'staff';

    protected $guarded = ['id'];

    protected $casts = [
        'joining_date'   => 'date',
        'monthly_salary' => 'decimal:2',
        'per_suit_rate'  => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    /** How a staff member is paid. */
    public const SALARY_TYPES = ['Monthly', 'Per Suit', 'Both'];

    /** Starting points, not a closed list — the field is free text. */
    public const ROLES = ['Master Tailor', 'Tailor', 'Cutter', 'Helper', 'Finisher', 'Presser'];

    /* ------------------------------------------------------------------ */
    /* Relationships                                                       */
    /* ------------------------------------------------------------------ */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(StaffPayment::class)->orderByDesc('paid_on');
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(StaffWorkLog::class)->orderByDesc('completed_on');
    }

    /* ------------------------------------------------------------------ */
    /* Scopes                                                              */
    /* ------------------------------------------------------------------ */

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }

        $term = trim($term);

        return $q->where(function (Builder $sub) use ($term) {
            $sub->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('role', 'like', "%{$term}%");
        });
    }

    /* ------------------------------------------------------------------ */
    /* Computed                                                            */
    /* ------------------------------------------------------------------ */

    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn ($p) => mb_substr($p, 0, 1))
            ->implode('');

        return mb_strtoupper($initials ?: 'S');
    }

    /** Total stitching earned, ever. */
    public function getWorkTotalAttribute(): float
    {
        return (float) ($this->work_logs_sum_amount ?? $this->workLogs()->sum('amount'));
    }

    /** Pieces finished, ever. */
    public function getPiecesTotalAttribute(): float
    {
        return (float) ($this->work_logs_sum_quantity ?? $this->workLogs()->sum('quantity'));
    }

    /** Wages actually handed over, ever. */
    public function getPaidTotalAttribute(): float
    {
        return (float) ($this->payments_sum_amount ?? $this->payments()->sum('amount'));
    }

    /**
     * What the shop owes this person for a given month.
     *
     * Monthly staff are owed their salary; piece-rate staff are owed whatever
     * they stitched that month; "Both" is exactly what it says. Whatever has
     * already been handed over for that month comes off the top.
     */
    public function dueFor(?string $period = null): array
    {
        $period ??= now()->format('Y-m');

        [$year, $month] = array_map('intval', explode('-', $period));

        $earnedFromWork = (float) $this->workLogs()
            ->whereYear('completed_on', $year)
            ->whereMonth('completed_on', $month)
            ->sum('amount');

        $salary = match ($this->salary_type) {
            'Monthly'  => (float) $this->monthly_salary,
            'Per Suit' => $earnedFromWork,
            default    => (float) $this->monthly_salary + $earnedFromWork,
        };

        $paid = (float) $this->payments()->where('period', $period)->sum('amount');

        $remaining = round(max($salary - $paid, 0), 2);

        return [
            'period'    => $period,
            'earned'    => round($salary, 2),
            'stitching' => round($earnedFromWork, 2),
            'paid'      => round($paid, 2),
            'remaining' => $remaining,
            'status'    => $salary <= 0 ? 'Pending' : ($remaining <= 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending')),
        ];
    }
}
