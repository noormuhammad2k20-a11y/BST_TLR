<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    protected $table = 'cs_customers';
    protected $guarded = ['id'];

    /**
     * Money and points are decimal columns; without casts they come back as
     * strings and every comparison in PHP/JS becomes a string comparison.
     */
    protected $casts = [
        'due_balance'        => 'decimal:2',
        'total_purchases'    => 'decimal:2',
        'loyalty_points'     => 'float',
        'last_purchase_date' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'cs_customer_id');
    }

    public function payments()
    {
        return $this->hasMany(CustomerPayment::class, 'cs_customer_id');
    }

    public function ledgers()
    {
        return $this->hasMany(CustomerLedger::class, 'cs_customer_id')
            ->orderBy('date', 'asc')
            ->orderBy('id', 'asc');
    }

    public function returns()
    {
        return $this->hasMany(ReturnOrder::class, 'cs_customer_id');
    }

    /** Initials for the avatar chips used across the module. */
    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];

        $initials = collect($parts)
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : '?';
    }
}
