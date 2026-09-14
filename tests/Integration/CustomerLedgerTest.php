<?php
namespace Tests\Integration;
use App\Models\{Customer,Order,Payment,User};
use App\Services\{CustomerLedger,TailoringFinanceService,Settings};
use Illuminate\Support\Facades\{DB,Http};
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerLedgerTest extends TestCase
{
    private Customer $customer;
    protected function setUp(): void {
        parent::setUp();
        if(getenv('INTEGRITY_MYSQL')!=='1') $this->markTestSkipped('Requires isolated atelier_integrity_test.');
        $this->assertSame('atelier_integrity_test',DB::connection()->getDatabaseName());
        DB::beginTransaction(); Settings::flush(); Settings::put(['sms_enabled'=>false]); Http::fake();
        $this->actingAs(User::factory()->create(['role'=>'admin','is_active'=>true]));
        $this->customer=Customer::create(['name'=>'Ledger test','phone'=>fake()->unique()->numerify('031########')]);
    }
    protected function tearDown(): void {
        if(getenv('INTEGRITY_MYSQL')==='1') { while(DB::transactionLevel()>0) DB::rollBack(); Settings::flush(); }
        parent::tearDown();
    }
    private function order($total=24000,$advance=0): Order {
        return Order::create(['customer_id'=>$this->customer->id,'status'=>'Ready','total'=>$total,'advance'=>$advance,
            'balance'=>$total-$advance,'delivery_date'=>today(),'items'=>[['name'=>'Stitching','qty'=>1,'price'=>$total]]]);
    }
    private function payment($amount): array {
        return ['amount'=>$amount,'payment_method'=>'Cash','operation_key'=>(string)Str::uuid(),'date'=>today()->toDateString()];
    }
    public function test_fully_paid_customer_uses_actual_payments_even_when_stored_balance_is_stale(): void {
        $order=$this->order(); app(TailoringFinanceService::class)->record($order->id,$this->payment(24000));
        DB::table('orders')->where('id',$order->id)->update(['balance'=>24000]);
        $this->getJson(route('customers.summary',$this->customer))->assertOk()->assertJsonPath('customer.due',0)
            ->assertJsonPath('customer.paid',24000)->assertJsonPath('customer.payment_status','Paid');
        $this->getJson(route('orders.receipt',$order))->assertOk()->assertJsonPath('payment_due',0);
    }
    public function test_partial_delivery_and_retry_record_money_once(): void {
        Settings::put(['allow_partial'=>false]);
        $order=$this->order();$data=$this->payment(12000);
        $this->postJson(route('delivery.collect',$order),$data)->assertOk();
        $this->postJson(route('delivery.collect',$order),$data)->assertOk();
        $this->assertSame('Delivered',$order->fresh()->status);
        $this->assertEquals(12000,$order->fresh()->balance_due);
        $this->assertSame(1,Payment::where('order_id',$order->id)->count());
        $this->assertSame('12000.00',app(CustomerLedger::class)->statement($this->customer)['due']);
        Http::assertNothingSent();
    }
    public function test_overpayment_and_invalid_delivery_roll_back_without_cash_or_status_changes(): void {
        $order=$this->order(100);
        $this->postJson(route('delivery.collect',$order),$this->payment(101))->assertUnprocessable();
        $order->update(['status'=>'Received']);
        $this->postJson(route('delivery.collect',$order),$this->payment(50))->assertUnprocessable();
        $this->assertSame(0,Payment::where('order_id',$order->id)->count());
        $this->assertSame('Received',$order->fresh()->status);
    }
    public function test_zero_delivery_retains_due_and_later_payment_clears_without_new_order(): void {
        $order=$this->order(100);
        $this->postJson(route('delivery.collect',$order),$this->payment(0))->assertOk();
        $data=$this->payment(100);
        $this->postJson(route('customers.ledger.payments',$this->customer),$data)->assertOk()->assertJsonPath('statement.due','0.00');
        $this->postJson(route('customers.ledger.payments',$this->customer),$data)->assertOk()->assertJsonPath('statement.due','0.00');
        $this->assertSame(1,Order::where('customer_id',$this->customer->id)->count());
        $this->assertSame(1,Payment::where('customer_id',$this->customer->id)->count());
    }
    public function test_cloth_old_dues_multiple_orders_and_advances_share_one_ledger_without_duplicates(): void {
        $order=$this->order(100,20);
        Payment::create(['customer_id'=>$this->customer->id,'order_id'=>$order->id,'amount'=>20,'type'=>'Advance','status'=>'Completed','payment_method'=>'Cash','date'=>now()]);
        $this->order(100);
        foreach(['Cloth Sale','Opening Due'] as $type) {
            $data=['type'=>$type,'description'=>'Existing reference','amount'=>50,'date'=>today()->toDateString(),'operation_key'=>(string)Str::uuid()];
            $this->postJson(route('customers.ledger.charges',$this->customer),$data)->assertOk();
            $this->postJson(route('customers.ledger.charges',$this->customer),$data)->assertOk();
        }
        $before=app(CustomerLedger::class)->statement($this->customer);
        $this->assertSame('280.00',$before['due']); $this->assertSame('20.00',$before['paid']);
        $this->postJson(route('customers.ledger.payments',$this->customer),$this->payment(280))->assertOk()->assertJsonPath('statement.due','0.00');
        $after=app(CustomerLedger::class)->statement($this->customer);
        $this->assertSame('300.00',$after['paid']); $this->assertSame('0.00',end($after['rows'])['balance']);
        $this->order(200);
        $this->assertSame('200.00',app(CustomerLedger::class)->statement($this->customer)['due']);
    }
    public function test_reversed_payment_restores_due_and_legacy_advance_is_not_counted_twice(): void {
        $order=$this->order(100,20);
        $p=app(TailoringFinanceService::class)->record($order->id,$this->payment(80));
        $this->assertSame('0.00',app(CustomerLedger::class)->statement($this->customer)['due']);
        app(TailoringFinanceService::class)->reverse($p->id);
        $statement=app(CustomerLedger::class)->statement($this->customer);
        $this->assertSame('80.00',$statement['due']); $this->assertSame('20.00',$statement['paid']);
    }

    public function test_delivery_accepts_payment_across_current_and_previous_order_dues(): void {
        $old=$this->order(5000); $old->update(['created_at'=>now()->subDays(5)]);
        $current=$this->order(7200);
        $this->getJson(route('orders.receipt',$current))->assertOk()
            ->assertJsonPath('dues.current_order_due',7200)->assertJsonPath('dues.previous_due',5000)
            ->assertJsonPath('dues.customer_total_due',12200);
        $data=$this->payment(10200);
        $this->postJson(route('delivery.collect',$current),$data)->assertOk()
            ->assertJsonPath('dues.customer_total_due',2000)->assertJsonPath('dues.previous_due',0);
        $this->postJson(route('delivery.collect',$current),$data)->assertOk()->assertJsonPath('dues.customer_total_due',2000);
        $this->assertEquals(0,$old->fresh()->balance_due);
        $this->assertEquals(2000,$current->fresh()->balance_due);
        $this->assertSame(2,Payment::where('customer_id',$this->customer->id)->count());
        $this->getJson(route('customers.summary',$this->customer))->assertOk()->assertJsonPath('customer.due',2000);
        Http::assertNothingSent();
    }

    public function test_historical_sales_are_not_confused_with_balance_before_latest_delivery(): void {
        $old=$this->order(20200,200); $old->update(['created_at'=>now()->subDays(5)]);
        Payment::create(['customer_id'=>$this->customer->id,'order_id'=>$old->id,'amount'=>200,'type'=>'Advance',
            'status'=>'Completed','payment_method'=>'Cash','date'=>now()->subDays(5)]);
        app(TailoringFinanceService::class)->record($old->id,$this->payment(15000),true);
        $current=$this->order(7200);
        $this->assertSame('12200.00',app(CustomerLedger::class)->statement($this->customer)['due']);
        $data=$this->payment(10200);
        $this->postJson(route('delivery.collect',$current),$data)->assertOk();
        $this->postJson(route('delivery.collect',$current),$data)->assertOk();
        $this->assertSame(4,Payment::where('customer_id',$this->customer->id)->count());
        $this->getJson(route('customers.summary',$this->customer))->assertOk()
            ->assertJsonPath('customer.spent',27400)->assertJsonPath('customer.paid',25400)->assertJsonPath('customer.due',2000);
        $this->getJson(route('orders.receipt',$current))->assertOk()->assertJsonPath('dues.customer_total_due',2000);
        $row=collect(app(\App\Services\CollectionBoard::class)->data()['deliveries'])->firstWhere('db_id',$current->id);
        $this->assertEquals(2000,$row['customer_total_due']);
        $statement=app(CustomerLedger::class)->statement($this->customer);
        $this->assertSame('2000.00',end($statement['rows'])['balance']);
        Http::assertNothingSent();
    }

    public function test_delivery_can_clear_current_order_and_opening_due_in_full_but_not_overpay(): void {
        $current=$this->order(7200);
        $this->postJson(route('customers.ledger.charges',$this->customer),[
            'type'=>'Opening Due','description'=>'Previous dues','amount'=>5000,'date'=>today()->subDays(5)->toDateString(),
            'operation_key'=>(string)Str::uuid()])->assertOk();
        $this->postJson(route('delivery.collect',$current),$this->payment(12201))->assertUnprocessable();
        $this->assertSame('Ready',$current->fresh()->status);
        $this->assertSame(0,Payment::where('customer_id',$this->customer->id)->count());
        $this->postJson(route('delivery.collect',$current),$this->payment(12200))->assertOk()
            ->assertJsonPath('dues.current_order_due',0)->assertJsonPath('dues.previous_due',0)
            ->assertJsonPath('dues.customer_total_due',0);
        $this->getJson(route('customers.summary',$this->customer))->assertOk()->assertJsonPath('customer.payment_status','Paid');
        $statement=app(CustomerLedger::class)->statement($this->customer);
        $this->assertSame('0.00',end($statement['rows'])['balance']);
    }
}
