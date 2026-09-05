<?php
namespace App\Services\ClothStore;

use App\Models\ClothStore\{Order,Customer,ReturnItem,Product};
use App\Services\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class OrderWorkflow
{
    public function __construct(private FinanceService $finance,private InventoryService $stock) {}
    public function transition(int $id,string $status): void
    {
        $stub=Order::findOrFail($id);
        DB::transaction(function () use ($stub,$id,$status) {
            $customer=Customer::withTrashed()->whereKey($stub->cs_customer_id)->lockForUpdate()->firstOrFail();
            $order=Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($order->status===$status) return;
            if (in_array($status,['Returned','Partially Returned'])) $this->fail('Use the return workflow to change return status.');
            if ($order->status==='Returned') $this->fail('A fully returned sale cannot be reinstated or cancelled.');
            $cancel=$status==='Cancelled'; $restore=$order->status==='Cancelled';
            if ($restore && $status!=='Pending') $this->fail('Reinstate a cancelled order to Pending first.');
            if (!$cancel && !$restore && $order->status==='Partially Returned') $this->fail('Partial return status is derived from returns.');
            if ($cancel || $restore) {
                if ($order->returnsPending ?? false) $this->fail('Resolve pending returns first.');
                if (DB::table('cs_returns')->where('cs_order_id',$id)->where('status','Pending')->exists()) $this->fail('Resolve pending returns before cancellation.');
                $this->finance->recalculate($order,$customer);
                $items=$order->items()->orderBy('cs_product_id')->get();
                Product::withTrashed()->whereIn('id',$items->pluck('cs_product_id'))->orderBy('id')->lockForUpdate()->get();
                $qty='0.00'; $cost='0.00'; $reportLines=[];
                foreach ($items as $item) {
                    $returned=(string)ReturnItem::where('cs_order_item_id',$item->id)->whereHas('returnOrder',fn($q)=>$q->whereIn('status',['Approved','Completed']))->sum('quantity');
                    $remaining=D::sub((string)$item->quantity,$returned);
                    if (D::cmp($remaining,'0')<=0) continue;
                    if ($cancel) $this->stock->restore($item->id,$item->cs_product_id,$remaining,$order->invoice_number);
                    else $this->stock->move($item->cs_product_id,D::sub('0',$remaining),'Sale reinstated',$order->invoice_number,null,$item->id);
                    $reportLines[]=['order_item_id'=>$item->id,'quantity'=>$remaining,'cost'=>D::mul($remaining,(string)$item->unit_cost),'amount'=>D::ratio(D::mul($remaining,(string)$item->unit_price),(string)$order->total_amount,(string)$order->subtotal)];
                    $qty=D::add($qty,$remaining); $cost=D::add($cost,D::mul($remaining,(string)$item->unit_cost));
                }
                $events=DB::table('cs_financial_adjustments')->where('order_id',$id);
                if ($cancel) {
                    $credits=(string)(clone $events)->where('kind','return')->sum('amount');
                    $amount=D::sub((string)$order->total_amount,$credits);
                } else {
                    $prior=(clone $events)->where('kind','cancel')->latest('id')->first();
                    if (!$prior) $this->fail('Historical cancellation requires reconciliation before reinstatement.');
                    $amount=$prior->amount;
                }
                $key=($cancel?'cancel:':'reinstate:').$id.':'.((clone $events)->count()+1);
                $this->finance->adjustment($order,$customer,$cancel?'cancel':'reinstate',$amount,$key,$qty,$cost);
                $adjustmentId=DB::table('cs_financial_adjustments')->where('operation_key',$key)->value('id');
                $assigned='0.00';
                foreach ($reportLines as $index=>$line) {
                    if ($index===count($reportLines)-1) $line['amount']=D::sub($amount,$assigned);
                    $assigned=D::add($assigned,$line['amount']);
                    DB::table('cs_adjustment_lines')->insert($line+['adjustment_id'=>$adjustmentId]);
                }
            }
            $order->update(['status'=>$status]);
            \App\Services\ActivityLogger::log('Sale status changed',$order->invoice_number.' → '.$status,'orders',$order,[], 'status_changed');
        },3);
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['status'=>$message]); }
}
