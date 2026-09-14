<?php
namespace App\Services;

use App\Models\{Customer, Order, Payment};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CustomerLedger
{
    public function orderDues(Order $order): array
    {
        $current=(string)$order->balance_due;
        $total=$order->customer ? $this->statement($order->customer)['due'] : $current;
        return ['current_order_due'=>(float)$current,
            'previous_due'=>(float)Decimal::max(Decimal::sub($total,$current)),
            'customer_total_due'=>(float)$total];
    }
    public function statement(Customer $customer): array
    {
        $rows=[]; $debts=[]; $sales='0.00'; $paid='0.00'; $due='0.00'; $previous='0.00';
        $orders=Order::withTrashed()->where('customer_id',$customer->id)->where('status','!=','Cancelled')->get();
        $payments=Payment::where('customer_id',$customer->id)->whereNull('reverses_payment_id')->get();
        foreach ($orders as $order) {
            $amount=(string)$order->total; $received=app(TailoringFinanceService::class)->paid($order);
            $balance=Decimal::max(Decimal::sub($amount,$received));
            $sales=Decimal::add($sales,$amount); $due=Decimal::add($due,$balance);
            $rows[]=$this->row($order->created_at, $order->display_number, 'Order / sale', $amount, '0');
            $debts[]=['order_id'=>$order->id,'charge_id'=>null,'due'=>$balance,'date'=>(string)$order->created_at];
            $recorded=$payments->where('order_id',$order->id)->where('type','Advance')->sum('amount');
            $legacy=Decimal::max(Decimal::sub((string)$order->advance,(string)$recorded));
            if (Decimal::cmp($legacy,'0')>0) {
                $rows[]=$this->row($order->created_at,$order->display_number,'Opening advance (already received)','0',$legacy);
                $paid=Decimal::add($paid,$legacy);
            }
        }
        $charges=DB::table('customer_ledger_charges')->where('customer_id',$customer->id)->get();
        foreach ($charges as $charge) {
            $received=(string)$payments->where('ledger_charge_id',$charge->id)->where('status','Completed')->sum('amount');
            $balance=Decimal::max(Decimal::sub($charge->amount,$received));
            if ($charge->type==='Opening Due') $previous=Decimal::add($previous,$charge->amount);
            else $sales=Decimal::add($sales,$charge->amount);
            $due=Decimal::add($due,$balance);
            $rows[]=$this->row($charge->date,'L-'.$charge->id,$charge->type.': '.$charge->description,$charge->amount,'0');
            $debts[]=['order_id'=>null,'charge_id'=>$charge->id,'due'=>$balance,'date'=>$charge->date];
        }
        foreach ($payments as $p) {
            if (!in_array($p->status,['Completed','Reversed'],true)) continue;
            if (!$orders->contains('id',$p->order_id) && !$charges->contains('id',$p->ledger_charge_id)) continue;
            $rows[]=$this->row($p->date ?? $p->created_at,$p->invoice_id ?? 'P-'.$p->id,'Payment · '.$p->payment_method,'0',(string)$p->amount);
            if ($p->status==='Completed') $paid=Decimal::add($paid,(string)$p->amount);
            else $rows[]=$this->row($p->reversed_at ?? $p->updated_at,'REV-'.$p->id,'Payment reversed',(string)$p->amount,'0');
        }
        $running='0.00';
        $rows=collect($rows)->sortBy('date')->values()->map(function($row) use (&$running) {
            $running=Decimal::sub(Decimal::add($running,$row['debit']),$row['credit']);
            return $row+['balance'=>$running];
        })->all();
        return ['previous'=>$previous,'sales'=>$sales,'paid'=>$paid,'due'=>$due,'rows'=>$rows,
            'debts'=>collect($debts)->sortBy('date')->values()->all(),'status'=>Decimal::cmp($due,'0')===0?'Paid':'Payment Pending'];
    }

    public function receive(Customer $customer,array $data): array
    {
        return DB::transaction(function() use($customer,$data) {
            Customer::whereKey($customer->id)->lockForUpdate()->firstOrFail();
            $prefix='ledger:'.$data['operation_key'].':';
            $existing=Payment::where('customer_id',$customer->id)->where('operation_key','like',$prefix.'%')->get();
            if ($existing->isNotEmpty()) {
                if ($existing->contains(fn($p)=>$p->status!=='Completed' || $p->payment_method!==$data['payment_method'])
                    || Decimal::cmp((string)$existing->sum('amount'),(string)$data['amount'])!==0) $this->fail('This payment reference was already used.');
                return $this->statement($customer);
            }
            $statement=$this->statement($customer); $remaining=Decimal::value((string)$data['amount']);
            if (Decimal::cmp($remaining,'0')<=0 || Decimal::cmp($remaining,$statement['due'])>0) $this->fail('Enter an amount within the current total due.');
            foreach($statement['debts'] as $index=>$debt) {
                $amount=Decimal::min($remaining,$debt['due']);
                if (Decimal::cmp($amount,'0')<=0) continue;
                $entry=$data+['date'=>now()]; $entry['amount']=$amount; $entry['operation_key']=$prefix.$index;
                if ($debt['order_id']) app(TailoringFinanceService::class)->record($debt['order_id'],$entry,true);
                else Payment::create(['customer_id'=>$customer->id,'ledger_charge_id'=>$debt['charge_id'],'amount'=>$amount,
                    'type'=>'Receipt','status'=>'Completed','payment_method'=>$data['payment_method'],'date'=>$entry['date'],
                    'notes'=>$data['notes']??null,'recorded_by'=>auth()->id(),'operation_key'=>$entry['operation_key']]);
                $remaining=Decimal::sub($remaining,$amount);
                if (Decimal::cmp($remaining,'0')===0) break;
            }
            StatsService::flush();
            ActivityLogger::log('Ledger payment received',$customer->display_code,'payments',$customer,['amount'=>$data['amount']],'created');
            return $this->statement($customer);
        },3);
    }
    private function row($date,string $reference,string $description,string $debit,string $credit): array {
        return ['date'=>(string)$date,'reference'=>$reference,'description'=>$description,'debit'=>$debit,'credit'=>$credit];
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['amount'=>$message]); }
}
