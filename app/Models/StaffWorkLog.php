<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One piece of finished stitching, credited to one tailor.
 *
 * `rate` and `amount` are stored rather than recalculated on read. A tailor's
 * per-suit rate changes over time, and work already completed has to keep the
 * rate it was actually earned at — otherwise raising someone's rate silently
 * rewrites what they were owed last month.
 */
class StaffWorkLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'quantity'     => 'decimal:2',
        'rate'         => 'decimal:2',
        'amount'       => 'decimal:2',
        'completed_on' => 'date',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
