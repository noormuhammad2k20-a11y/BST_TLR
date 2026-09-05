<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ClothStore\{Order,Customer};
use App\Services\ClothStore\FinanceService;
use App\Services\Decimal as D;

final class ReconcileIntegrity extends Command
{
    protected $signature='integrity:reconcile {--apply : Apply evidence-backed managed invoice recalculations only} {--order= : Restrict application to one invoice ID}';
    protected $description='Report inventory, receivable, orphan, and legacy-finance inconsistencies without deleting history';
    public function handle(): int
    {
        $drift=DB::table('cs_products as p')->leftJoin('cs_product_locations as l','l.cs_product_id','=','p.id')
            ->selectRaw('p.id, p.stock_quantity, COALESCE(SUM(l.quantity),0) as location_total')
            ->groupBy('p.id','p.stock_quantity')->havingRaw('p.stock_quantity <> COALESCE(SUM(l.quantity),0)')->get();
        $this->info('Inventory mismatches: '.$drift->count());
        foreach ($drift as $row) $this->line("Product {$row->id}: total {$row->stock_quantity}; locations {$row->location_total}; manual stock evidence required.");
        $negative=DB::table('cs_customers')->where('due_balance','<',0)->pluck('id');
        $this->line('Negative customer balances requiring transaction review: '.$negative->implode(', '));
        $legacy=DB::table('cs_returns')->whereIn('status',['Approved','Completed'])->whereNull('processed_at')->pluck('id');
        $this->line('Historical returns requiring credit/refund evidence: '.$legacy->implode(', '));
        $unallocated=DB::table('cs_customer_payments as p')->whereNull('cs_order_id')->where('status','Completed')
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('cs_payment_allocations as a')->whereColumn('a.payment_id','p.id'))
            ->whereNotIn('payment_type',['Reversal','Refund'])->pluck('p.id');
        $this->line('Legacy unallocated payments (do not guess invoice allocation): '.$unallocated->implode(', '));
        $this->line('Users with no cloth-store role: '.DB::table('users as u')->where('role','!=','admin')
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('cs_user_roles as r')->whereColumn('r.user_id','u.id'))->count());
        if (!$this->option('apply')) { $this->info('Dry run only. No records changed.'); return self::SUCCESS; }
        if (!$id=$this->option('order')) { $this->error('--apply requires one explicit --order ID.');return self::FAILURE; }
        $stub=Order::withTrashed()->findOrFail($id);
        DB::transaction(function () use ($stub,$id) {
            $customer=Customer::withTrashed()->whereKey($stub->cs_customer_id)->lockForUpdate()->firstOrFail();
            $order=Order::withTrashed()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($order->remaining_amount===null || DB::table('cs_returns')->where('cs_order_id',$id)->whereIn('status',['Approved','Completed'])->whereNull('processed_at')->exists()) {
                throw new \RuntimeException('Legacy invoice requires manual evidence; no changes applied.');
            }
            $before=['order_due'=>$order->remaining_amount,'paid'=>$order->paid_amount,'customer_due'=>$customer->due_balance];
            $finance=app(FinanceService::class); $finance->recalculate($order,$customer);
            $delta=D::sub((string)$customer->due_balance,(string)$before['customer_due']);
            if (D::cmp($delta,'0')!==0) $finance->ledger($customer,'Reconciliation','ORDER-'.$id,D::max($delta),D::max(D::sub('0',$delta)));
            DB::table('integrity_audits')->insert(['kind'=>'invoice_recalculation','source_table'=>'cs_orders','source_id'=>$id,
                'evidence'=>json_encode(['before'=>$before,'after'=>['order_due'=>$order->remaining_amount,'paid'=>$order->paid_amount,'customer_due'=>$customer->due_balance]])]);
        });
        $this->info('Selected invoice recalculated with audit evidence.');return self::SUCCESS;
    }
}
