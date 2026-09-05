<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use \Illuminate\Database\Eloquent\SoftDeletes;

    public const METHODS = ['Cash', 'Bank Transfer', 'Card', 'Other'];
    public const STATUSES = ['Pending', 'Approved', 'Rejected'];
    protected $table = 'cs_expenses';
    protected $guarded = ['id'];

    protected $casts = [
        'expense_date' => 'date',
    ];
}
