<?php

namespace App\Models\ClothStore;

use Illuminate\Database\Eloquent\Model;

class ReturnOrder extends Model
{
    protected $table = 'cs_returns';
    protected $guarded = ['id'];

    protected $casts = [
        'total_refund_amount' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'cs_order_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'cs_customer_id');
    }

    public function items()
    {
        return $this->hasMany(ReturnItem::class, 'cs_return_id');
    }

    protected static function boot()
    {
        parent::boot();

        /*
         * return_number is UNIQUE. It used to be built from rand(1000, 9999)
         * scoped to the day, which collides far sooner than it looks: by the
         * birthday paradox roughly 100 returns in one day gives about a 40%
         * chance of two matching numbers, and the insert then fails with a
         * duplicate key.
         *
         * Deriving it from the primary key makes it unique by construction
         * and readable as a sequence: RTN-000042.
         */
        static::creating(function (self $model) {
            if (empty($model->return_number)) {
                $model->return_number = 'TMP-' . uniqid();
            }
        });

        static::created(function (self $model) {
            if (str_starts_with((string) $model->return_number, 'TMP-')) {
                $model->return_number = 'RTN-' . str_pad((string) $model->id, 6, '0', STR_PAD_LEFT);
                $model->saveQuietly();
            }
        });
    }
}
