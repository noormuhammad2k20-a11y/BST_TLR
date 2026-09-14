<?php

namespace Tests\Integration;

use App\Models\{Customer, Order, User};
use App\Services\Settings;
use Illuminate\Support\Facades\{DB, Http};
use Tests\TestCase;

final class DeliveryReceiptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('INTEGRITY_MYSQL') !== '1') $this->markTestSkipped('Requires isolated atelier_integrity_test database.');
        $this->assertSame('atelier_integrity_test', DB::connection()->getDatabaseName());
        DB::beginTransaction();
        Settings::flush();
        Settings::put(['sms_enabled'=>false]);
        Http::fake();
        $this->actingAs(User::factory()->create(['role'=>'admin','is_active'=>true]));
    }

    protected function tearDown(): void
    {
        if (getenv('INTEGRITY_MYSQL') === '1') {
            while (DB::transactionLevel() > 0) DB::rollBack();
            Settings::flush();
        }
        parent::tearDown();
    }

    private function order(string $status = 'Ready'): Order
    {
        $customer=Customer::create(['name'=>'Receipt <script>alert(1)</script>', 'phone'=>'03001112222']);
        return Order::create(['customer_id'=>$customer->id,'status'=>$status,'total'=>100,'advance'=>40,
            'balance'=>60,'delivery_date'=>today(),'items'=>[['name'=>'Shirt','qty'=>1,'price'=>100]]]);
    }

    public function test_enabled_collection_prints_final_receipt_with_actual_balance_and_credit(): void
    {
        $this->putJson(route('settings.update'), ['delivery_print_receipt'=>true])->assertOk();
        $order=$this->order();
        $this->postJson(route('delivery.collect',$order))->assertOk()
            ->assertJsonPath('receipt_url',route('delivery.receipt',$order));
        $this->assertSame('Delivered',$order->fresh()->status);
        $this->assertNotNull($order->fresh()->delivered_at);
        $response=$this->get(route('delivery.receipt',$order))->assertOk()
            ->assertSee('FINAL RECEIPT')->assertSee('Total Paid')->assertSee('60.00')
            ->assertSee('Payment Outstanding')->assertSee('Noor M Hingorjo')->assertSee('0303 4980786')
            ->assertSee('&lt;script&gt;',false)->assertDontSee('<script>alert(1)</script>',false);
        $this->assertSame(1,substr_count($response->getContent(),'Noor M Hingorjo'));
        Http::assertNothingSent();
    }

    public function test_disabled_collection_does_not_request_print_and_reprint_is_available(): void
    {
        $this->putJson(route('settings.update'), ['delivery_print_receipt'=>false])->assertOk();
        $order=$this->order();
        $this->postJson(route('delivery.collect',$order))->assertOk()->assertJsonPath('receipt_url',null);
        $this->assertSame('Delivered',$order->fresh()->status);
        $this->get(route('delivery.receipt',$order))->assertOk();
        Http::assertNothingSent();
    }

    public function test_final_receipt_rejects_uncollected_orders_and_invalid_transition(): void
    {
        $order=$this->order('Received');
        $this->get(route('delivery.receipt',$order))->assertStatus(422);
        $this->postJson(route('delivery.collect',$order))->assertStatus(422);
        $this->assertSame('Received',$order->fresh()->status);
    }

    public function test_fully_paid_receipt_respects_paper_width_and_content_switches(): void
    {
        Settings::put(['printer_width'=>'58mm','receipt_show_phone'=>false,'receipt_show_advance'=>true,
            'receipt_show_balance'=>true]);
        $order=$this->order(); $order->update(['advance'=>100,'balance'=>0]);
        $this->postJson(route('delivery.collect',$order))->assertOk();
        $this->get(route('delivery.receipt',$order))->assertOk()->assertSee('Fully Paid')
            ->assertSee('width:50mm',false)->assertDontSee('03001112222');
    }
}
