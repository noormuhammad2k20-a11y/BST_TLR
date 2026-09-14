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

    /** This directory contains stitchers only. */
    public const ROLES = ['Master Tailor', 'Tailor'];

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
        return $this->hasMany(StaffPayment::class)->whereNull('reversed_at')->whereNull('reverses_payment_id')->orderByDesc('paid_on');
    }

    public function paymentHistory(): HasMany
    {
        return $this->hasMany(StaffPayment::class)->orderByDesc('paid_on')->orderByDesc('id');
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

    /** Earnings use the rate stored at completion; payments settle one calendar period. */
    public function dueFor(?string $period = null): array
    {
        $period ??= \App\Services\StaffPayPeriod::current($this->payment_period ?? 'Monthly');
        [$start, $end, $frequency] = \App\Services\StaffPayPeriod::bounds($period);
        $work = $this->workLogs()->where('completed_on', '>=', $start->toDateString())->where('completed_on', '<', $end->addDay()->toDateString())->get();
        $workCents = $work->sum(fn ($w) => (int) round((float) $w->amount * 100));
        // A monthly retainer is apportioned by calendar day for shorter cycles.
        $salaryCents = 0;
        if (in_array($this->salary_type, ['Monthly', 'Both'], true)) {
            $monthlyCents = (int) round((float) $this->monthly_salary * 100);
            for ($day = $start; $day->lte($end); $day = $day->addDay()) {
                $salaryCents += (int) round($monthlyCents * $day->day / $day->daysInMonth)
                    - (int) round($monthlyCents * ($day->day - 1) / $day->daysInMonth);
            }
        }
        $earnedCents = $salaryCents + ($this->salary_type === 'Monthly' ? 0 : $workCents);
        $paidCents = (int) round((float) $this->payments()->where('period', $period)->sum('amount') * 100);
        $remaining = max($earnedCents - $paidCents, 0) / 100;

        return [
            'period' => $period,
            'payment_period' => $frequency,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'pieces' => (float) $work->sum('quantity'),
            'rate' => $work->pluck('rate')->unique()->count() === 1 ? (float) $work->first()->rate : null,
            'configured_rate' => (float) $this->per_suit_rate,
            'rates' => $work->groupBy('rate')->map(fn ($logs, $rate) => [
                'rate' => (float) $rate, 'pieces' => (float) $logs->sum('quantity'),
                'earned' => round((float) $logs->sum('amount'), 2),
            ])->values()->all(),
            'earned' => $earnedCents / 100,
            'stitching' => $workCents / 100,
            'paid' => $paidCents / 100,
            'remaining' => $remaining,
            'credit' => max($paidCents - $earnedCents, 0) / 100,
            'status' => $earnedCents <= 0 ? 'Pending' : ($remaining <= 0 ? 'Paid' : ($paidCents > 0 ? 'Partial' : 'Pending')),
        ];
    }
}
