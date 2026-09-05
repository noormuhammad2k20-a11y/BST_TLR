<?php

namespace App\Services\ClothStore;

use App\Models\ClothStore\{Order, Customer, ReturnOrder, ReturnItem, Product};
use App\Services\Decimal as D;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ReturnService
{
    public function __construct(private FinanceService $finance, private InventoryService $stock) {}

    public function create(array $data): ReturnOrder
    {
        return DB::transaction(function () use ($data) {
            $order=Order::whereKey($data['order_id'])->lockForUpdate()->firstOrFail();
            if ($order->status==='Cancelled') $this->fail('Cancelled orders cannot be returned.');
            $return=ReturnOrder::create(['cs_order_id'=>$order->id,'cs_customer_id'=>$order->cs_customer_id,
                'status'=>'Pending','notes'=>$data['notes']??null,'total_refund_amount'=>'0.00']);
            $total='0.00';
            foreach ($data['items'] as $input) {
                $line=$order->items()->whereKey($input['order_item_id'])->firstOrFail();
                $qty=D::value((string)$input['quantity']);
                $used=(string)ReturnItem::where('cs_order_item_id',$line->id)->whereHas('returnOrder',
                    fn($q)=>$q->whereIn('status',['Pending','Approved','Completed']))->sum('quantity');
                if (D::cmp(D::add($used,$qty),(string)$line->quantity)>0) $this->fail('Returned quantity exceeds the remaining sold quantity.');
                if ($input['action_type']==='Exchange' && empty($input['exchange_product_id'])) $this->fail('An exchange requires a replacement product.');
                // A cumulative proportional allocation gives the final fragment the exact rounding residue.
                $lineNet=D::ratio((string)$line->total,(string)$order->total_amount,(string)$order->subtotal);
                $value=D::sub(D::ratio($lineNet,D::add($used,$qty),(string)$line->quantity),D::ratio($lineNet,$used,(string)$line->quantity));
                if ($input['action_type']==='Exchange') $value='0.00';
                ReturnItem::create(['cs_return_id'=>$return->id,'cs_order_item_id'=>$line->id,'cs_product_id'=>$line->cs_product_id,
                    'quantity'=>$qty,'reason'=>$input['reason'],'action_type'=>$input['action_type'],
                    'exchange_product_id'=>$input['action_type']==='Exchange'?$input['exchange_product_id']:null,'refund_amount'=>$value]);
                $total=D::add($total,$value);
            }
            $return->update(['total_refund_amount'=>$total]);
            return $return;
        },3);
    }

    public function transition(int $id, string $status): void
    {
        $stub=ReturnOrder::findOrFail($id);
        DB::transaction(function () use ($id,$status,$stub) {
            $customer=Customer::withTrashed()->whereKey($stub->cs_customer_id)->lockForUpdate()->firstOrFail();
            $order=Order::withTrashed()->whereKey($stub->cs_order_id)->lockForUpdate()->firstOrFail();
            $return=ReturnOrder::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($return->status==='Completed' || $return->status==='Rejected' || $return->status===$status) $this->fail('Return is already processed or unchanged.');
            if ($return->status==='Approved' && $status!=='Completed') $this->fail('Approved returns can only be completed.');
            if ($order->status==='Cancelled') $this->fail('Cancelled orders cannot be returned.');
            if ($return->status==='Approved' && !$return->processed_at) $this->fail('Historical approved return requires reconciliation before settlement.');
            if (in_array($status,['Approved','Completed']) && !$return->processed_at) {
                $this->finance->recalculate($order,$customer);
                $items=$return->items()->orderBy('cs_product_id')->get();
                $productIds=$items->pluck('cs_product_id')->merge($items->pluck('exchange_product_id')->filter())->unique()->sort()->values();
                Product::withTrashed()->whereIn('id',$productIds)->orderBy('id')->lockForUpdate()->get();
                $quantity='0.00'; $cost='0.00'; $refund='0.00'; $reportLines=[];
                foreach ($items as $item) {
                    $line=$order->items()->whereKey($item->cs_order_item_id)->firstOrFail();
                    $used=(string)ReturnItem::where('cs_order_item_id',$line->id)->where('cs_return_id','!=',$id)
                        ->whereHas('returnOrder',fn($q)=>$q->whereIn('status',['Approved','Completed']))->sum('quantity');
                    if (D::cmp(D::add($used,(string)$item->quantity),(string)$line->quantity)>0) $this->fail('This quantity has already been returned.');
                    $this->stock->restore($line->id,$line->cs_product_id,(string)$item->quantity,$return->return_number);
                    if ($item->action_type==='Exchange') {
                        $replacement=Product::whereKey($item->exchange_product_id)->where('status','Active')->firstOrFail();
                        $this->stock->move($replacement->id,D::sub('0',(string)$item->quantity),'Exchange',$return->return_number);
                    } else {
                        $quantity=D::add($quantity,(string)$item->quantity);
                        $cost=D::add($cost,D::mul((string)$item->quantity,(string)$line->unit_cost));
                        $refund=D::add($refund,(string)$item->refund_amount);
                        $reportLines[]=['order_item_id'=>$line->id,'amount'=>(string)$item->refund_amount,'quantity'=>(string)$item->quantity,'cost'=>D::mul((string)$item->quantity,(string)$line->unit_cost)];
                    }
                }
                // When all original goods are refunded, consume the invoice's final penny residue.
                $sold=(string)$order->items()->sum('quantity');
                $priorRefundQty=(string)ReturnItem::where('action_type','Refund')->where('cs_return_id','!=',$id)
                    ->whereHas('returnOrder',fn($q)=>$q->where('cs_order_id',$order->id)->whereIn('status',['Approved','Completed']))->sum('quantity');
                if (D::cmp(D::add($priorRefundQty,$quantity),$sold)===0) {
                    $credited=(string)DB::table('cs_financial_adjustments')->where('order_id',$order->id)->where('kind','return')->sum('amount');
                    $refund=D::sub((string)$order->total_amount,$credited);
                }
                $this->finance->adjustment($order,$customer,'return',$refund,'return:'.$id,$quantity,$cost);
                $adjustmentId=DB::table('cs_financial_adjustments')->where('operation_key','return:'.$id)->value('id');
                $assigned='0.00';
                foreach ($reportLines as $index=>$reportLine) {
                    if ($index===count($reportLines)-1) $reportLine['amount']=D::sub($refund,$assigned);
                    $assigned=D::add($assigned,$reportLine['amount']);
                    DB::table('cs_adjustment_lines')->insert($reportLine+['adjustment_id'=>$adjustmentId]);
                }
                $return->update(['processed_at'=>now(),'total_refund_amount'=>$refund]);
            }
            if ($status==='Completed') {
                $this->finance->settle($order,$customer,'refund:return:'.$id);
                $return->settled_at=now();
            }
            $return->status=$status; $return->save();
            if (in_array($status,['Approved','Completed'])) {
                $returned=(string)ReturnItem::whereHas('returnOrder',fn($q)=>$q->where('cs_order_id',$order->id)->whereIn('status',['Approved','Completed']))->sum('quantity');
                $order->update(['status'=>D::cmp($returned,(string)$order->items()->sum('quantity'))>=0?'Returned':'Partially Returned']);
            }
        },3);
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['return'=>$message]); }
}
