<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;

class CustomerPayment extends Model
{
    protected $table = 'cs_customer_payments';
    protected $guarded = ['id'];

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'cs_customer_id');
    }
}
