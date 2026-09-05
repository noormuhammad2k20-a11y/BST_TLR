<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $table = 'cs_expenses';
    protected $guarded = ['id'];

    protected $casts = [
        'expense_date' => 'date',
    ];
}
