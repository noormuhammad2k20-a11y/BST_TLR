<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One wage payment to one staff member.
 *
 * Kept out of the customer `payments` table on purpose: money coming in and
 * money going out must never end up in the same sum by accident.
 */
class StaffPayment extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount'  => 'decimal:2',
        'paid_on' => 'date',
    ];

    public const METHODS = ['Cash', 'Bank Transfer', 'Easypaisa', 'JazzCash', 'Cheque'];

    public const STATUSES = ['Paid', 'Partial', 'Pending'];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
