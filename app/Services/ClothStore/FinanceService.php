<?php

namespace App\Services\ClothStore;

use App\Models\ClothStore\{Customer, CustomerPayment, CustomerLedger, Order};
use App\Services\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinanceService
{
    public const METHODS = ['Cash','Card','Bank Transfer','EasyPaisa','JazzCash','Cheque'];

    /** Caller owns customer and order locks. Historical unexplained balances are never erased. */
    public function recalculate(Order $order, Customer $customer): void
    {
        $oldDue = $order->remaining_amount ?? D::max(D::sub((string)$order->total_amount,(string)$order->paid_amount));
        $paid = (string)CustomerPayment::where('cs_order_id',$order->id)->where('status','Completed')
            ->whereNull('reverses_payment_id')->whereNotIn('payment_type',['Refund','Reversal'])->sum('amount');
        $allocated = DB::table('cs_payment_allocations as a')->join('cs_customer_payments as p','p.id','=','a.payment_id')
            ->where('a.order_id',$order->id)->where('p.status','Completed')->whereNull('p.cs_order_id')->sum('a.amount');
        $paid=D::add($paid,(string)$allocated);
        $events=DB::table('cs_financial_adjustments')->where('order_id',$order->id)->get();
        $charge=(string)$order->total_amount; $refunded='0.00';
        foreach ($events as $event) {
            if (in_array($event->kind,['return','cancel'])) $charge=D::sub($charge,$event->amount);
            if ($event->kind==='reinstate') $charge=D::add($charge,$event->amount);
            if ($event->kind==='refund') $refunded=D::add($refunded,$event->amount);
        }
        if (D::cmp($charge,'0')<0) $this->fail('Credits exceed invoice value; reconciliation is required.');
        $retained=D::sub($paid,$refunded);
        $due=D::max(D::sub($charge,$retained));
        $refundDue=D::max(D::sub($retained,$charge));
        $newCustomerDue=D::add((string)$customer->due_balance,D::sub($due,(string)$oldDue));
        if (D::cmp($newCustomerDue,'0')<0) $this->fail('Customer balance requires reconciliation before this operation.');
        $order->update(['paid_amount'=>$retained,'remaining_amount'=>$due,'refund_due'=>$refundDue,
            'payment_status'=>D::cmp($due,'0')===0?'Paid':(D::cmp($retained,'0')>0?'Partial':'Unpaid')]);
        $customer->update(['due_balance'=>$newCustomerDue]);
    }

    public function collect(int $customerId, array $data, ?int $orderId=null): CustomerPayment
    {
        return DB::transaction(function () use ($customerId,$data,$orderId) {
            $customer=Customer::whereKey($customerId)->lockForUpdate()->firstOrFail();
            if (!empty($data['operation_key'])) {
                $existing=CustomerPayment::where('operation_key',$data['operation_key'])->first();
                if ($existing) $this->fail('This payment operation has already been submitted.');
            }
            $amount=D::value((string)$data['amount']);
            if (D::cmp($amount,'0')<=0 || D::cmp($amount,(string)$customer->due_balance)>0) $this->fail('Payment exceeds the outstanding balance or is not positive.');
            $orders=Order::where('cs_customer_id',$customerId)->when($orderId,fn($q)=>$q->whereKey($orderId))
                ->orderBy('id')->lockForUpdate()->get();
            foreach ($orders as $order) $this->recalculate($order,$customer);
            if (D::cmp($amount,(string)$customer->due_balance)>0) $this->fail('Payment exceeds the reconciled outstanding balance.');
            if ($orderId && ($orders->isEmpty() || D::cmp($amount,(string)$orders->first()->remaining_amount)>0)) $this->fail('Payment exceeds this invoice balance.');
            $payment=CustomerPayment::create(['cs_customer_id'=>$customerId,'cs_order_id'=>$orderId,'amount'=>$amount,
                'payment_type'=>'Due Payment','payment_method'=>$data['payment_method'],'reference'=>$data['reference']??null,
                'notes'=>$data['notes']??null,'payment_date'=>now(),'received_by'=>auth()->user()?->name,
                'status'=>'Completed','operation_key'=>$data['operation_key']??null]);
            $remaining=$amount;
            foreach ($orders as $order) {
                $part=D::min($remaining,(string)$order->remaining_amount);
                if (D::cmp($part,'0')>0) {
                    DB::table('cs_payment_allocations')->insert(['payment_id'=>$payment->id,'order_id'=>$order->id,'amount'=>$part,'created_at'=>now(),'updated_at'=>now()]);
                    $this->recalculate($order,$customer);
                    $remaining=D::sub($remaining,$part);
                }
            }
            if (D::cmp($remaining,'0')>0) {
                // Explicit allocation to documented customer-level opening receivable.
                DB::table('cs_payment_allocations')->insert(['payment_id'=>$payment->id,'order_id'=>null,'amount'=>$remaining,'created_at'=>now(),'updated_at'=>now()]);
                $customer->update(['due_balance'=>D::sub((string)$customer->due_balance,$remaining)]);
            }
            $this->ledger($customer,'Payment','PAY-'.$payment->id,'0.00',$amount);
            return $payment;
        },3);
    }

    public function reverse(int $paymentId): void
    {
        $stub=CustomerPayment::findOrFail($paymentId);
        DB::transaction(function () use ($stub,$paymentId) {
            $customer=Customer::withTrashed()->whereKey($stub->cs_customer_id)->lockForUpdate()->firstOrFail();
            $ids=DB::table('cs_payment_allocations')->where('payment_id',$paymentId)->whereNotNull('order_id')->pluck('order_id')->all();
            if ($stub->cs_order_id) $ids[]=$stub->cs_order_id;
            $orders=Order::withTrashed()->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();
            $payment=CustomerPayment::whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ($payment->status!=='Completed' || $payment->reverses_payment_id || in_array($payment->payment_type,['Refund','Reversal'])) $this->fail('This payment cannot be reversed again.');
            foreach ($orders as $order) $this->recalculate($order,$customer);
            $before=(string)$customer->due_balance;
            $payment->update(['status'=>'Reversed','reversed_at'=>now(),'reversed_by'=>auth()->id()]);
            CustomerPayment::create(['cs_customer_id'=>$customer->id,'cs_order_id'=>$payment->cs_order_id,
                'amount'=>$payment->amount,'payment_type'=>'Reversal','payment_method'=>$payment->payment_method,
                'reference'=>'REV-'.$payment->id,'payment_date'=>now(),'received_by'=>auth()->user()?->name,
                'status'=>'Completed','reverses_payment_id'=>$payment->id]);
            foreach ($orders as $order) $this->recalculate($order,$customer);
            $opening=(string)DB::table('cs_payment_allocations')->where('payment_id',$paymentId)->whereNull('order_id')->sum('amount');
            if ($orders->isEmpty() && D::cmp($opening,'0')===0) $opening=(string)$payment->amount;
            $customer->update(['due_balance'=>D::add((string)$customer->due_balance,$opening)]);
            $this->ledger($customer,'Payment Reversal','REV-'.$paymentId,D::sub((string)$customer->due_balance,$before),'0.00');
        },3);
    }

    public function adjustment(Order $order, Customer $customer, string $kind, string $amount, string $key, string $quantity='0.00', string $cost='0.00'): void
    {
        if (DB::table('cs_financial_adjustments')->where('operation_key',$key)->exists()) $this->fail('This financial operation has already been processed.');
        $before=(string)$customer->due_balance;
        DB::table('cs_financial_adjustments')->insert(['order_id'=>$order->id,'kind'=>$kind,'amount'=>$amount,'quantity'=>$quantity,
            'cost'=>$cost,'operation_key'=>$key,'reference'=>$order->invoice_number,'actor_id'=>auth()->id(),'created_at'=>now(),'updated_at'=>now()]);
        $this->recalculate($order,$customer);
        $delta=D::sub((string)$customer->due_balance,$before);
        $this->ledger($customer,ucfirst($kind),$key,D::max($delta),D::max(D::sub('0',$delta)));
        if (in_array($kind,['return','cancel','reinstate'])) {
            $change=$kind==='reinstate'?$amount:D::sub('0',$amount);
            $customer->update(['total_purchases'=>D::add((string)$customer->total_purchases,$change)]);
        }
    }

    public function settle(Order $order, Customer $customer, string $key): void
    {
        $this->recalculate($order,$customer);
        $amount=(string)$order->refund_due;
        if (D::cmp($amount,'0')>0) $this->adjustment($order,$customer,'refund',$amount,$key);
    }

    public function ledger(Customer $customer,string $type,string $reference,string $debit,string $credit): void
    {
        CustomerLedger::create(['cs_customer_id'=>$customer->id,'date'=>now(),'type'=>$type,'reference'=>$reference,
            'description'=>$type.' '.$reference,'debit'=>$debit,'credit'=>$credit,'balance'=>$customer->due_balance]);
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['payment'=>$message]); }
}
