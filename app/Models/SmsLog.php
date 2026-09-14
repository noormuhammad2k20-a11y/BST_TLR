<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class)->withTrashed();
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function collectionOrders()
    {
        return $this->belongsToMany(Order::class,'collection_sms_orders')->withTrashed()->withPivot('reason');
    }

    /**
     * Log a sent or failed SMS.
     */
    public static function record(array $data): self
    {
        return static::create([
            'phone'       => $data['phone'] ?? '',
            'message'     => $data['message'] ?? '',
            'template_id' => $data['template_id'] ?? null,
            'provider'    => $data['provider'] ?? 'sendpk',
            'provider_message_id' => $data['provider_message_id'] ?? null,
            'status'      => $data['status'] ?? 'sent',
            'error'       => $data['error'] ?? null,
            'order_id'    => $data['order_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'api_response' => $data['api_response'] ?? null,
            'sent_at'     => $data['sent_at'] ?? now(),
        ]);
    }
}
