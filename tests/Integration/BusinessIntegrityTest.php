<?php
namespace Tests\Integration;

use Tests\TestCase;
use App\Models\User;
use App\Models\ClothStore\{Customer,Category,Product,Location,ProductLocation,Order,CustomerPayment};
use App\Services\ClothStore\{FinanceService,InventoryService,ReturnService,OrderWorkflow,SalesAnalytics};
use App\Services\{Decimal as D,Settings};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('INTEGRITY_MYSQL')!=='1') $this->markTestSkipped('Run with INTEGRITY_MYSQL=1 against the isolated MySQL database.');
        $this->assertSame('atelier_integrity_test',DB::connection()->getDatabaseName());
        DB::beginTransaction(); Settings::flush();
        $this->actingAs(User::factory()->create(['role'=>'admin','is_active'=>true]));
    }
    protected function tearDown(): void
    {
        if (getenv('INTEGRITY_MYSQL')==='1') while (DB::transactionLevel()>0) DB::rollBack();
        parent::tearDown();
    }
    protected function sale(string $paid='100.00',string $quantity='10.00',string $discount='0.00'): array
    {
        $customer=Customer::create(['name'=>'Integrity customer','phone'=>'TEST-'.uniqid(),'due_balance'=>'0.00','total_purchases'=>'0.00']);
        $category=Category::create(['name'=>'Integrity fabric']);
        $product=Product::create(['cs_category_id'=>$category->id,'name'=>'Integrity cotton','sku'=>'TEST-'.uniqid(),
            'price'=>'10.00','cost_price'=>'4.00','stock_quantity'=>'0.00','unit'=>'meter','status'=>'Active']);
        $main=Location::where('name','Main Store')->firstOrFail();
        $other=Location::create(['name'=>'Test warehouse','type'=>'Warehouse','is_active'=>true]);
        app(InventoryService::class)->move($product->id,'4.00','Test fixture','fixture',$main->id);
        app(InventoryService::class)->move($product->id,'16.00','Test fixture','fixture',$other->id);
        $response=$this->postJson(route('cloth-store.checkout.store'),['cs_customer_id'=>$customer->id,
            'items'=>[['cs_product_id'=>$product->id,'quantity'=>$quantity]],'paid_amount'=>$paid,'discount'=>$discount,'payment_method'=>'Cash']);
        $response->assertOk()->assertJsonPath('success',true);
        return [$customer->fresh(),$product->fresh(),Order::findOrFail($response->json('order.id'))];
    }
    protected function returned(Order $order,string $qty,string $action='Refund',?int $replacement=null)
    {
        return app(ReturnService::class)->create(['order_id'=>$order->id,'items'=>[['order_item_id'=>$order->items()->first()->id,
            'quantity'=>$qty,'reason'=>'Fit','action_type'=>$action,'exchange_product_id'=>$replacement]]]);
    }
    public function test_multilocation_checkout_keeps_stock_equal(): void
    {
        [$c,$p,$o]=$this->sale('70.00','7.00');
        $this->assertSame('13.00',$p->stock_quantity);
        $this->assertSame('13.00',(string)ProductLocation::where('cs_product_id',$p->id)->sum('quantity'));
        $this->assertSame('0.00',$c->due_balance);
        $this->assertSame('Paid',$o->payment_status);
    }
    public function test_full_paid_refund_retains_zero_due_and_records_disbursement(): void
    {
        [$c,$p,$o]=$this->sale(); $r=$this->returned($o,'10.00');
        app(ReturnService::class)->transition($r->id,'Approved');
        $this->assertSame('0.00',$c->fresh()->due_balance);
        $this->assertSame('100.00',$o->fresh()->refund_due);
        app(ReturnService::class)->transition($r->id,'Completed');
        $this->assertSame('0.00',$o->fresh()->refund_due);
        $this->assertSame('100.00',(string)DB::table('cs_financial_adjustments')->where('order_id',$o->id)->where('kind','refund')->sum('amount'));
        $this->assertSame('20.00',$p->fresh()->stock_quantity);
        $this->assertSame('0.00',SalesAnalytics::totals(now()->startOfDay(),now()->endOfDay())['sales']);
        $this->expectException(ValidationException::class); app(ReturnService::class)->transition($r->id,'Completed');
    }
    public function test_partial_payment_return_splits_receivable_and_refund(): void
    {
        [$c,$p,$o]=$this->sale('60.00'); $r=$this->returned($o,'6.00');
        app(ReturnService::class)->transition($r->id,'Approved');
        $this->assertSame('0.00',$c->fresh()->due_balance);
        $this->assertSame('20.00',$o->fresh()->refund_due);
        $this->assertSame('40.00',SalesAnalytics::totals(now()->startOfDay(),now()->endOfDay())['sales']);
    }
    public function test_unpaid_return_reduces_only_receivable(): void
    {
        [$c,$p,$o]=$this->sale('0.00'); $r=$this->returned($o,'3.00');
        app(ReturnService::class)->transition($r->id,'Completed');
        $this->assertSame('70.00',$c->fresh()->due_balance); $this->assertSame('0.00',$o->fresh()->refund_due);
    }
    public function test_payment_allocation_reversal_and_duplicate_guard(): void
    {
        [$c,$p,$o]=$this->sale('0.00');
        $pay=app(FinanceService::class)->collect($c->id,['amount'=>'60.00','payment_method'=>'Cash']);
        $this->assertSame('40.00',$o->fresh()->remaining_amount);
        app(FinanceService::class)->reverse($pay->id);
        $this->assertSame('100.00',$o->fresh()->remaining_amount);
        $this->assertSame('100.00',$c->fresh()->due_balance);
        $this->assertSame('Reversed',$pay->fresh()->status);
        $this->expectException(ValidationException::class); app(FinanceService::class)->reverse($pay->id);
    }
    public function test_rejects_overpayment(): void
    {
        [$c]=$this->sale('60.00');
        $this->expectException(ValidationException::class);
        app(FinanceService::class)->collect($c->id,['amount'=>'41.00','payment_method'=>'Cash']);
    }
    public function test_cancel_and_reinstate_are_atomic_without_duplicate_stock(): void
    {
        [$c,$p,$o]=$this->sale('60.00');
        app(OrderWorkflow::class)->transition($o->id,'Cancelled');
        $this->assertSame('0.00',$c->fresh()->due_balance); $this->assertSame('60.00',$o->fresh()->refund_due);
        $this->assertSame('20.00',$p->fresh()->stock_quantity);
        app(OrderWorkflow::class)->transition($o->id,'Cancelled');
        $this->assertSame('20.00',$p->fresh()->stock_quantity);
        app(OrderWorkflow::class)->transition($o->id,'Pending');
        $this->assertSame('10.00',$p->fresh()->stock_quantity); $this->assertSame('40.00',$c->fresh()->due_balance);
    }
    public function test_cancellation_after_partial_return_does_not_restore_twice(): void
    {
        [$c,$p,$o]=$this->sale('0.00'); $r=$this->returned($o,'3.00'); app(ReturnService::class)->transition($r->id,'Approved');
        app(OrderWorkflow::class)->transition($o->id,'Cancelled');
        $this->assertSame('20.00',$p->fresh()->stock_quantity); $this->assertSame('0.00',$c->fresh()->due_balance);
    }
    public function test_second_return_cannot_exceed_sold_quantity(): void
    {
        [$c,$p,$o]=$this->sale();$this->returned($o,'7.00');
        $this->expectException(ValidationException::class);$this->returned($o,'4.00');
    }
    public function test_exchange_updates_both_product_locations(): void
    {
        [$c,$p,$o]=$this->sale();
        $replacement=$p->replicate();$replacement->sku='EX-'.uniqid();$replacement->stock_quantity='0.00';$replacement->save();
        app(InventoryService::class)->move($replacement->id,'5.00','Fixture','fixture');
        $r=$this->returned($o,'2.00','Exchange',$replacement->id); app(ReturnService::class)->transition($r->id,'Completed');
        $this->assertSame('12.00',$p->fresh()->stock_quantity);$this->assertSame('3.00',$replacement->fresh()->stock_quantity);
        $this->assertSame('0.00',$c->fresh()->due_balance);
    }
    public function test_archival_preserves_payments_ledgers_and_customer_link(): void
    {
        [$c,$p,$o]=$this->sale();$count=$c->ledgers()->count();$c->delete();
        $this->assertNull(Customer::find($c->id));$this->assertSame($c->id,$o->fresh()->customer->id);
        $this->assertSame($count,DB::table('cs_customer_ledgers')->where('cs_customer_id',$c->id)->count());
        $this->assertTrue(CustomerPayment::where('cs_customer_id',$c->id)->exists());
    }
    public function test_stock_drift_is_rejected_without_clamping(): void
    {
        [$c,$p]=$this->sale();$p->update(['stock_quantity'=>'99.00']);
        $this->expectException(ValidationException::class);app(InventoryService::class)->move($p->id,'-1.00','Sale','test');
    }
    public function test_tailor_cannot_access_finance_or_other_jobs(): void
    {
        $tailor=User::factory()->create(['role'=>'tailor','is_active'=>true]);$this->actingAs($tailor);
        $this->getJson(route('finance.index'))->assertForbidden();
        $this->getJson(route('cloth-store.payments.index'))->assertForbidden();
    }
    public function test_staff_without_permission_cannot_reverse_payment(): void
    {
        [$c,$p,$o]=$this->sale();$payment=CustomerPayment::where('cs_order_id',$o->id)->firstOrFail();
        $this->actingAs(User::factory()->create(['role'=>'staff','is_active'=>true]));
        $this->putJson(route('cloth-store.payments.reverse',$payment))->assertForbidden();
    }
    public function test_disabled_session_loses_access_immediately(): void
    {
        $u=auth()->user();$u->update(['is_active'=>false]);
        $this->getJson(route('customers.index'))->assertUnauthorized();
    }
    public function test_admin_password_reset_invalidates_prior_session(): void
    {
        $u=auth()->user();$u->update(['password'=>'replacement-password']);
        $this->withSession(['auth_session_version'=>0])->getJson(route('customers.index'))->assertUnauthorized();
    }
    public function test_secret_settings_are_encrypted_and_masked(): void
    {
        Settings::put(['gateway_token'=>'a-secret-that-must-not-be-cached-plaintext']);
        $raw=DB::table('settings')->where('key','gateway_token')->value('value');
        $this->assertStringStartsWith('enc:v1:',$raw);
        $this->assertSame('a-secret-that-must-not-be-cached-plaintext',Settings::str('gateway_token'));
        $this->assertNotSame(Settings::str('gateway_token'),Settings::forClient()['gateway_token']);
    }
    public function test_discounted_invoice_full_refund_is_exact(): void
    {
        [$c,$p,$o]=$this->sale('90.01','10.00','9.99');
        foreach (['3.33','3.33','3.34'] as $qty) {$r=$this->returned($o,$qty);app(ReturnService::class)->transition($r->id,'Completed');}
        $this->assertSame('90.01',(string)DB::table('cs_financial_adjustments')->where('order_id',$o->id)->where('kind','refund')->sum('amount'));
        $this->assertSame('0.00',$c->fresh()->due_balance);
    }
}
