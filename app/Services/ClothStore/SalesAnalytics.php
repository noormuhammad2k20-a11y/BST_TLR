<?php
namespace App\Services\ClothStore;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

final class SalesAnalytics
{
    /** Each credit belongs to its processing date, not the original sale date. */
    public static function orders(): Builder
    {
        $sales=DB::table('cs_orders')->selectRaw('id, cs_customer_id, payment_method, created_at, total_amount, gross_profit, total_meters_sold, discount, 1 as sale_count');
        $adjust=DB::table('cs_financial_adjustments as a')->join('cs_orders as o','o.id','=','a.order_id')
            ->whereIn('a.kind',['return','cancel','reinstate'])->selectRaw("o.id, o.cs_customer_id, o.payment_method, a.created_at,
                IF(a.kind='reinstate',1,-1)*a.amount as total_amount,
                IF(a.kind='reinstate',1,-1)*(a.amount-a.cost) as gross_profit,
                IF(a.kind='reinstate',1,-1)*a.quantity as total_meters_sold,
                IF(a.kind='reinstate',1,-1)*IF(o.total_amount=0,0,o.discount*a.amount/o.total_amount) as discount, 0 as sale_count");
        return DB::query()->fromSub($sales->unionAll($adjust),'cs_orders');
    }
    public static function lines(): Builder
    {
        $sales=DB::table('cs_order_items as i')->join('cs_orders as o','o.id','=','i.cs_order_id')
            ->selectRaw('i.id, i.cs_order_id, i.cs_product_id, i.quantity, i.unit_cost, i.unit_price,
                IF(o.subtotal=0,0,i.total*o.total_amount/o.subtotal) as total, o.created_at');
        $adjust=DB::table('cs_adjustment_lines as l')->join('cs_financial_adjustments as a','a.id','=','l.adjustment_id')
            ->join('cs_order_items as i','i.id','=','l.order_item_id')
            ->selectRaw("i.id, i.cs_order_id, i.cs_product_id, IF(a.kind='reinstate',1,-1)*l.quantity as quantity,
                i.unit_cost, i.unit_price, IF(a.kind='reinstate',1,-1)*l.amount as total, a.created_at");
        return DB::query()->fromSub($sales->unionAll($adjust),'cs_order_items');
    }
    public static function totals($start,$end): array
    {
        $query=self::orders()->whereBetween('created_at',[$start,$end]);
        return ['sales'=>(string)(clone $query)->sum('total_amount'),'profit'=>(string)(clone $query)->sum('gross_profit'),
            'quantity'=>(string)(clone $query)->sum('total_meters_sold'),'discount'=>(string)(clone $query)->sum('discount'),
            'transactions'=>(int)(clone $query)->sum('sale_count')];
    }
}
