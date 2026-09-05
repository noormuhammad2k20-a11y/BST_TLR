<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;

class CustomerLedger extends Model
{
    protected $table = 'cs_customer_ledgers';
    protected $guarded = ['id'];

    protected $casts = [
        'date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'cs_customer_id');
    }
}
