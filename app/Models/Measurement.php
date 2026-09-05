<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Measurement extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'details'     => 'array',
        'is_template' => 'boolean',
    ];

    /**
     * Every numeric measurement column, in the order the UI renders them.
     * Single source of truth for validation, display and completeness scoring.
     */
    public const FIELDS = [
        'length', 'shoulder_width', 'sleeve_length',
        'chest', 'chest_losing',
        'waist', 'waist_losing',
        'hip', 'hip_losing',
        'collar', 'ghera', 'patti', 'button', 'cuff', 'koni',
        'elbow', 'armhole', 'takai', 'salwar_length', 'pancho',
    ];

    /**
     * The fields a shop starts out with as mandatory. Editable in Settings —
     * read `Settings::requiredMeasurementFields()` rather than this constant
     * anywhere that validates or renders a form.
     */
    public const REQUIRED_FIELDS = ['chest_losing', 'waist_losing', 'hip_losing'];

    /** Human label for one measurement column. */
    public static function label(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Every column with its display label, so the UI never has to guess.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return collect(self::FIELDS)
            ->mapWithKeys(fn (string $f) => [$f => self::label($f)])
            ->all();
    }

    /** The `step` a number input should use for the configured precision. */
    public static function stepFor(int $decimals): string
    {
        return $decimals <= 0 ? '1' : '0.' . str_repeat('0', $decimals - 1) . '1';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * The order this sheet was taken for, when it was taken during booking.
     *
     * A sheet can also exist on its own — measured at the counter, reused for
     * later orders — in which case this is null and only `customer_id` is set.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        if (blank($term)) {
            return $q;
        }

        $term = trim($term);

        return $q->where(function (Builder $sub) use ($term) {
            $sub->where('garment_type', 'like', "%{$term}%")
                ->orWhere('tailor', 'like', "%{$term}%")
                ->orWhereHas('customer', fn (Builder $c) => $c
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"));
        });
    }

    /**
     * Percentage of measurement fields that were actually filled in. Drives the
     * "accuracy" stat instead of a hardcoded number.
     */
    public function getCompletenessAttribute(): float
    {
        $filled = collect(self::FIELDS)
            ->filter(fn ($f) => $this->{$f} !== null && $this->{$f} !== '')
            ->count();

        return round($filled / count(self::FIELDS) * 100, 1);
    }
}
