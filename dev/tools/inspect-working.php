<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
echo json_encode([
    'order_columns'=>Schema::getColumnListing('cs_orders'),
    'supplier_system_removed'=>!Schema::hasTable('cs_suppliers') && !Schema::hasColumn('cs_products','cs_supplier_id'),
    'negative_customer_count'=>DB::table('cs_customers')->where('due_balance','<',0)->count(),
    'stock_mismatch_count'=>DB::table('cs_products as p')->whereRaw('p.stock_quantity <> (SELECT COALESCE(SUM(quantity),0) FROM cs_product_locations WHERE cs_product_id=p.id)')->count(),
    'unlinked_customer_payment_count'=>DB::table('cs_customer_payments')->whereNull('cs_order_id')->count(),
    'orphan_payment_order_count'=>DB::table('cs_customer_payments')->whereNotNull('cs_order_id')->whereNotIn('cs_order_id',DB::table('cs_orders')->select('id'))->count(),
    'historical_processed_return_count'=>DB::table('cs_returns')->whereIn('status',['Approved','Completed'])->count(),
    'role_grants'=>DB::table('cs_roles as r')->leftJoin('cs_role_permissions as p','p.role_id','=','r.id')->selectRaw('r.name, COUNT(p.permission_id) as permission_count')->groupBy('r.id','r.name')->get(),
    'schema'=>collect(['cs_customer_payments','payments','staff_payments','cs_financial_adjustments','cs_inventory_allocations'])
        ->mapWithKeys(fn($table)=>[$table=>collect(Schema::getColumns($table))->map(fn($column)=>[
            'name'=>$column['name'],'type'=>$column['type_name'],'nullable'=>$column['nullable']])->all()]),
],JSON_PRETTY_PRINT).PHP_EOL;
