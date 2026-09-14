<?php
namespace App\Services;

use App\Models\{Order,Payment};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TailoringFinanceService
{
    public function paid(Order $order): string
    {
        $valid=(string)Payment::where('order_id',$order->id)->where('status','Completed')->whereNull('reverses_payment_id')->sum('amount');
        // Reversed deposits still prove that the advance was recorded; never resurrect them as legacy cash.
        $recorded=(string)Payment::where('order_id',$order->id)->where('type','Advance')->whereNull('reverses_payment_id')->sum('amount');
        return Decimal::add($valid,Decimal::max(Decimal::sub((string)$order->advance,$recorded)));
    }
    public function record(int $id,array $data,bool $allowPartial = false): Payment
    {
        return DB::transaction(function () use ($id,$data,$allowPartial) {
            $stub=Order::withTrashed()->findOrFail($id);
            \App\Models\Customer::withTrashed()->whereKey($stub->customer_id)->lockForUpdate()->firstOrFail();
            $order=Order::withTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
            if (!empty($data['operation_key']) && $existing=Payment::where('operation_key',$data['operation_key'])->first()) {
                if ($existing->order_id!==$order->id || Decimal::cmp((string)$existing->amount,(string)$data['amount'])!==0
                    || $existing->payment_method!==$data['payment_method'] || $existing->status!=='Completed') $this->fail('Payment reference already used.');
                return $existing;
            }
            if ($order->status==='Cancelled') $this->fail('Cancelled orders cannot receive payments.');
            $due=Decimal::max(Decimal::sub((string)$order->total,$this->paid($order)));
            $amount=Decimal::value((string)$data['amount']);
            if (Decimal::cmp($amount,'0')<=0 || Decimal::cmp($amount,$due)>0) $this->fail('Payment exceeds the outstanding balance.');
            if (!$allowPartial && !Settings::bool('allow_partial') && Decimal::cmp($amount,$due)!==0) $this->fail('Partial payments are disabled.');
            $payment=Payment::create(['invoice_id'=>$order->display_invoice,'order_id'=>$order->id,'customer_id'=>$order->customer_id,
                'amount'=>$amount,'type'=>'Receipt','status'=>'Completed','payment_method'=>$data['payment_method'],
                'reference'=>$data['reference']??null,'notes'=>$data['notes']??null,'date'=>$data['date']??now(),
                'recorded_by'=>auth()->id(),'operation_key'=>$data['operation_key']??null]);
            app(OrderService::class)->recalculateBalance($order);
            ActivityLogger::log('Payment recorded',$order->display_invoice,'payments',$payment,['amount'=>$amount],'created');
            return $payment;
        },3);
    }
    public function reverse(int $id): void
    {
        $stub=Payment::findOrFail($id);
        DB::transaction(function () use ($stub,$id) {
            if ($stub->customer_id) \App\Models\Customer::withTrashed()->whereKey($stub->customer_id)->lockForUpdate()->firstOrFail();
            $order=$stub->order_id?Order::withTrashed()->whereKey($stub->order_id)->lockForUpdate()->firstOrFail():null;
            $payment=Payment::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($payment->status!=='Completed' || $payment->reverses_payment_id) $this->fail('Payment is already reversed.');
            $payment->update(['status'=>'Reversed','reversed_at'=>now(),'reversed_by'=>auth()->id()]);
            Payment::create(['invoice_id'=>$payment->invoice_id,'order_id'=>$payment->order_id,'customer_id'=>$payment->customer_id,
                'amount'=>$payment->amount,'type'=>'Reversal','status'=>'Completed','payment_method'=>$payment->payment_method,
                'date'=>now(),'recorded_by'=>auth()->id(),'reverses_payment_id'=>$id,'reference'=>'REV-'.$id]);
            if ($order) app(OrderService::class)->recalculateBalance($order);
            ActivityLogger::log('Payment reversed','Payment '.$id,'payments',$payment,['amount'=>$payment->amount],'reversed');
        },3);
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['amount'=>$message]); }
}
